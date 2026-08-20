<?php

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\OfficeSession;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class OfficeService
{
    public function __construct(
        private AuditService $audit,
        private PermissionService $permissions,
        private StorageService $storage,
    ) {}

    /**
     * Start an online editing session: the document is locked (check-out)
     * and a short-lived token grants a scoped download (RM-012 compliant).
     */
    public function startEdit(User $user, Document $document): OfficeSession
    {
        if (! $this->permissions->can($user, 'documents.edit', $document)) {
            throw new \RuntimeException('Permission de modification refusée.', 403);
        }

        $version = $document->currentVersion;
        if (! $version) {
            throw new \RuntimeException('Aucune version à éditer.');
        }

        // Tenant policy: automatic check-out lock can be disabled
        // (Administration → Documents → verrouillage auto).
        $autoLock = TenantSettings::for($document->tenant)->get('auto_lock_on_edit', true);
        $token = $autoLock ? Str::random(64) : null;
        if ($autoLock && $version->lock_token !== null) {
            throw new \RuntimeException('Le document est verrouillé par une autre session d\'édition.');
        }

        if ($autoLock) {
            $version->update(['lock_token' => $token]);
        }

        $session = OfficeSession::create([
            'tenant_id' => $document->tenant_id,
            'document_id' => $document->id,
            'version_id' => $version->id,
            'user_id' => $user->id,
            'token' => $token ? hash('sha256', $token) : Str::random(64),
            'expires_at' => now()->addMinutes(60),
        ]);

        $session->raw_token = $token ?? $session->token;

        $this->audit->log('office.session.started', 'office_session', $session->id, ['document' => $document->id, 'auto_lock' => $autoLock]);

        return $session;
    }

    /** Token-scoped download: identity + integrity checked server-side. */
    public function downloadViaToken(string $token): OfficeSession
    {
        $session = OfficeSession::where('token', hash('sha256', $token))->first();

        if (! $session || ! $session->isValid()) {
            abort(404, 'Session d\'édition invalide ou expirée.');
        }

        $this->audit->log('office.download', 'office_session', $session->id);

        return $session;
    }

    /**
     * Return of the edited file: token verified, then a NEW version is created
     * (RM-005: never overwrite history). The lock is released.
     */
    public function returnFile(User $user, string $token, $file): DocumentVersion
    {
        $session = OfficeSession::where('token', hash('sha256', $token))->first();

        if (! $session || ! $session->isValid()) {
            throw new \RuntimeException('Session d\'édition invalide ou expirée.', 404);
        }

        if ($session->user_id !== $user->id && ! $user->isSuperAdmin()) {
            throw new \RuntimeException('Session d\'édition liée à un autre utilisateur.', 403);
        }

        $document = Document::withoutGlobalScopes()->findOrFail($session->document_id);
        $version = app(DocumentService::class)->addVersion(
            $user,
            $document,
            $file,
            'Retour d\'édition Office (nouvelle version)',
            $session->version?->version
        );

        // Release the lock on the checked-out version (the one that was locked).
        $session->version?->update(['lock_token' => null]);
        $session->update(['returned_at' => now()]);
        $this->audit->log('office.session.returned', 'office_session', $session->id, ['new_version' => $version->id]);

        return $version;
    }

    /**
     * Save content edited in the embedded browser editor (texte/markdown/…).
     * Same guarantees as returnFile: session token, new version, lock released,
     * history never overwritten (RM-005). The file is written to a temporary
     * file so the standard upload pipeline (validation, checksum, extraction)
     * is reused unchanged.
     */
    public function saveEmbedded(User $user, string $token, string $content): DocumentVersion
    {
        $session = OfficeSession::where('token', hash('sha256', $token))->first();

        if (! $session || ! $session->isValid()) {
            throw new \RuntimeException('Session d\'édition invalide ou expirée.', 404);
        }

        if ($session->user_id !== $user->id && ! $user->isSuperAdmin()) {
            throw new \RuntimeException('Session d\'édition liée à un autre utilisateur.', 403);
        }

        $version = $session->version;
        if (! $version) {
            throw new \RuntimeException('Version d\'édition introuvable.', 404);
        }

        $tmp = tempnam(sys_get_temp_dir(), 'kaeged-edit');
        if ($tmp === false) {
            throw new \RuntimeException('Impossible de créer un fichier temporaire.', 500);
        }
        file_put_contents($tmp, $content);

        try {
            $file = new UploadedFile($tmp, $version->file_name, $version->mime_type, null, true);

            $document = Document::withoutGlobalScopes()->findOrFail($session->document_id);
            $newVersion = app(DocumentService::class)->addVersion(
                $user,
                $document,
                $file,
                'Édition en ligne ('.$version->mime_type.')',
                $version->version
            );

            // Release the lock on the checked-out version.
            $version->update(['lock_token' => null]);
            $session->update(['returned_at' => now()]);
            $this->audit->log('office.session.returned', 'office_session', $session->id, [
                'new_version' => $newVersion->id,
                'mode' => 'embedded',
            ]);

            return $newVersion;
        } finally {
            @unlink($tmp);
        }
    }

    /** Fallback (CA-019): plain download; the re-import creates a new version. */
    public function fallbackDownloadPath(DocumentVersion $version): string
    {
        return $this->storage->path($version->file_path);
    }
}
