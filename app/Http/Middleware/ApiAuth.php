<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! $token) {
            return response()->json(['error' => 'Jeton API manquant.'], 401);
        }

        $apiToken = ApiToken::where('token_hash', ApiToken::hash($token))->first();

        if (! $apiToken || ($apiToken->expires_at && $apiToken->expires_at->isPast())) {
            return response()->json(['error' => 'Jeton API invalide ou expiré.'], 401);
        }

        $user = $apiToken->user;
        if (! $user || $user->isSuspended() || $user->tenant?->isSuspended()) {
            return response()->json(['error' => 'Compte ou tenant suspendu.'], 403);
        }

        $request->setUserResolver(fn () => $user);
        $request->attributes->set('api_token_id', $apiToken->id);
        TenantContext::set($user->tenant_id);

        $apiToken->update(['last_used_at' => now()]);

        return $next($request);
    }
}
