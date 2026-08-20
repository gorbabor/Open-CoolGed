<?php

namespace App\Http\Controllers;

use App\Models\Share;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

/**
 * External share access (Administration → Partage, option "liens externes").
 * Security (RM-012): no permanent public URL — access goes through a random
 * 256-bit token stored hashed, optional password, mandatory expiry, revocable,
 * and every hit is audited.
 */
class SharePublicController extends Controller
{
    public function __construct(private AuditService $audit) {}

    public function show(string $token)
    {
        $share = Share::withoutGlobalScopes()
            ->where('token', Share::hashToken($token))
            ->first();

        if (! $share || ! $share->isActive() || ! $share->is_external) {
            abort(404, 'Lien invalide ou expiré.');
        }

        $this->audit->log('share.external.viewed', 'share', $share->id, ['document' => $share->document_id]);

        return view('share.external', [
            'share' => $share,
            'token' => $token,
            'locked' => $share->password_hash !== null && ! session('share_unlocked_'.$share->id),
            'document' => $share->document,
            'version' => $share->document?->currentVersion,
        ]);
    }

    public function unlock(Request $request, string $token)
    {
        $share = Share::withoutGlobalScopes()
            ->where('token', Share::hashToken($token))
            ->first();

        if (! $share || ! $share->isActive() || ! $share->is_external) {
            abort(404, 'Lien invalide ou expiré.');
        }

        if ($share->password_hash === null) {
            return redirect()->route('share.external.show', $token);
        }

        $request->validate(['password' => ['required']]);

        if (! Hash::check($request->input('password'), $share->password_hash)) {
            $this->audit->log('share.external.password_failed', 'share', $share->id);

            return back()->withErrors(['password' => 'Mot de passe incorrect.']);
        }

        session(['share_unlocked_'.$share->id => true]);
        $this->audit->log('share.external.unlocked', 'share', $share->id);

        return redirect()->route('share.external.show', $token);
    }

    /** Stream the current version (octet-stream: viewers/download managers both work). */
    public function download(string $token)
    {
        $share = Share::withoutGlobalScopes()
            ->where('token', Share::hashToken($token))
            ->first();

        if (! $share || ! $share->isActive() || ! $share->is_external) {
            abort(404, 'Lien invalide ou expiré.');
        }

        if ($share->password_hash !== null && ! session('share_unlocked_'.$share->id)) {
            abort(403, 'Mot de passe requis.');
        }

        $version = $share->document?->currentVersion;
        if (! $version) {
            abort(404, 'Document indisponible.');
        }

        $this->audit->log('share.external.downloaded', 'share', $share->id, ['document' => $share->document_id]);

        $path = Storage::disk(config('ged.storage_disk'))->path($version->file_path);

        return response()->stream(function () use ($path) {
            readfile($path);
        }, 200, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="'.rawurlencode($version->file_name).'"',
        ]);
    }
}
