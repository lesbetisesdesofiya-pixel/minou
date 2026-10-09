<?php

namespace App\Services;

use App\Models\Dish;
use App\Models\DishVariation;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\DB;

/**
 * Création de commande partagée entre le checkout web et POST /api/orders.
 * Recalcule les prix côté serveur (le total client n'est jamais trusté).
 *
 * @return array ['order' => Order, 'subtotal' => ..., 'service_fee' => ..., 'delivery_fee' => ..., 'total' => ..., 'fcm' => ...]
 */
class OrderService
{
    public function create(array $data): array
    {
        $this->validate($data);

        $pricing = new PricingService();
        $serviceType  = $data['serviceType'] ?? 'emporter';
        $neighborhood = $data['neighborhood'] ?? null;
        $quote        = $pricing->quote($data['items'] ?? [], $serviceType, $neighborhood);

        DB::beginTransaction();
        try {
            // Paiement désactivé (PAYMENT_DISABLED) : la commande passe directement en cuisine
            $skipPayment = (bool) config('services.moneyfusion.disabled');
            $order = Order::create([
                'service_type'          => $serviceType,
                'client_name'           => $data['name']          ?? null,
                'client_phone'          => $data['phone']         ?? null,
                'neighborhood'          => $neighborhood,
                'table_number'          => $data['table']         ?? null,
                'notes'                 => $data['notes']         ?? null,
                'subtotal_amount'       => $quote['subtotal'],
                'service_fee'           => $quote['service_fee'],
                'delivery_fee'          => $quote['delivery_fee'],
                'total_amount'          => $quote['total'],
                'status'                => $skipPayment ? 'Payée' : 'En attente de paiement',
                'payment_method'        => $skipPayment ? 'sans_paiement' : ($data['paymentMethod'] ?? null),
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

            // Notification push cuisine (échec silencieux)
            $fcmResult = [];
            try {
                $fcmService = new FcmService();
                $fcmResult  = $fcmService->sendOrderNotification($order->id, array_merge($data, ['total' => $quote['total']]));
            } catch (\Exception $e) {
                error_log("FCM Error: " . $e->getMessage());
            }

            return array_merge($quote, ['order' => $order, 'fcm' => $fcmResult]);
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Validation stricte anti-abus (400 si rejeté). Les prix sont recalculés
     * serveur de toute façon ; ici on bloque paniers vides/absurdes et champs hors format.
     */
    private function validate(array $data): void
    {
        if (empty($data) || !is_array($data)) {
            throw new \InvalidArgumentException('Données invalides');
        }

        $serviceType = $data['serviceType'] ?? 'emporter';
        if (!in_array($serviceType, ['livraison', 'emporter'], true)) {
            throw new \InvalidArgumentException('Type de service invalide');
        }

        $items = $data['items'] ?? [];
        if (!is_array($items) || count($items) === 0) {
            throw new \InvalidArgumentException('Panier vide');
        }
        if (count($items) > 50) {
            throw new \InvalidArgumentException('Trop d’articles (max 50 lignes)');
        }
        foreach ($items as $item) {
            if (!is_array($item)) {
                throw new \InvalidArgumentException('Article invalide');
            }
            $qty = $item['quantity'] ?? 1;
            if (!is_numeric($qty) || (int) $qty < 1 || (int) $qty > 99) {
                throw new \InvalidArgumentException('Quantité invalide (1-99)');
            }
            $price = $item['itemPrice'] ?? null;
            if (!is_numeric($price) || (float) $price < 0 || (float) $price > 10000000) {
                throw new \InvalidArgumentException('Prix invalide');
            }
            if (isset($item['name']) && mb_strlen((string) $item['name']) > 120) {
                throw new \InvalidArgumentException('Nom d’article trop long');
            }
            if (isset($item['selectedOptions']) && (!is_array($item['selectedOptions']) || count($item['selectedOptions']) > 20)) {
                throw new \InvalidArgumentException('Options invalides');
            }
        }

        foreach (['name' => 80, 'phone' => 20, 'neighborhood' => 100] as $field => $max) {
            if (isset($data[$field]) && mb_strlen((string) $data[$field]) > $max) {
                throw new \InvalidArgumentException("Champ {$field} trop long (max {$max})");
            }
        }
        if (isset($data['notes']) && mb_strlen((string) $data['notes']) > 500) {
            throw new \InvalidArgumentException('Notes trop longues (max 500)');
        }
        if ($serviceType === 'livraison' && empty(trim((string) ($data['neighborhood'] ?? '')))) {
            throw new \InvalidArgumentException('Quartier requis pour la livraison');
        }
    }
}
