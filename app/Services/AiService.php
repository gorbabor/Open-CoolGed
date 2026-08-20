<?php

namespace App\Services;

use App\Models\AiJob;
use App\Models\AiResult;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\User;
use App\Services\Contracts\AiProvider;
use App\Services\Providers\MockAiProvider;
use Illuminate\Support\Facades\Log;

class AiService
{
    /** @var array<string, AiProvider> */
    private array $providers = [];

    public function __construct(private AuditService $audit, private PermissionService $permissions)
    {
        $this->providers['mock'] = new MockAiProvider;
    }

    public function registerProvider(AiProvider $provider): void
    {
        $this->providers[$provider->name()] = $provider;
    }

    public function providerNames(): array
    {
        return array_keys($this->providers);
    }

    /**
     * CA-009: the provider must never be called when the user has no AI permission.
     * RM-016: no AI action if tenant, user or document disallows it.
     */
    public function assertAllowed(User $user, Document $document): void
    {
        if (! $this->permissions->can($user, 'ai.use', $document)) {
            throw new \RuntimeException('Permission IA requise sur ce document.');
        }

        if (! $document->tenant()->first()->aiEnabled()) {
            throw new \RuntimeException("L'IA est désactivée pour ce tenant.");
        }

        if ($user->tenant_id !== $document->tenant_id) {
            throw new \RuntimeException('Tenant incompatible.');
        }
    }

    /**
     * SEC-014: only the necessary content is prepared before calling the provider.
     * RM-017: the provider never writes to the source document.
     */
    public function dispatch(User $user, Document $document, string $jobType, array $params = []): AiJob
    {
        $this->assertAllowed($user, $document);

        $version = $document->currentVersion;
        if (! $version) {
            throw new \RuntimeException('Le document ne possède aucune version.');
        }

        $provider = $this->providers[$document->tenant()->first()->allowedProviders()[0] ?? 'mock'] ?? $this->providers['mock'];

        $job = AiJob::create([
            'tenant_id' => $document->tenant_id,
            'user_id' => $user->id,
            'document_id' => $document->id,
            'version_id' => $version->id,
            'job_type' => $jobType,
            'provider' => $provider->name(),
            'status' => 'pending',
            'input_summary' => mb_substr((string) $version->extracted_text, 0, 200),
        ]);

        $this->audit->log('ai.job.created', 'ai_job', $job->id, ['job_type' => $jobType, 'provider' => $provider->name()]);

        try {
            $job->update(['status' => 'running']);
            $result = $provider->run($jobType, $version, $params);
            $job->update(['status' => 'succeeded', 'output' => $result['content']]);

            AiResult::create([
                'tenant_id' => $document->tenant_id,
                'job_id' => $job->id,
                'document_id' => $document->id,
                'result_type' => $jobType,
                'content' => $result['content'],
                'confidence' => $result['confidence'],
            ]);

            $this->audit->log('ai.job.succeeded', 'ai_job', $job->id);
        } catch (\Throwable $e) {
            $job->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 500)]);
            Log::warning('AI job failed', ['job' => $job->id, 'error' => $e->getMessage()]);

            return $job->fresh();
        }

        return $job->fresh();
    }

    /** Human validation (RM-017 / CA-010): applying a result creates a new version, never overwrites. */
    public function applyResult(User $user, AiResult $result, string $newContent, ?string $comment = null): DocumentVersion
    {
        $document = $result->document;

        $this->audit->log('ai.result.validated', 'ai_result', $result->id, ['document' => $document->id]);

        return app(DocumentService::class)->createVersionFromContent(
            $user,
            $document,
            $newContent,
            $comment ?? 'Version issue d\'une proposition IA validée',
            $result->job?->version?->version
        );
    }

    public function validateResult(User $user, AiResult $result, bool $accepted): void
    {
        if ($accepted) {
            $result->update(['validated_by' => $user->id, 'validated_at' => now()]);
        }

        $this->audit->log($accepted ? 'ai.result.accepted' : 'ai.result.rejected', 'ai_result', $result->id);
    }
}
