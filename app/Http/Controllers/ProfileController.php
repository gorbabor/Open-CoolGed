<?php

namespace App\Http\Controllers;

use App\Services\AuditService;
use App\Services\MfaService;
use Illuminate\Http\Request;

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
