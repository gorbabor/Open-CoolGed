<?php

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Folder;
use App\Models\MetadataValue;
use App\Models\Referential;
use App\Models\Space;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class DocumentService
{
    public const DEFAULT_ALLOWED_MIMES = [
        'application/pdf', 'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'text/plain', 'text/csv', 'application/json', 'text/html',
        'text/markdown',
        'image/jpeg', 'image/png', 'image/tiff',
    ];

    public function __construct(
        private StorageService $storage,
        private QuotaService $quota,
        private AuditService $audit,
        private PermissionService $permissions,
        private TextExtractor $extractor,
        private NotificationService $notifications,
        private DocumentLifecycleService $lifecycle,
    ) {}

    /** MIME types allowed by default policy (RM-013) — tenant can override via settings. */
    public function defaultAllowedMimes(): array
    {
        return self::DEFAULT_ALLOWED_MIMES;
    }

    /** MIME types allowed for a tenant (configurable in Administration → Documents). */
    public function allowedMimes(?User $user = null): array
    {
        $user ??= auth()->user();
        if ($user?->tenant) {
            return TenantSettings::for($user->tenant)->allowedMimes();
        }

        return $this->defaultAllowedMimes();
    }

    public function validateUpload(User $user, UploadedFile $file, ?Document $document = null): array
    {
        $tenant = $user->tenant;

        if (! $document) {
            if (! $this->permissions->can($user, 'documents.create')) {
                throw new \RuntimeException('Permission de création refusée.', 403);
            }
            if (! $this->quota->canStore($tenant, $file->getSize())) {
                throw new \RuntimeException('Quota de stockage du tenant dépassé.', 413);
            }
        } else {
            if (! $this->permissions->can($user, 'documents.edit', $document)) {
                throw new \RuntimeException('Permission de modification refusée.', 403);
            }
        }

        if (! in_array($file->getMimeType(), $this->allowedMimes($user), true)) {
            throw new \RuntimeException("Type de fichier non autorisé : {$file->getMimeType()}", 415);
        }

        $maxBytes = $tenant->max_file_size_mb * 1048576;
        if ($file->getSize() > $maxBytes) {
            throw new \RuntimeException('Taille maximale de fichier dépassée.', 413);
        }

        return ['mime' => $file->getMimeType(), 'size' => $file->getSize()];
    }

    public function create(
        User $user,
        array $data,
        ?UploadedFile $file = null,
        ?string $versionComment = null,
    ): Document {
        if ($file !== null) {
            $this->validateUpload($user, $file);
        }

        $tenant = $user->tenant;
        $space = Space::where('tenant_id', $tenant->id)->findOrFail($data['space_id']);
        $folder = ! empty($data['folder_id'])
            ? Folder::where('tenant_id', $tenant->id)->findOrFail($data['folder_id'])
            : null;

        if ($folder && $folder->space_id !== $space->id) {
            throw new \RuntimeException('Le dossier ne fait pas partie de cet espace.', 422);
        }

        $document = Document::create([
            'tenant_id' => $tenant->id,
            'space_id' => $space->id,
            'folder_id' => $folder?->id,
            'document_type_id' => $data['document_type_id'] ?? null,
            'title' => $data['title'],
            'reference' => $data['reference'] ?? null,
            'description' => $data['description'] ?? null,
            'confidentiality' => $data['confidentiality'] ?? 'internal',
            'expiration_at' => $data['expiration_at'] ?? null,
            'domain_id' => $data['domain_id'] ?? null,
            'process_id' => $data['process_id'] ?? null,
            'status' => 'draft',
            'created_by' => $user->id,
            'owner_id' => $data['owner_id'] ?? null,
        ]);

        if ($file !== null) {
            $version = $this->addVersion($user, $document, $file, $versionComment ?? 'Version initiale', '1.0');
            $document->update(['current_version_id' => $version->id]);
        }

        $this->saveMetadata($document, $data['metadata'] ?? []);
        $this->syncTags($document, $data['tags'] ?? []);
        $this->syncApplicationReferentials($document, $data['application'] ?? []);
        $this->audit->log('document.created', 'document', $document->id, ['title' => $document->title]);

        return $document->fresh();
    }

    /** Dimensions d'application multi-valeurs (poste, site, pays…) — pivot document_referential. */
    public function syncApplicationReferentials(Document $document, array $application): void
    {
        $types = ['job', 'department', 'direction', 'site', 'entity', 'country'];
        $pivots = [];

        foreach ($types as $type) {
            $id = $application[$type] ?? null;
            if (empty($id)) {
                continue;
            }
            $ref = Referential::where('tenant_id', $document->tenant_id)
                ->where('type', $type)
                ->find((int) $id);
            if ($ref) {
                $pivots[$ref->id] = ['tenant_id' => $document->tenant_id, 'type' => $type];
            }
        }

        $document->referentials()->sync($pivots);
    }

    public function addVersion(User $user, Document $document, UploadedFile $file, string $comment, ?string $baseVersion = null): DocumentVersion
    {
        if (! $this->lifecycle->contentEditable($user, $document)) {
            throw new \RuntimeException('Ce document est verrouillé (statut « '.$document->statusLabel().' »). Une nouvelle révision est requise.', 403);
        }

        $this->validateUpload($user, $file, $document);

        $tenant = $user->tenant;

        if (! $this->quota->canStore($tenant, $file->getSize())) {
            throw new \RuntimeException('Quota de stockage du tenant dépassé.', 413);
        }

        $path = $this->storage->store('documents/'.$document->tenant_id, $file);
        $checksum = hash_file('sha256', $file->getRealPath());

        $version = DocumentVersion::create([
            'tenant_id' => $document->tenant_id,
            'document_id' => $document->id,
            'version' => $baseVersion ? $this->bumpVersion($baseVersion, $comment) : $this->nextVersion($document, $comment),
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'checksum' => $checksum,
            'comment' => $comment,
            'created_by' => $user->id,
        ]);

        $version->update(['extracted_text' => $this->extractor->extract($version)]);

        $document->update(['current_version_id' => $version->id]);
        $this->audit->log('document.version.created', 'document_version', $version->id, ['version' => $version->version]);

        return $version;
    }

    /** RM-006: restoring an old version creates a new current version, never deletes history. */
    public function restoreVersion(User $user, Document $document, DocumentVersion $source): DocumentVersion
    {
        if (! $this->lifecycle->contentEditable($user, $document)) {
            throw new \RuntimeException('Ce document est verrouillé (statut « '.$document->statusLabel().' »). Une nouvelle révision est requise.', 403);
        }

        if (! $this->permissions->can($user, 'documents.edit', $document)) {
            throw new \RuntimeException('Permission de modification refusée.', 403);
        }

        $newPath = 'documents/'.$document->tenant_id.'/restored-'.now()->timestamp.'-'.$source->file_name;
        Storage::disk($this->storage->disk())->copy($source->file_path, $newPath);

        $version = DocumentVersion::create([
            'tenant_id' => $document->tenant_id,
            'document_id' => $document->id,
            'version' => $this->nextVersion($document, 'Restauration de la version '.$source->version),
            'file_path' => $newPath,
            'file_name' => $source->file_name,
            'mime_type' => $source->mime_type,
            'size' => $source->size,
            'checksum' => $source->checksum,
            'extracted_text' => $source->extracted_text,
            'comment' => 'Restauration de la version '.$source->version,
            'created_by' => $user->id,
        ]);

        $document->update(['current_version_id' => $version->id]);
        $this->audit->log('document.version.restored', 'document_version', $version->id, ['from' => $source->version, 'to' => $version->version]);

        return $version;
    }

    /** For validated AI proposals: write content as a file and create a version. */
    public function createVersionFromContent(User $user, Document $document, string $content, string $comment, ?string $fromVersion = null): DocumentVersion
    {
        $path = 'documents/'.$document->tenant_id.'/ia-'.now()->timestamp.'.txt';
        Storage::disk($this->storage->disk())->put($path, $content);

        $version = DocumentVersion::create([
            'tenant_id' => $document->tenant_id,
            'document_id' => $document->id,
            'version' => $this->nextVersion($document, $comment),
            'file_path' => $path,
            'file_name' => 'ia-proposal-'.now()->timestamp.'.txt',
            'mime_type' => 'text/plain',
            'size' => strlen($content),
            'checksum' => hash('sha256', $content),
            'extracted_text' => mb_substr($content, 0, 200000),
            'comment' => $comment,
            'created_by' => $user->id,
        ]);

        $document->update(['current_version_id' => $version->id]);
        $this->audit->log('document.version.created', 'document_version', $version->id, ['version' => $version->version, 'source' => 'ai']);

        return $version;
    }

    public function saveMetadata(Document $document, array $values): void
    {
        foreach ($values as $definitionId => $value) {
            if ($value === '' || $value === null) {
                continue;
            }

            MetadataValue::updateOrCreate(
                ['tenant_id' => $document->tenant_id, 'document_id' => $document->id, 'definition_id' => $definitionId],
                ['tenant_id' => $document->tenant_id, 'value' => is_array($value) ? json_encode($value) : $value]
            );
        }
    }

    public function syncTags(Document $document, array $tagNames): void
    {
        $ids = [];
        foreach (array_filter($tagNames) as $name) {
            $tag = Tag::firstOrCreate(
                ['tenant_id' => $document->tenant_id, 'name' => trim($name)],
                ['tenant_id' => $document->tenant_id, 'name' => trim($name)]
            );
            $ids[] = $tag->id;
        }

        $document->tags()->sync($ids);
    }

    private function nextVersion(Document $document, string $comment): string
    {
        $last = DocumentVersion::where('document_id', $document->id)
            ->orderByDesc('id')
            ->value('version');

        return $last ? $this->bumpVersion($last, $comment) : '1.0';
    }

    private function bumpVersion(string $current, string $comment): string
    {
        [$major, $minor] = array_map('intval', explode('.', $current));

        if (str_contains(mb_strtolower($comment), '[mineur]') || str_contains(mb_strtolower($comment), 'mineur')) {
            return $major.'.'.($minor + 1);
        }

        return ($major + 1).'.0';
    }
}
