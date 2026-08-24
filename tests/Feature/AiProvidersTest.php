<?php

namespace Tests\Feature;

use App\Models\PlatformSettings;
use App\Models\Tenant;
use App\Services\AiService;
use App\Services\Providers\AnthropicProvider;
use App\Services\Providers\OpenAiProvider;
use App\Support\TenantContext;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiProvidersTest extends TestCase
{
    private function makeVersionedDoc(Tenant $tenant, array $settings = []): array
    {
        $tenant->update(['settings' => array_merge($tenant->settings, $settings)]);
        $user = $this->makeUser($tenant, 'manager');
        $space = $this->makeSpace($tenant);
        $doc = $this->makeDocument($tenant, $user, $space, ['content' => 'Contenu de test pour le LLM.']);

        return [$tenant, $user, $doc];
    }

    public function test_openai_provider_calls_chat_completions_with_bearer(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [['message' => ['content' => '{"summary":"Résumé du document."}']]],
            ]),
        ]);

        $provider = new OpenAiProvider('sk-test-123', 'gpt-4o-mini');
        $tenant = $this->makeTenant();
        [, , $doc] = $this->makeVersionedDoc($tenant);

        TenantContext::set($tenant->id);
        $result = $provider->run('summary', $doc->currentVersion()->first(), ['length' => 200]);

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://api.openai.com/v1/chat/completions'
                && $request->hasHeader('Authorization', 'Bearer sk-test-123')
                && str_contains($request->body(), 'gpt-4o-mini');
        });

        $this->assertArrayHasKey('content', $result);
        $this->assertArrayHasKey('confidence', $result);
    }

    public function test_anthropic_provider_calls_messages_with_x_api_key(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => '{"summary":"Résumé Anthropic."}']],
            ]),
        ]);

        $provider = new AnthropicProvider('sk-ant-test', 'claude-3-5-haiku');
        $tenant = $this->makeTenant();
        [, , $doc] = $this->makeVersionedDoc($tenant);

        TenantContext::set($tenant->id);
        $result = $provider->run('summary', $doc->currentVersion()->first());

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://api.anthropic.com/v1/messages'
                && $request->hasHeader('x-api-key', 'sk-ant-test')
                && $request->hasHeader('anthropic-version', '2023-06-01')
                && str_contains($request->body(), 'claude-3-5-haiku');
        });

        $this->assertArrayHasKey('content', $result);
    }

    public function test_provider_failure_marks_job_failed(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response('Server error', 500),
        ]);

        $tenant = $this->makeTenant();
        $tenant->update([
            'settings' => array_merge($tenant->settings, [
                'ai_providers' => ['openai'],
                'openai_api_key' => Crypt::encryptString('sk-test-123'),
                'openai_model' => 'gpt-4o-mini',
            ]),
        ]);
        $user = $this->makeUser($tenant, 'manager');
        $space = $this->makeSpace($tenant);
        $doc = $this->makeDocument($tenant, $user, $space, ['content' => 'Contenu.']);

        $this->actingAsUser($user);
        $job = app(AiService::class)->dispatch($user, $doc, 'summary');

        $this->assertSame('failed', $job->status);
        $this->assertNotEmpty($job->error);
    }

    public function test_provider_selection_prefers_registered_allowed_provider(): void
    {
        Http::fake(['api.openai.com/*' => Http::response(['choices' => [['message' => ['content' => '{"summary":"OK."}']]]])]);

        $tenant = $this->makeTenant();
        $tenant->update([
            'settings' => array_merge($tenant->settings, [
                'ai_providers' => ['mock', 'openai'],
                'openai_api_key' => Crypt::encryptString('sk-test-123'),
                'openai_model' => 'gpt-4o-mini',
            ]),
        ]);
        $user = $this->makeUser($tenant, 'manager');
        $space = $this->makeSpace($tenant);
        $doc = $this->makeDocument($tenant, $user, $space, ['content' => 'Contenu.']);

        $this->actingAsUser($user);
        $job = app(AiService::class)->dispatch($user, $doc, 'summary');

        $this->assertSame('openai', $job->provider);
        $this->assertSame('succeeded', $job->status);
    }

    public function test_provider_selection_falls_back_to_mock_without_keys(): void
    {
        $tenant = $this->makeTenant();
        $tenant->update([
            'settings' => array_merge($tenant->settings, ['ai_providers' => ['openai', 'anthropic']]),
        ]);
        $user = $this->makeUser($tenant, 'manager');
        $space = $this->makeSpace($tenant);
        $doc = $this->makeDocument($tenant, $user, $space, ['content' => 'Contenu.']);

        $this->actingAsUser($user);
        $job = app(AiService::class)->dispatch($user, $doc, 'summary');

        $this->assertSame('mock', $job->provider);
    }

    public function test_tenant_key_inherits_from_platform(): void
    {
        PlatformSettings::instance()->set([
            'openai_api_key' => Crypt::encryptString('sk-platform'),
            'openai_model' => 'gpt-4o-mini',
        ]);

        $tenant = $this->makeTenant(); // sans clé propre

        $this->assertSame('sk-platform', app(AiService::class)->resolveApiKey($tenant, 'openai'));
    }

    public function test_tenant_key_overrides_platform(): void
    {
        PlatformSettings::instance()->set([
            'openai_api_key' => Crypt::encryptString('sk-platform'),
            'openai_model' => 'gpt-4o-mini',
        ]);

        $tenant = $this->makeTenant();
        $tenant->update(['settings' => array_merge($tenant->settings, [
            'openai_api_key' => Crypt::encryptString('sk-tenant'),
        ])]);

        $this->assertSame('sk-tenant', app(AiService::class)->resolveApiKey($tenant->fresh(), 'openai'));
    }

    public function test_platform_settings_save_encrypts_ai_keys(): void
    {
        $user = $this->makeUser($this->makeTenant(), 'tenant_admin');
        $user->update(['is_super_admin' => true]);
        $this->actingAs($user);

        $this->post(route('superadmin.settings.update'), [
            'openai_api_key' => 'sk-super-secret',
            'openai_model' => 'gpt-4o-mini',
        ])->assertRedirect();

        $settings = PlatformSettings::instance()->fresh()->settings;
        $this->assertNotSame('sk-super-secret', $settings['openai_api_key']);
        $this->assertSame('sk-super-secret', Crypt::decryptString($settings['openai_api_key']));
    }

    public function test_tenant_settings_save_encrypts_ai_keys(): void
    {
        $tenant = $this->makeTenant();
        $user = $this->makeUser($tenant, 'tenant_admin');
        $this->actingAsUser($user);

        $this->post(route('admin.settings.update'), [
            'openai_api_key' => 'sk-tenant-secret',
            'openai_model' => 'gpt-4o-mini',
        ])->assertRedirect();

        $tenant->refresh();
        $this->assertSame('sk-tenant-secret', Crypt::decryptString($tenant->settings['openai_api_key']));
    }

    public function test_test_ai_route_reports_connection_failure(): void
    {
        Http::fake(['api.openai.com/*' => Http::response('Unauthorized', 401)]);

        $tenant = $this->makeTenant();
        $tenant->update(['settings' => array_merge($tenant->settings, [
            'openai_api_key' => Crypt::encryptString('sk-invalide'),
        ])]);
        $user = $this->makeUser($tenant, 'tenant_admin');
        $this->actingAsUser($user);

        $this->post(route('admin.settings.test-ai'), ['provider' => 'openai'])
            ->assertRedirect()
            ->assertSessionHasErrors('ai');
    }
}
