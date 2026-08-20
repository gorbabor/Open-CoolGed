<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * SSO/OIDC par tenant (V2). Flux mock : le fournisseur renvoie l'email de
 * l'utilisateur sur le callback. En production, un adaptateur OIDC réel
 * (code + échange de jetons) remplace ce contrôleur sans changer les routes.
 */
class SsoController extends Controller
{
    public function __construct(private AuditService $audit) {}

    public function start(Request $request)
    {
        $tenant = Tenant::where('slug', $request->input('tenant'))->firstOrFail();

        if (($tenant->settings['sso_enabled'] ?? false) !== true || ! config('ged.sso_enabled')) {
            abort(404, 'SSO non activé pour ce tenant.');
        }

        $this->audit->log('sso.redirected', 'tenant', $tenant->id, ['provider' => 'mock']);

        // Simulation du fournisseur OIDC : retour immédiat sur le callback.
        return redirect()->route('sso.callback', ['tenant' => $tenant->slug, 'email' => $request->input('email')]);
    }

    public function callback(Request $request)
    {
        $tenant = Tenant::where('slug', $request->input('tenant'))->firstOrFail();
        $email = $request->input('email');

        if (($tenant->settings['sso_enabled'] ?? false) !== true || ! config('ged.sso_enabled')) {
            abort(404, 'SSO non activé pour ce tenant.');
        }

        $user = User::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('email', $email)
            ->where('status', 'active')
            ->first();

        if (! $user) {
            $this->audit->log('sso.failed', 'tenant', $tenant->id, ['email' => $email]);

            return redirect()->route('login')->withErrors(['email' => 'Aucun compte SSO actif pour cet email.']);
        }

        Auth::login($user);
        $user->update(['last_login_at' => now()]);
        $this->audit->log('sso.login.success', 'user', $user->id);

        return redirect()->route('dashboard');
    }
}
