<?php

namespace App\Services;

use App\Models\AiJob;
use App\Models\AiResult;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\PlatformSettings;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Contracts\AiProvider;
use App\Services\Providers\AnthropicProvider;
use App\Services\Providers\GeminiProvider;
use App\Services\Providers\MockAiProvider;
use App\Services\Providers\OpenAiCompatibleProvider;
use App\Services\Providers\OpenAiProvider;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class AiService
{
    /** @var array<string, AiProvider> */
    private array $providers = [];

    public function __construct(private AuditService $audit, private PermissionService $permissions)
    {
        $this->providers['mock'] = new MockAiProvider;

        $platform = PlatformSettings::instance()->settings ?? [];

        foreach (config('llm.providers', []) as $slug => $cfg) {
            $key = $this->decryptKey($platform[$slug.'_api_key'] ?? null);
            if ($key === null) {
                continue;
            }
            $provider = $this->buildProvider($slug, $cfg, $key, $platform[$slug.'_model'] ?? $cfg['default_model']);
            if ($provider !== null) {
                $this->providers[$slug] = $provider;
            }
        }
    }

    /** Instancie le driver adéquat selon la config du registry (driver openai_compat / openai / anthropic / gemini). */
    private function buildProvider(string $slug, array $cfg, string $key, string $model): ?AiProvider
    {
        return match ($cfg['driver'] ?? 'openai_compat') {
            'openai' => new OpenAiProvider($key, $model),
            'anthropic' => new AnthropicProvider($key, $model),
            'gemini' => new GeminiProvider($key, $model),
            'openai_compat' => new OpenAiCompatibleProvider($slug, $key, $cfg['base_url'], $model),
            default => null,
        };
    }

    private function decryptKey(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Throwable) {
            return $value; // non chiffrée (saisie antérieure)
        }
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
     * Clé API résolue pour un tenant : clé du tenant > clé plateforme > null.
     * (Héritage : le tenant peut surcharger la clé de la plateforme.)
     */
    public function resolveApiKey(Tenant $tenant, string $provider): ?string
    {
        $tenantSettings = $tenant->settings ?? [];
        $tenantKey = $this->decryptKey($tenantSettings[$provider.'_api_key'] ?? null);
        if ($tenantKey !== null) {
            return $tenantKey;
        }

        $platform = PlatformSettings::instance()->settings ?? [];

        return $this->decryptKey($platform[$provider.'_api_key'] ?? null);
    }

    /** Modèle résolu : tenant > plateforme > défaut du registry. */
    public function resolveModel(Tenant $tenant, string $provider, string $default): string
    {
        $tenantSettings = $tenant->settings ?? [];
        if (! empty($tenantSettings[$provider.'_model'])) {
            return $tenantSettings[$provider.'_model'];
        }

        $platform = PlatformSettings::instance()->settings ?? [];

        return ! empty($platform[$provider.'_model']) ? $platform[$provider.'_model'] : $default;
    }

    /** Défaut du registry pour un fournisseur (modèle par défaut du catalogue). */
    public function defaultModelFor(string $provider): string
    {
        return config('llm.providers.'.$provider.'.default_model', 'gpt-4o-mini');
    }

    /**
     * Provider effectif pour un document : premier fournisseur ENREGISTRÉ (clé disponible)
     * parmi les fournisseurs autorisés du tenant ; sinon mock.
     */
    public function providerFor(Document $document): AiProvider
    {
        $allowed = $document->tenant()->first()->allowedProviders() ?: ['mock'];

        foreach ($allowed as $slug) {
            if (isset($this->providers[$slug])) {
                return $this->providers[$slug];
            }
        }

        return $this->providers['mock'];
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

        $tenant = $document->tenant()->first();
        $allowed = $tenant->allowedProviders() ?: ['mock'];

        // Fournisseur effectif : premier autorisé (hors mock) AVEC une clé résolue (tenant > plateforme), sinon mock.
        $provider = $this->providers['mock'];
        foreach ($allowed as $slug) {
            if ($slug === 'mock') {
                continue;
            }
            // Provider enregistré manuellement (ex. adaptateur custom) : utilisé tel quel.
            if (isset($this->providers[$slug]) && ! isset(config('llm.providers')[$slug])) {
                $provider = $this->providers[$slug];
                break;
            }
            $key = $this->resolveApiKey($tenant, $slug);
            if ($key === null) {
                continue;
            }
            $cfg = config('llm.providers.'.$slug);
            if ($cfg === null) {
                $provider = $this->providers[$slug] ?? $this->providers['mock'];
                break;
            }
            $provider = $this->buildProvider($slug, $cfg, $key, $this->resolveModel($tenant, $slug, $cfg['default_model']));
            break;
        }

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

    /**
     * Test de connexion à un fournisseur LLM (bouton « Tester la connexion »).
     * Utilise la clé résolue (tenant > plateforme) et fait un appel minimal.
     */
    public function testConnection(Tenant $tenant, string $provider): void
    {
        $key = $this->resolveApiKey($tenant, $provider);

        if ($key === null) {
            throw new \RuntimeException("Aucune clé API configurée pour « {$provider} » (ni tenant, ni plateforme).");
        }

        $cfg = config('llm.providers.'.$provider);
        if ($cfg === null) {
            throw new \RuntimeException("Fournisseur inconnu : {$provider}");
        }

        $providerInstance = $this->buildProvider(
            $provider,
            $cfg,
            $key,
            $this->resolveModel($tenant, $provider, $cfg['default_model']),
        );

        $dummy = new DocumentVersion;
        $dummy->extracted_text = 'ping';

        $providerInstance->run('summary', $dummy);
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
