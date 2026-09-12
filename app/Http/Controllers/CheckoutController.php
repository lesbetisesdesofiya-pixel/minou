<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Dish;
use App\Models\DishVariation;
use App\Services\FcmService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CheckoutController extends Controller
{
    public function index()
    {
        return view('checkout');
    }

    public function store(Request $request)
    {
        $data = $request->json()->all();

        if (empty($data)) {
            return response()->json(['success' => false, 'message' => 'Données invalides'], 400);
        }

        DB::beginTransaction();
        try {
            $order = Order::create([
                'service_type'          => $data['serviceType']  ?? 'emporter',
                'client_name'           => $data['name']          ?? null,
                'client_phone'          => $data['phone']         ?? null,
                'neighborhood'          => $data['neighborhood']  ?? null,
                'table_number'          => $data['table']         ?? null,
                'notes'                 => $data['notes']         ?? null,
                'total_amount'          => $data['total']         ?? 0,
                'status'                => 'En attente de paiement',
                'payment_method'        => $data['paymentMethod'] ?? null,
                'transaction_reference' => $data['transactionReference'] ?? null,
            ]);

            foreach ($data['items'] ?? [] as $item) {
                $options     = $item['selectedOptions'] ?? [];
                $optionsText = implode(', ', array_map(fn($o) => is_array($o) ? $o['name'] : $o, $options));

                OrderItem::create([
                    'order_id'     => $order->id,
                    'product_name' => $item['name']      ?? 'Article',
                    'quantity'     => $item['quantity']  ?? 1,
                    'price'        => $item['itemPrice'] ?? 0,
                    'options_text' => $optionsText,
                ]);

                $dishId = $item['id'] ?? null;
                if ($dishId) {
                    $qty = $item['quantity'] ?? 1;
                    Dish::where('id', $dishId)->increment('orders_count', $qty);

                    // Decrement fish stock
                    foreach ($options as $opt) {
                        $optName = is_array($opt) ? ($opt['name'] ?? '') : $opt;
                        if (!$optName) continue;

                        $variation = DishVariation::whereHas('dish.category', fn($q) => $q->where('nom', 'Poissons'))
                            ->where('dish_id', $dishId)
                            ->where('name', $optName)
                            ->first();

                        if ($variation) {
                            $variation->decrement('stock_kg', $qty);
                        }
                    }
                }
            }

            DB::commit();

            // Send FCM notification for pending payment order
            $fcmResult = [];
            try {
                $fcmService = new FcmService();
                $fcmResult  = $fcmService->sendOrderNotification($order->id, $data);
            } catch (\Exception $e) {
                error_log("FCM Error: " . $e->getMessage());
            }

            return response()->json([
                'success'    => true,
                'order_id'   => $order->id,
                '_fcm_debug' => $fcmResult,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
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
     * Valider automatiquement le paiement avec Gemini Vision API
     */
    public function validatePayment(Request $request, $id)
    {
        $order = Order::find($id);
        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Commande introuvable'], 404);
        }

        if (!$request->hasFile('screenshot')) {
            return response()->json(['success' => false, 'message' => 'La capture d\'écran du reçu est requise.'], 400);
        }

        // Sauvegarder l'image
        $path = $request->file('screenshot')->store('screenshots', 'public');
        $imagePath = storage_path('app/public/' . $path);
        $imageData = base64_encode(file_get_contents($imagePath));
        $mimeType = $request->file('screenshot')->getMimeType();

        $apiKey = env('GEMINI_API_KEY');

        // Mode Démo / Simulation si clé manquante ou fictive
        if (!$apiKey || $apiKey === 'AIzaSyFakeKeyForNowPleaseChangeMe' || strpos($apiKey, 'FakeKey') !== false) {
            $refRead = 'SIM-' . strtoupper(uniqid());
            $order->update([
                'status' => 'Payée',
                'transaction_reference' => $refRead,
                'screenshot_path' => $path
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Paiement validé avec succès (Mode Démo / Clé API Gemini simulée).',
                'order' => $order,
                '_demo' => true
            ]);
        }

        try {
            $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . $apiKey;

            $prompt = "Analyze this payment receipt screenshot. Return a JSON object with the following fields: "
                    . "'montant_paye' (number, the paid amount in CFA/F), "
                    . "'nom_ou_code_marchand' (string, the merchant name or code), "
                    . "'reference_transaction' (string, the unique transaction reference/ID), "
                    . "and 'date_paiement' (string, format YYYY-MM-DD).";

            $payload = [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt],
                            [
                                'inlineData' => [
                                    'mimeType' => $mimeType,
                                    'data' => $imageData
                                ]
                            ]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'responseMimeType' => 'application/json',
                    'responseSchema' => [
                        'type' => 'OBJECT',
                        'properties' => [
                            'montant_paye' => ['type' => 'NUMBER'],
                            'nom_ou_code_marchand' => ['type' => 'STRING'],
                            'reference_transaction' => ['type' => 'STRING'],
                            'date_paiement' => ['type' => 'STRING']
                        ],
                        'required' => ['montant_paye', 'nom_ou_code_marchand', 'reference_transaction', 'date_paiement']
                    ]
                ]
            ];

            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                            ->timeout(30)
                            ->post($endpoint, $payload);

            if (!$response->successful()) {
                throw new \Exception("Erreur de communication avec l'API Gemini: HTTP " . $response->status());
            }

            $resData = $response->json();
            $text = $resData['candidates'][0]['content']['parts'][0]['text'] ?? '{}';
            $extracted = json_decode(trim($text), true);

            if (!$extracted || !isset($extracted['montant_paye']) || !isset($extracted['nom_ou_code_marchand']) || !isset($extracted['reference_transaction']) || !isset($extracted['date_paiement'])) {
                return response()->json(['success' => false, 'message' => 'Impossible de lire correctement les informations du reçu. Veuillez renvoyer une image plus nette.'], 400);
            }

            // 1. Validation de la date
            $today = date('Y-m-d');
            if ($extracted['date_paiement'] !== $today) {
                return response()->json(['success' => false, 'message' => "La date du reçu (" . $extracted['date_paiement'] . ") ne correspond pas à la date d'aujourd'hui ($today)."], 400);
            }

            // 2. Validation du marchand
            $expectedMerchant = $order->payment_method === 'tmoney' ? env('CODE_MARCHAND_TMONEY') : env('CODE_MARCHAND_MOOV');
            $merchantRead = strval($extracted['nom_ou_code_marchand']);
            if (stripos($merchantRead, $expectedMerchant) === false && stripos($expectedMerchant, $merchantRead) === false) {
                return response()->json(['success' => false, 'message' => "Le marchand détecté ($merchantRead) ne correspond pas au marchand attendu ($expectedMerchant)."], 400);
            }

            // 3. Validation du montant
            $amountRead = floatval($extracted['montant_paye']);
            $orderAmount = floatval($order->total_amount);
            if ($amountRead < $orderAmount) {
                return response()->json(['success' => false, 'message' => "Le montant payé détecté ($amountRead F) est inférieur au montant requis ($orderAmount F)."], 400);
            }

            // 4. Référence unique (sécurité anti-fraude)
            $refRead = trim($extracted['reference_transaction']);
            if (empty($refRead)) {
                return response()->json(['success' => false, 'message' => "La référence de transaction est vide sur le reçu."], 400);
            }

            $exists = Order::where('transaction_reference', $refRead)
                           ->where('id', '!=', $order->id)
                           ->where('status', 'Payée')
                           ->exists();
            if ($exists) {
                return response()->json(['success' => false, 'message' => "Ce reçu a déjà été utilisé pour valider une autre commande (Tentative de fraude détectée)."], 400);
            }

            // Tout est valide !
            $order->update([
                'status' => 'Payée',
                'transaction_reference' => $refRead,
                'screenshot_path' => $path
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Paiement validé avec succès par Gemini Vision !',
                'order' => $order
            ]);

        } catch (\Throwable $e) {
            Log::error("Gemini Validation Error: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Erreur lors de la validation du reçu: ' . $e->getMessage()], 500);
        }
    }
}
