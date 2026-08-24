<?php

namespace App\Http\Controllers;

use App\Services\AuditService;
use App\Services\MfaService;
use App\Services\TenantSettings;
use App\Themes\ThemeRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    public function __construct(private MfaService $mfa, private AuditService $audit) {}

    public function show()
    {
        $user = auth()->user();

        return view('profile', [
            'user' => $user,
            'secret' => $user->mfa_enabled ? null : $user->mfa_secret,
            'otpauth' => $user->mfa_secret ? $this->mfa->otpauthUrl($user, $user->mfa_secret) : null,
        ]);
    }

    /** Préférence d'apparence : clair / sombre / auto (persistant par utilisateur). */
    public function updateThemeMode(Request $request)
    {
        $data = $request->validate(['theme_mode' => ['required', 'in:light,dark,auto']]);

        auth()->user()->update(['theme_mode' => $data['theme_mode']]);
        $this->audit->log('profile.theme_mode_updated', 'user', auth()->id(), $data);

        return back()->with('success', 'Apparence mise à jour.');
    }

    /** Préférence de thème personnel (palette + police) : vide = thème de l'entreprise. */
    public function updateTheme(Request $request)
    {
        $data = $request->validate(['theme' => ['nullable', 'in:'.implode(',', array_keys(ThemeRegistry::THEMES))]]);

        auth()->user()->update(['theme' => $data['theme'] ?: null]);
        $this->audit->log('profile.theme_updated', 'user', auth()->id(), $data);

        return back()->with('success', 'Thème mis à jour.');
    }

    /** Changement de mot de passe : ancien requis, longueur min du tenant, confirmation. */
    public function updatePassword(Request $request)
    {
        $user = auth()->user();
        $min = (int) TenantSettings::for($user->tenant)->get('password_min_length', 8);

        $data = $request->validate([
            'current_password' => ['required'],
            'password' => ['required', "min:{$min}", 'confirmed'],
        ]);

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages(['current_password' => 'Le mot de passe actuel est incorrect.']);
        }

        $user->update(['password' => $data['password']]);
        $this->audit->log('profile.password_changed', 'user', $user->id);

        return back()->with('success', 'Mot de passe modifié.');
    }

    public function enableMfa(Request $request)
    {
        $user = auth()->user();

        if (! $user->mfa_secret) {
            $user->update(['mfa_secret' => $this->mfa->generateSecret()]);

            return back()->with('success', 'Secret généré. Scannez-le et confirmez avec un code.');
        }

        if (! $this->mfa->verify($user->mfa_secret, trim($request->input('code') ?? ''))) {
            return back()->withErrors(['code' => 'Code invalide.']);
        }

        $user->update(['mfa_enabled' => true]);
        $this->audit->log('mfa.enabled', 'user', $user->id);

        return back()->with('success', 'MFA activé.');
    }

    public function disableMfa()
    {
        auth()->user()->update(['mfa_enabled' => false, 'mfa_secret' => null]);
        $this->audit->log('mfa.disabled', 'user', auth()->id());

        return back()->with('success', 'MFA désactivé.');
    }
}
