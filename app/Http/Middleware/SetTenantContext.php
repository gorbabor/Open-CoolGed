<?php

namespace App\Http\Middleware;

use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetTenantContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            TenantContext::set($user->is_super_admin ? null : $user->tenant_id);
        } else {
            TenantContext::set(null);
        }

        return $next($request);
    }
}
