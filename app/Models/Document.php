<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'space_id', 'folder_id', 'document_type_id', 'title', 'reference',
        'document_code', 'domain_id', 'process_id', 'owner_id', 'reviewer_id', 'approver_id',
        'criticality', 'review_frequency', 'next_review_date', 'effective_date',
        'replaces_document_id', 'replaced_by_document_id', 'is_active_version', 'read_ack_required',
        'description', 'status', 'confidentiality', 'expiration_at',
        'current_version_id', 'created_by', 'archived_at',
    ];

    protected $casts = [
        'expiration_at' => 'date',
        'effective_date' => 'date',
        'next_review_date' => 'date',
        'archived_at' => 'datetime',
        'is_active_version' => 'boolean',
        'read_ack_required' => 'boolean',
    ];

    /** Libellés harmonisés (cycle de vie Kaeged + statuts V02). */
    public const STATUS_LABELS = [
        'draft' => 'Brouillon',
        'in_review' => 'En revue',
        'approved' => 'Approuvé',
        'archived' => 'Archivé',
        'expired' => 'Expiré',
        'a_creer' => 'À créer',
        'brouillon' => 'Brouillon',
        'en_verification' => 'En vérification',
        'en_approbation' => 'En approbation',
        'approuve_applicable' => 'Approuvé — applicable',
        'en_revision' => 'En révision',
        'obsolete_archive' => 'Obsolète / archivé',
    ];

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function domain()
    {
        return $this->belongsTo(Referential::class, 'domain_id');
    }

    public function process()
    {
        return $this->belongsTo(Referential::class, 'process_id');
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    /** Dimensions d'application multi-valeurs (postes, sites, entités, pays…). */
    public function referentials()
    {
        return $this->belongsToMany(Referential::class, 'document_referential')
            ->withPivot('tenant_id', 'type');
    }

    public function replaces()
    {
        return $this->belongsTo(Document::class, 'replaces_document_id');
    }

    public function replacedBy()
    {
        return $this->belongsTo(Document::class, 'replaced_by_document_id');
    }

    public function readAcknowledgements()
    {
        return $this->hasMany(ReadAcknowledgement::class);
    }

    public function space()
    {
        return $this->belongsTo(Space::class);
    }

    public function folder()
    {
        return $this->belongsTo(Folder::class);
    }

    public function type()
    {
        return $this->belongsTo(DocumentType::class, 'document_type_id');
    }

    public function versions()
    {
        return $this->hasMany(DocumentVersion::class)->orderByDesc('id');
    }

    public function currentVersion()
    {
        return $this->belongsTo(DocumentVersion::class, 'current_version_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class)->withPivot('tenant_id');
    }

    public function metadataValues()
    {
        return $this->hasMany(MetadataValue::class);
    }

    public function comments()
    {
        return $this->hasMany(Comment::class)->orderByDesc('id');
    }

    public function shares()
    {
        return $this->hasMany(Share::class);
    }

    public function workflowInstances()
    {
        return $this->hasMany(WorkflowInstance::class);
    }

    public function aiJobs()
    {
        return $this->hasMany(AiJob::class);
    }

    public function isTrashed(): bool
    {
        return $this->trashed();
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null || $this->status === 'archived';
    }

    public function isLocked(): bool
    {
        return $this->currentVersion?->lock_token !== null;
    }
}
