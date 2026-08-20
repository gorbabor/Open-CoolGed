<?php

namespace Tests\Feature;

use App\Models\AiResult;
use App\Models\DocumentVersion;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RolePermission;
use App\Services\AiService;
use App\Services\Contracts\AiProvider;
use Tests\TestCase;

class AiAndOcrTest extends TestCase
{
    public function test_ai_action_without_permission_is_refused_before_any_call(): void
    {
        $tenant = $this->makeTenant();
        $space = $this->makeSpace($tenant);
        $user = $this->makeUser($tenant, 'user'); // 'user' role has no ai.use
        $doc = $this->makeDocument($tenant, $user, $space);

        $this->actingAsUser($user);

        $response = $this->post(route('ai.dispatch', $doc), ['job_type' => 'summary']);
        $response->assertSessionHasErrors('ai');

        // CA-009: no job was created, therefore no provider call happened.
        $this->assertDatabaseMissing('ai_jobs', ['document_id' => $doc->id]);
    }

    public function test_ai_suggestion_never_modifies_source_document(): void
    {
        $tenant = $this->makeTenant();
        $space = $this->makeSpace($tenant);
        $user = $this->makeUser($tenant, 'manager');
        $doc = $this->makeDocument($tenant, $user, $space, ['content' => 'Contrat de maintenance informatique 2026. Montant 24000 EUR.']);

        $this->actingAsUser($user);
        $this->post(route('ai.dispatch', $doc), ['job_type' => 'summary'])->assertRedirect();

        $doc->refresh();

        // CA-010: the source file and version count are untouched.
        $this->assertSame(1, $doc->versions()->count());
        $this->assertSame('1.0', $doc->currentVersion->version);
        $this->assertDatabaseHas('ai_jobs', ['document_id' => $doc->id, 'status' => 'succeeded']);
        $this->assertDatabaseHas('ai_results', ['document_id' => $doc->id, 'result_type' => 'summary']);
    }

    public function test_provider_failure_never_loses_the_document(): void
    {
        $tenant = $this->makeTenant(['settings' => ['ai_enabled' => true, 'ai_providers' => ['failing']]]);
        $space = $this->makeSpace($tenant);
        $user = $this->makeUser($tenant, 'manager');
        $doc = $this->makeDocument($tenant, $user, $space);

        $failing = new class implements AiProvider
        {
            public function name(): string
            {
                return 'failing';
            }

            public function run(string $jobType, DocumentVersion $version, array $params = []): array
            {
                throw new \RuntimeException('fournisseur injoignable');
            }
        };

        app(AiService::class)->registerProvider($failing);

        $this->actingAsUser($user);
        $this->post(route('ai.dispatch', $doc), ['job_type' => 'summary'])->assertRedirect();

        $doc->refresh();

        // CA-016: job failed, source document intact.
        $this->assertDatabaseHas('ai_jobs', ['document_id' => $doc->id, 'status' => 'failed']);
        $this->assertSame(1, $doc->versions()->count());
        $this->assertFileExists(storage_path('app/private/'.$doc->currentVersion->file_path));
    }

    public function test_ocr_extracts_text_separately_from_source(): void
    {
        $tenant = $this->makeTenant();
        $space = $this->makeSpace($tenant);
        $user = $this->makeUser($tenant, 'manager');
        $doc = $this->makeDocument($tenant, $user, $space, ['content' => 'Scan de facture n° F-2026-42.']);

        $this->actingAsUser($user);
        $this->post(route('ai.ocr', $doc))->assertRedirect();

        $doc->refresh();
        $this->assertStringContainsString('F-2026-42', (string) $doc->currentVersion->extracted_text);
        $this->assertSame(1, $doc->versions()->count());
    }

    public function test_ai_result_needs_human_validation_before_any_change(): void
    {
        $tenant = $this->makeTenant();
        $space = $this->makeSpace($tenant);
        $user = $this->makeUser($tenant, 'manager');
        $doc = $this->makeDocument($tenant, $user, $space, ['content' => 'Contenu initial du document.']);

        $this->actingAsUser($user);
        $this->post(route('ai.dispatch', $doc), ['job_type' => 'correction'])->assertRedirect();

        $doc->refresh();
        $this->assertSame(1, $doc->versions()->count());

        // Validating + applying creates a NEW version (RM-017, RM-005).
        $result = AiResult::where('document_id', $doc->id)->first();
        $this->post(route('ai.results.apply', $result), ['content' => 'Contenu corrigé par validation humaine.'])->assertRedirect();

        $doc->refresh();
        $this->assertSame(2, $doc->versions()->count());
        $this->assertSame('2.0', $doc->currentVersion->version);
        $this->assertDatabaseHas('audit_logs', ['action' => 'ai.result.validated']);
    }

    public function test_permission_change_is_audited(): void
    {
        $tenant = $this->makeTenant();
        $admin = $this->makeUser($tenant, 'tenant_admin');
        $role = Role::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('slug', 'user')->first();
        $perm = Permission::where('slug', 'documents.download')->first();

        $this->actingAsUser($admin);

        $this->post(route('admin.roles.update', $role), [
            'permissions' => [$perm->slug => ['scope_type' => 'tenant', 'scope_id' => '', 'deny' => '1']],
        ])->assertRedirect();

        // CA-014: actor and timestamp are recorded.
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'admin.role.permissions.updated',
            'user_id' => $admin->id,
            'resource_type' => 'role',
            'resource_id' => $role->id,
        ]);

        $deny = RolePermission::where('role_id', $role->id)->where('permission_id', $perm->id)->where('denied', true)->first();
        $this->assertNotNull($deny);
    }
}
