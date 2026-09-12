<?php

namespace App\Services;

use App\Models\DeliveryZone;

/**
 * Source de vérité des prix : sous-total, frais de service, livraison.
 * Utilisé par le checkout web ET par l'API (POST /api/quote).
 *
 * Règles :
 * - frais de service = 10% du sous-total articles UNIQUEMENT (hors livraison)
 * - livraison = 0 si emporter, sinon tarif zone du quartier ou forfait par défaut
 * - total = sous-total + service + livraison
 */
class PricingService
{
    public function feeRate(): float
    {
        return (float) config('services.moneyfusion.fee_rate', 0.10);
    }

    public function defaultDeliveryFee(): int
    {
        return (int) config('delivery.default', 1000);
    }

    /**
     * @return array quartier => tarif (BDD admin prioritaire, config en secours)
     */
    public function zonesMap(): array
    {
        $map = [];
        foreach (config('delivery.zones', []) as $quartier => $tarif) {
            $map[trim((string) $quartier)] = (int) $tarif;
        }
        foreach (DeliveryZone::where('active', true)->orderBy('quartier')->get() as $zone) {
            $map[trim($zone->quartier)] = (int) $zone->fee;
        }
        return $map;
    }

    public function resolveDeliveryFee(?string $serviceType, ?string $neighborhood): int
    {
        if ($serviceType !== 'livraison') {
            return 0;
        }

        $needle = mb_strtolower(trim((string) $neighborhood));
        foreach ($this->zonesMap() as $quartier => $tarif) {
            if ($needle !== '' && $needle === mb_strtolower(trim((string) $quartier))) {
                return (int) $tarif;
            }
        }

        return $this->defaultDeliveryFee();
    }

    /**
     * @param array $items [{ itemPrice, quantity }]
     */
    public function quote(array $items, ?string $serviceType, ?string $neighborhood): array
    {
        $subtotal = 0;
        foreach ($items as $item) {
            $subtotal += (float) ($item['itemPrice'] ?? 0) * (int) ($item['quantity'] ?? 1);
        }
        $serviceFee  = (int) round($subtotal * $this->feeRate());
        $deliveryFee = $this->resolveDeliveryFee($serviceType, $neighborhood);

        return [
            'subtotal'     => $subtotal,
            'service_fee'  => $serviceFee,
            'delivery_fee' => $deliveryFee,
            'total'        => (int) ($subtotal + $serviceFee + $deliveryFee),
        ];
    }
}
