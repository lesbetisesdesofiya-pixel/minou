<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Clé API obligatoire sur les endpoints publics (/api/menu, /api/orders, ...).
 * - Header : X-API-Key: <API_KEY> (ou ?api_key= en secours).
 * - /api/auth/login (throttlé) et /api/payment/webhook (anti-fraude propre)
 *   restent ouverts : ils n'utilisent PAS ce middleware.
 * - Fail-closed : sans API_KEY configurée, tout est refusé (401).
 */
class RequireApiKey
{
    public function handle(Request $request, Closure $next)
    {
        $expected = (string) config('services.api.key', '');

        $provided = trim((string) $request->header('X-API-Key', ''));
        if ($provided === '') {
            $provided = trim((string) $request->query('api_key', ''));
        }

        if ($expected === '' || $provided === '' || !hash_equals($expected, $provided)) {
            return response()->json(['error' => 'Clé API requise (header X-API-Key)'], 401);
        }

        return $next($request);
    }
}
