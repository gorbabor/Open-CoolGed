<?php

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\ReadAcknowledgement;
use App\Models\Referential;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Périmètre V02 — règles de contrôle documentaire (GED opérationnelle V02 §9.3).
 * - Règles de passage à « approuvé/applicable » (fichier, version, propriétaire, dates).
 * - Obsolescence automatique de l'ancienne version (lien remplace/remplacé par).
 * - Accusés de lecture (Lot D).
 */
class V02DocumentService
{
    public const STATUSES = [
        'a_creer', 'brouillon', 'en_verification', 'en_approbation',
        'approuve_applicable', 'en_revision', 'obsolete_archive',
    ];

    public function __construct(
        private AuditService $audit,
        private NotificationService $notifications,
    ) {}

    /** Règles §9.3 — renvoie la liste des erreurs bloquantes avant approbation. */
    public function approvalBlockers(Document $document): array
    {
        $errors = [];

        $version = DocumentVersion::withoutGlobalScopes()
            ->where('document_id', $document->id)
            ->orderByDesc('id')
            ->first();

        if (! $version) {
            $errors[] = 'Un fichier doit être attaché avant approbation (règle 1).';
        }

        if (! $version?->version) {
            $errors[] = 'Une version doit être renseignée avant approbation (règle 2).';
        }

        if (! $document->owner_id) {
            $errors[] = 'Un propriétaire doit être affecté avant approbation (règle 3).';
        }

        if (! $document->effective_date) {
            $errors[] = 'Une date d\'application doit être renseignée (règle 4).';
        }

        if (! $document->next_review_date) {
            $errors[] = 'Une prochaine date de revue doit être renseignée (règle 5).';
        }

        return $errors;
    }

    /**
     * Passage en « approuvé / applicable » avec contrôle des règles.
     * Règle 6/7 : le document approuvé est verrouillé en modification pour les standards
     * (seul le workflow « en révision » le déverrouille). Règle 9 : l'ancienne version
     * applicable devient obsolète (lien remplace/remplacé par).
     */
    public function approve(Document $document, User $byUser, ?Document $previousActive = null): Document
    {
        $blockers = $this->approvalBlockers($document);
        if ($blockers !== []) {
            throw new \RuntimeException(implode(' ', $blockers));
        }

        return DB::transaction(function () use ($document, $byUser, $previousActive) {
            $previous = $previousActive ?? Document::withoutGlobalScopes()
                ->where('tenant_id', $document->tenant_id)
                ->where('id', '!=', $document->id)
                ->where('status', 'approuve_applicable')
                ->where('is_active_version', true)
                ->where('document_code', $document->document_code)
                ->first();

            $document->update([
                'status' => 'approuve_applicable',
                'is_active_version' => true,
                'archived_at' => null,
            ]);

            if ($previous) {
                $previous->update([
                    'status' => 'obsolete_archive',
                    'is_active_version' => false,
                    'archived_at' => now(),
                    'replaced_by_document_id' => $document->id,
                ]);
                $document->update(['replaces_document_id' => $previous->id]);
                $this->audit->log('v02.document.obsoleted', 'document', $previous->id, ['replaced_by' => $document->id]);
            }

            $this->audit->log('v02.document.approved', 'document', $document->id, ['by' => $byUser->id]);

            return $document->fresh();
        });
    }

    /** Règle 8 : les documents obsolètes restent consultables par les habilités. */
    public function isVisibleToStandardUser(Document $document): bool
    {
        return ! in_array($document->status, ['obsolete_archive'], true);
    }

    /** Lot D : accuser lecture (unique par version + utilisateur), tracé. */
    public function acknowledge(Document $document, User $user): ReadAcknowledgement
    {
        $version = DocumentVersion::withoutGlobalScopes()
            ->where('document_id', $document->id)
            ->orderByDesc('id')
            ->first();
        if (! $version) {
            throw new \RuntimeException('Aucune version à accuser.');
        }

        $ack = ReadAcknowledgement::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $document->tenant_id, 'document_version_id' => $version->id, 'user_id' => $user->id],
            ['document_id' => $document->id, 'acknowledged_at' => now()]
        );

        $this->audit->log('v02.read_acknowledged', 'document', $document->id, ['user' => $user->id, 'version' => $version->id]);

        return $ack;
    }

    public function hasAcknowledged(Document $document, User $user): bool
    {
        $version = DocumentVersion::withoutGlobalScopes()
            ->where('document_id', $document->id)
            ->orderByDesc('id')
            ->first();
        if (! $version) {
            return false;
        }

        return ReadAcknowledgement::withoutGlobalScopes()
            ->where('document_version_id', $version->id)
            ->where('user_id', $user->id)
            ->exists();
    }

    /** Création des référentiels V02 à partir d'un nom (avec contrôle des doublons orthographiques). */
    public static function ensureReferential(int $tenantId, string $type, string $name, ?string $code = null): Referential
    {
        $name = trim($name);

        return Referential::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('type', $type)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first()
            ?? Referential::withoutGlobalScopes()->create([
                'tenant_id' => $tenantId,
                'type' => $type,
                'name' => $name,
                'code' => $code,
            ]);
    }
}
