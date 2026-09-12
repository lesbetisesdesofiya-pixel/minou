<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MoneyFusionService
{
    public function initiate(array $paymentData): array
    {
        $apiUrl = config('services.moneyfusion.api_url');

        if (!$apiUrl) {
            throw new \Exception("MONEYFUSION_API_URL non configurée. Renseignez-la depuis le tableau de bord MoneyFusion.");
        }

        $response = Http::withHeaders(['Content-Type' => 'application/json'])
            ->timeout(30)
            ->post($apiUrl, $paymentData);

        if (!$response->successful()) {
            throw new \Exception("Erreur MoneyFusion: HTTP " . $response->status() . " - " . $response->body());
        }

        return $response->json();
    }

    public function checkStatus(string $token): array
    {
        $base = rtrim(config('services.moneyfusion.status_url', 'https://pay.moneyfusion.net/paiementNotif'), '/');

        $response = Http::timeout(20)->get("{$base}/{$token}");

        if (!$response->successful()) {
            throw new \Exception("Erreur vérification statut: HTTP " . $response->status());
        }

        return $response->json();
    }
}
