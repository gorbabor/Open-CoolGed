<?php

namespace App\Services;

use App\Models\Document;
use App\Models\User;

/**
 * Cycle de vie documentaire — gel par statut (pratique GED classique).
 * Un document approuvé, archivé, expiré ou obsolète est verrouillé :
 * plus de modification des métadonnées ni du contenu — une nouvelle
 * révision (nouveau cycle de validation) est requise.
 */
class DocumentLifecycleService
{
    /** Statuts où le document est gelé (métadonnées + contenu). */
    public const FROZEN_STATUSES = [
        'approved', 'archived', 'expired',
        'approuve_applicable', 'obsolete_archive',
    ];

    public function __construct(
        private PermissionService $permissions,
    ) {}

    public function isFrozen(Document $document): bool
    {
        return in_array($document->status, self::FROZEN_STATUSES, true);
    }

    /** Le gel prévaut toujours : un document gelé n'est modifiable par personne. */
    public function metadataEditable(User $user, Document $document): bool
    {
        if ($this->isFrozen($document)) {
            return false;
        }

        return $this->permissions->can($user, 'documents.metadata', $document);
    }

    public function contentEditable(User $user, Document $document): bool
    {
        if ($this->isFrozen($document)) {
            return false;
        }

        return $this->permissions->can($user, 'documents.edit', $document);
    }
}
