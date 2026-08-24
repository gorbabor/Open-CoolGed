<?php

namespace Tests\Feature;

use App\Http\Middleware\SetTenantContext;
use App\Services\AiService;
use App\Services\Providers\GeminiProvider;
use App\Services\Providers\OpenAiCompatibleProvider;
use App\Support\TenantContext;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LlmRegistryTest extends TestCase
{
    public function test_registry_exposes_at_least_ten_providers(): void
    {
        $registry = config('llm.providers');

        $this->assertGreaterThanOrEqual(10, count($registry));
        foreach (['openai', 'anthropic', 'gemini', 'groq', 'deepseek', 'mistral', 'openrouter', 'ollama'] as $slug) {
            $this->assertArrayHasKey($slug, $registry, "fournisseur {$slug} manquant");
            $this->assertArrayHasKey('label', $registry[$slug]);
            $this->assertArrayHasKey('base_url', $registry[$slug]);
            $this->assertArrayHasKey('driver', $registry[$slug]);
            $this->assertArrayHasKey('default_model', $registry[$slug]);
        }
    }

    public function test_openai_compatible_provider_uses_configured_base_url(): void
    {
        Http::fake([
            'api.groq.com/*' => Http::response(['choices' => [['message' => ['content' => '{"summary":"Groq OK."}']]]]),
        ]);

        $provider = new OpenAiCompatibleProvider('groq', 'grok-key', 'https://api.groq.com/openai/v1', 'llama-3.3-70b');
        $tenant = $this->makeTenant();
        $user = $this->makeUser($tenant, 'manager');
        $space = $this->makeSpace($tenant);
        $doc = $this->makeDocument($tenant, $user, $space, ['content' => 'Texte.']);
        $this->actingAsUser($user);
        $this->withoutMiddleware(SetTenantContext::class);

        TenantContext::set($tenant->id);
        $result = $provider->run('summary', $doc->currentVersion()->first());

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://api.groq.com/openai/v1/chat/completions'
                && $request->hasHeader('Authorization', 'Bearer grok-key')
                && str_contains($request->body(), 'llama-3.3-70b');
        });

        $this->assertSame('groq', $provider->name());
        $this->assertArrayHasKey('content', $result);
    }

    public function test_gemini_provider_calls_generate_content(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => '{"summary":"Gemini OK."}']]]]],
            ]),
        ]);

        $provider = new GeminiProvider('gemini-key', 'gemini-2.0-flash');
        $tenant = $this->makeTenant();
        $user = $this->makeUser($tenant, 'manager');
        $space = $this->makeSpace($tenant);
        $doc = $this->makeDocument($tenant, $user, $space, ['content' => 'Texte.']);
        TenantContext::set($tenant->id);

        $result = $provider->run('summary', $doc->currentVersion()->first());

        Http::assertSent(function (Request $request) {
            return str_contains($request->url(), 'generativelanguage.googleapis.com')
                && str_contains($request->url(), 'gemini-2.0-flash')
                && str_contains($request->url(), 'key=gemini-key');
        });

        $this->assertSame('gemini', $provider->name());
        $this->assertArrayHasKey('content', $result);
    }

    public function test_ai_service_instantiates_compat_provider_from_registry(): void
    {
        Http::fake(['api.deepseek.com/*' => Http::response(['choices' => [['message' => ['content' => '{"summary":"DeepSeek OK."}']]]])]);

        $tenant = $this->makeTenant();
        $tenant->update(['settings' => array_merge($tenant->settings, [
            'ai_providers' => ['deepseek'],
            'deepseek_api_key' => Crypt::encryptString('ds-key'),
            'deepseek_model' => 'deepseek-chat',
        ])]);
        $user = $this->makeUser($tenant, 'manager');
        $space = $this->makeSpace($tenant);
        $doc = $this->makeDocument($tenant, $user, $space, ['content' => 'Texte.']);

        $this->actingAsUser($user);
        $job = app(AiService::class)->dispatch($user, $doc, 'summary');

        $this->assertSame('deepseek', $job->provider);
        $this->assertSame('succeeded', $job->status);
    }

    public function test_custom_provider_supported_via_registry(): void
    {
        Http::fake(['custom.example.com/*' => Http::response(['choices' => [['message' => ['content' => '{"summary":"Custom OK."}']]]])]);

        config(['llm.providers.custom' => [
            'label' => 'Custom LLM',
            'base_url' => 'https://custom.example.com/v1',
            'driver' => 'openai_compat',
            'default_model' => 'custom-model',
        ]]);

        $tenant = $this->makeTenant();
        $tenant->update(['settings' => array_merge($tenant->settings, [
            'ai_providers' => ['custom'],
            'custom_api_key' => Crypt::encryptString('custom-key'),
            'custom_model' => 'custom-model',
        ])]);
        $user = $this->makeUser($tenant, 'manager');
        $space = $this->makeSpace($tenant);
        $doc = $this->makeDocument($tenant, $user, $space, ['content' => 'Texte.']);

        $this->actingAsUser($user);
        $job = app(AiService::class)->dispatch($user, $doc, 'summary');

        $this->assertSame('custom', $job->provider);
        $this->assertSame('succeeded', $job->status);
    }
}
