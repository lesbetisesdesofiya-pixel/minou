<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Dish;
use App\Models\DishVariation;
use App\Services\MoneyFusionService;
use App\Services\OrderService;
use App\Services\PricingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CheckoutController extends Controller
{
    public function index()
    {
        return view('checkout', [
            'deliveryZones'         => $this->deliveryZonesMap(),
            'deliveryDefaultFee'    => config('delivery.default', 1000),
            // Paiement désactivé : le checkout valide la commande directement
            'paymentDisabled'       => (bool) config('services.moneyfusion.disabled'),
            // Mode TEST : MoneyFusion non configuré → parcours de simulation direct
            'moneyfusionConfigured' => (bool) config('services.moneyfusion.api_url'),
        ]);
    }

    /**
     * Quartiers à tarif spécial (délégué au PricingService, source unique).
     * @return array quartier => tarif
     */
    private function deliveryZonesMap(): array
    {
        return (new PricingService())->zonesMap();
    }

    public function store(Request $request)
    {
        $data = $request->json()->all();

        if (empty($data)) {
            return response()->json(['success' => false, 'message' => 'Données invalides'], 400);
        }

        try {
            $result = (new OrderService())->create($data);
            $order  = $result['order'];

            return response()->json([
                'success'    => true,
                'order_id'   => $order->id,
                'subtotal'   => $result['subtotal'],
                'service_fee'=> $result['service_fee'],
                'delivery_fee' => $result['delivery_fee'],
                'total'      => $result['total'],
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Erreur serveur: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Annuler une commande en cours et restituer les stocks de poissons
     */
    public function cancel(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $order = Order::with('items')->find($id);
            if (!$order) {
                return response()->json(['success' => false, 'message' => 'Commande introuvable'], 404);
            }

            if ($order->status === 'Annulée') {
                return response()->json(['success' => true, 'message' => 'Commande déjà annulée']);
            }

            // Sécurité : on ne peut annuler qu'une commande non payée / non préparée.
            // (Pas de remboursement automatique : une commande Payée ne s'annule qu'au restaurant.)
            if (!in_array($order->status, ['En attente de paiement', 'pending'], true)) {
                return response()->json(['success' => false, 'message' => 'Commande déjà payée ou en cours : annulation impossible en ligne, contactez le restaurant.'], 409);
            }

            // Mettre à jour le statut
            $order->update(['status' => 'Annulée']);

            // Restituer les stocks
            foreach ($order->items as $item) {
                $dish = Dish::where('nom', $item->product_name)->first();
                if ($dish) {
                    // Décrémenter l'orders_count
                    $dish->decrement('orders_count', $item->quantity);

                    // Reconstituer le stock pour la catégorie Poissons
                    $options = array_map('trim', explode(',', $item->options_text ?? ''));
                    foreach ($options as $optName) {
                        if (empty($optName)) continue;

                        $variation = DishVariation::whereHas('dish.category', fn($q) => $q->where('nom', 'Poissons'))
                            ->where('dish_id', $dish->id)
                            ->where('name', $optName)
                            ->first();

                        if ($variation) {
                            $variation->increment('stock_kg', $item->quantity);
                        }
                    }
                }
            }

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Commande annulée avec succès, stocks restitués.']);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Erreur lors de l\'annulation: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Déclenche l'alarme FCM "paiement confirmé" (app Android, même verrouillée).
     * N'échoue jamais le flux : toute erreur est loggée côté serveur.
     */
    private function firePaidAlarm(Order $order): void
    {
        try {
            (new \App\Services\FcmService())->sendPaymentConfirmedNotification($order->fresh());
        } catch (\Throwable $e) {
            Log::error('Paid alarm error: ' . $e->getMessage());
        }
    }

    /**
     * Frais de livraison automatiques (délégué au PricingService, source unique).
     */
    private function resolveDeliveryFee(?string $serviceType, ?string $neighborhood): int
    {
        return (new PricingService())->resolveDeliveryFee($serviceType, $neighborhood);
    }

    /**
     * Initier un paiement MoneyFusion pour une commande existante.
     * POST /orders/{id}/moneyfusion/initiate { numeroSend, nomclient }
     */
    public function initiateMoneyFusion(Request $request, $id)
    {
        $order = Order::with('items')->find($id);
        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Commande introuvable'], 404);
        }

        if ($order->status === 'Payée') {
            return response()->json(['success' => true, 'message' => 'Commande déjà payée', 'already_paid' => true]);
        }

        $numeroSend = preg_replace('/\s+/', '', trim($request->input('numeroSend', $order->client_phone ?? '')));
        $nomclient  = trim($request->input('nomclient', $order->client_name ?? ''));

        if (!preg_match('/^[\d+\-()]{8,20}$/', $numeroSend ?? '')) {
            return response()->json(['success' => false, 'message' => 'Numéro Mobile Money invalide'], 400);
        }
        if (mb_strlen($nomclient) < 2 || mb_strlen($nomclient) > 80) {
            return response()->json(['success' => false, 'message' => 'Nom du payeur invalide'], 400);
        }

        // Articles au format MoneyFusion : [{ nom_article: prix }]
        // + ligne frais de service (10%) pour transparence
        $articles = [];
        foreach ($order->items as $item) {
            $articles[] = [$item->product_name => (float) $item->price * (int) $item->quantity];
        }
        if ((float) ($order->service_fee ?? 0) > 0) {
            $articles[] = ['Frais de service (10%)' => (float) $order->service_fee];
        }
        if ((float) ($order->delivery_fee ?? 0) > 0) {
            $articles[] = ['Frais de livraison' => (float) $order->delivery_fee];
        }
        if (empty($articles)) {
            $articles[] = ['Commande #' . $order->id => (float) $order->total_amount];
        }

        $paymentData = [
            'totalPrice'    => (int) round((float) $order->total_amount),
            'article'       => $articles,
            'personal_Info' => [['orderId' => $order->id]],
            'numeroSend'    => $numeroSend,
            'nomclient'     => $nomclient,
            'return_url'    => route('payment.callback') . '?order_id=' . $order->id,
            'webhook_url'   => route('payment.webhook'),
        ];

        try {
            $service = new MoneyFusionService();
            $result  = $service->initiate($paymentData);

            if (empty($result['statut']) || empty($result['token']) || empty($result['url'])) {
                return response()->json(['success' => false, 'message' => $result['message'] ?? 'Réponse MoneyFusion invalide'], 502);
            }

            $order->update([
                'payment_method'     => 'moneyfusion',
                'payment_token'      => $result['token'],
                'payment_url'        => $result['url'],
                'moneyfusion_status' => 'pending',
                'client_phone'       => $order->client_phone ?: $numeroSend,
                'client_name'        => $order->client_name ?: $nomclient,
            ]);

            return response()->json([
                'success'     => true,
                'payment_url' => $result['url'],
                'token'       => $result['token'],
                'message'     => $result['message'] ?? 'Paiement en cours',
            ]);
        } catch (\Throwable $e) {
            Log::error("MoneyFusion initiate error: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur MoneyFusion: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Vérifier le statut d'un paiement (polling frontend).
     * GET /orders/{id}/moneyfusion/status
     */
    public function moneyFusionStatus(Request $request, $id)
    {
        $order = Order::find($id);
        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Commande introuvable'], 404);
        }

        if ($order->status === 'Payée') {
            return response()->json(['success' => true, 'paid' => true, 'status' => 'paid', 'order' => $order]);
        }

        if (!$order->payment_token) {
            return response()->json(['success' => false, 'message' => 'Aucun paiement initié pour cette commande'], 400);
        }

        try {
            $service = new MoneyFusionService();
            $result  = $service->checkStatus($order->payment_token);
            $data    = $result['data'] ?? [];
            $statut  = strtolower($data['statut'] ?? '');

            if ($statut === 'paid') {
                $order->update([
                    'status'                => 'Payée',
                    'moneyfusion_status'    => 'paid',
                    'transaction_reference' => $data['numeroTransaction'] ?? $order->payment_token,
                ]);
                $this->firePaidAlarm($order);

                return response()->json(['success' => true, 'paid' => true, 'status' => 'paid', 'order' => $order->fresh()]);
            }

            if (in_array($statut, ['failure', 'no paid', 'cancelled', 'canceled'])) {
                $order->update(['moneyfusion_status' => $statut]);
                return response()->json(['success' => true, 'paid' => false, 'status' => $statut]);
            }

            return response()->json(['success' => true, 'paid' => false, 'status' => $statut ?: 'pending']);
        } catch (\Throwable $e) {
            Log::error("MoneyFusion status error: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur vérification: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Retour client après paiement (return_url MoneyFusion).
     * GET /payment/callback?order_id=...
     */
    public function paymentCallback(Request $request)
    {
        $orderId = $request->query('order_id');
        $order   = $orderId ? Order::find($orderId) : null;

        if ($order && $order->status !== 'Payée' && $order->payment_token) {
            try {
                $service = new MoneyFusionService();
                $result  = $service->checkStatus($order->payment_token);
                $data    = $result['data'] ?? [];
                if (strtolower($data['statut'] ?? '') === 'paid') {
                    $order->update([
                        'status'                => 'Payée',
                        'moneyfusion_status'    => 'paid',
                        'transaction_reference' => $data['numeroTransaction'] ?? $order->payment_token,
                    ]);
                    $this->firePaidAlarm($order);
                }
            } catch (\Throwable $e) {
                Log::error("MoneyFusion callback check error: " . $e->getMessage());
            }
        }

        if ($order) {
            return redirect()->route('track', ['id' => $order->id]);
        }

        return redirect()->route('menu');
    }

    /**
     * SIMULATION (TEST UNIQUEMENT) : marquer une commande comme payée sans MoneyFusion.
     * Actif SEULEMENT si MONEYFUSION_API_URL est vide. Dès que la vraie URL
     * est renseignée, cet endpoint répond 403 — aucun risque en production.
     * POST /orders/{id}/moneyfusion/simulate
     */
    public function simulatePayment(Request $request, $id)
    {
        if (config('services.moneyfusion.api_url')) {
            return response()->json(['success' => false, 'message' => 'Simulation désactivée : MoneyFusion est configuré.'], 403);
        }

        $order = Order::find($id);
        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Commande introuvable'], 404);
        }

        if ($order->status === 'Payée') {
            return response()->json(['success' => true, 'message' => 'Commande déjà payée', 'already_paid' => true, 'order' => $order->fresh()]);
        }

        if (!in_array($order->status, ['En attente de paiement', 'pending'], true)) {
            return response()->json(['success' => false, 'message' => 'Simulation impossible à ce stade.'], 409);
        }

        $order->update([
            'status'                => 'Payée',
            'payment_method'        => 'moneyfusion',
            'moneyfusion_status'    => 'simulated',
            'transaction_reference' => 'SIM-' . strtoupper(uniqid()),
        ]);
        $this->firePaidAlarm($order);

        return response()->json([
            'success'      => true,
            'simulated'    => true,
            'message'      => 'Paiement simulé (mode TEST).',
            'order'        => $order->fresh(),
            'subtotal'     => $order->subtotal_amount,
            'service_fee'  => $order->service_fee,
            'delivery_fee' => $order->delivery_fee,
            'total'        => $order->total_amount,
        ]);
    }

    /**
     * Webhook MoneyFusion (POST). Idempotent via tokenPay.
     * POST /payment/webhook
     */
    public function paymentWebhook(Request $request)
    {
        $payload = $request->all();
        $event   = $payload['event'] ?? '';
        $token   = $payload['tokenPay'] ?? null;

        if (!$token) {
            return response()->json(['success' => false, 'message' => 'tokenPay manquant'], 400);
        }

        // Retrouve la commande par token, sinon via personal_Info.orderId
        $order = Order::where('payment_token', $token)->first();
        if (!$order) {
            $orderId = $payload['personal_Info'][0]['orderId'] ?? null;
            $order   = $orderId ? Order::find($orderId) : null;
        }

        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Commande introuvable'], 404);
        }

        // Ignore les notifications redondantes
        if ($order->status === 'Payée' && in_array($event, ['payin.session.pending', 'payin.session.completed'])) {
            return response()->json(['success' => true, 'message' => 'Déjà traitée']);
        }

        if ($event === 'payin.session.completed') {
            // Anti-fraude : le montant notifié doit couvrir la commande
            $notified = (float) ($payload['Montant'] ?? 0);
            if ($notified > 0 && $notified < (float) $order->total_amount) {
                Log::warning("MoneyFusion webhook montant insuffisant order={$order->id} notified={$notified}");
                return response()->json(['success' => false, 'message' => 'Montant insuffisant'], 400);
            }

            // Anti-fraude : si l'API est configurée, on re-vérifie le statut
            // en serveur-à-serveur avant de marquer Payée (un POST forgé ne suffit pas).
            if (config('services.moneyfusion.api_url')) {
                try {
                    $check  = (new MoneyFusionService())->checkStatus($token);
                    $remote = strtolower($check['data']['statut'] ?? '');
                    if ($remote !== 'paid') {
                        $order->update(['moneyfusion_status' => $remote ?: 'pending']);
                        return response()->json(['success' => true, 'message' => 'En attente de confirmation MoneyFusion']);
                    }
                    $ref = $check['data']['numeroTransaction'] ?? ($payload['numeroTransaction'] ?? $token);
                } catch (\Throwable $e) {
                    Log::error("MoneyFusion webhook verify error: " . $e->getMessage());
                    return response()->json(['success' => true, 'message' => 'Vérification reportée']);
                }
            } else {
                $ref = $payload['numeroTransaction'] ?? $token;
            }

            $order->update([
                'status'                => 'Payée',
                'moneyfusion_status'    => 'paid',
                'transaction_reference' => $ref,
            ]);
            $this->firePaidAlarm($order);
        } elseif ($event === 'payin.session.cancelled') {
            $order->update(['moneyfusion_status' => 'cancelled']);
        } else { // pending ou autre : simple suivi
            if ($order->moneyfusion_status !== 'paid') {
                $order->update(['moneyfusion_status' => 'pending']);
            }
        }

        return response()->json(['success' => true]);
    }
}
