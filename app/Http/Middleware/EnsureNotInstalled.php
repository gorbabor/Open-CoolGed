<?php

namespace App\Http\Middleware;

use App\Services\InstallerService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureNotInstalled
{
    public function __construct(private InstallerService $installer) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->installer->isInstalled()) {
            abort(404);
        }

        return $next($request);
    }
}
