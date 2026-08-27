<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            // Priorité : préférence utilisateur > langue du tenant > locale plateforme.
            $locale = $user->locale;
            if (! $locale && $user->tenant_id) {
                $locale = $user->tenant->settings['language'] ?? null;
            }
            if (in_array($locale, ['fr', 'en'], true)) {
                App::setLocale($locale);
            }
        }

        return $next($request);
    }
}
