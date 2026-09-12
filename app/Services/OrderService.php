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
        if (empty($data)) {
            throw new \InvalidArgumentException('Données invalides');
        }

        $pricing = new PricingService();
        $serviceType  = $data['serviceType'] ?? 'emporter';
        $neighborhood = $data['neighborhood'] ?? null;
        $quote        = $pricing->quote($data['items'] ?? [], $serviceType, $neighborhood);

        DB::beginTransaction();
        try {
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
}
