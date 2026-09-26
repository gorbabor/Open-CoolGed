<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditService;
use App\Services\MenuService;
use App\Services\MfaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function __construct(private MfaService $mfa) {}

    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request, AuditService $audit)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $user = User::withoutGlobalScopes()->where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            $audit->log('auth.login.failed', 'user', $user?->id, ['email' => $credentials['email']]);

            return back()->withErrors(['email' => 'Identifiants invalides.']);
        }

        if ($user->isSuspended()) {
            $audit->log('auth.login.suspended', 'user', $user->id);

            return back()->withErrors(['email' => 'Compte suspendu.']);
        }

        if ($user->tenant?->isSuspended()) {
            $audit->log('auth.login.tenant_suspended', 'user', $user->id);

            return back()->withErrors(['email' => 'Organisation suspendue.']);
        }

        if ($user->mfa_enabled) {
            $request->session()->put('mfa_user_id', $user->id);
            $audit->log('auth.login.mfa_challenge', 'user', $user->id);

            return redirect()->route('mfa.verify');
        }

        Auth::login($user, $request->boolean('remember'));
        $user->update(['last_login_at' => now()]);
        $audit->log('auth.login.success', 'user', $user->id);

        return redirect()->intended($user->isSuperAdmin() ? route('superadmin.tenants') : app(MenuService::class)->startupRoute($user));
    }

    public function showMfaVerify()
    {
        if (! session('mfa_user_id')) {
            return redirect()->route('login');
        }

        return view('auth.mfa');
    }

    public function mfaVerify(Request $request, AuditService $audit)
    {
        $userId = $request->session()->get('mfa_user_id');
        if (! $userId) {
            return redirect()->route('login');
        }

        $user = User::withoutGlobalScopes()->find($userId);
        $code = $request->input('code') ?? '';

        if (! $this->mfa->verify($user->mfa_secret, trim($code))) {
            $audit->log('auth.login.mfa_failed', 'user', $user->id);

            return back()->withErrors(['code' => 'Code invalide ou expiré.']);
        }

        Auth::login($user);
        $request->session()->forget('mfa_user_id');
        $user->update(['last_login_at' => now()]);
        $audit->log('auth.login.success', 'user', $user->id);

        return redirect()->to($user->isSuperAdmin() ? route('superadmin.tenants') : app(MenuService::class)->startupRoute($user));
    }

    public function logout(Request $request, AuditService $audit)
    {
        $audit->log('auth.logout', 'user', auth()->id());
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
