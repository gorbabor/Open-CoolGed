<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InstallMode
{
    /** Avant l'ouverture de session : l'assistant ne doit dépendre d'aucune table (base non créée). */
    public function handle(Request $request, Closure $next): Response
    {
        config([
            'session.driver' => 'file',
            'session.files' => storage_path('framework/sessions'),
        ]);

        return $next($request);
    }
}
