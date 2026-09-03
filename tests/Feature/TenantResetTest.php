<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\MetadataDefinition;
use App\Models\Referential;
use App\Models\Space;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Workflow;
use App\Services\BackupService;
use App\Services\PersonalSpaceService;
use App\Services\TenantResetService;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TenantResetTest extends TestCase
{
    private function seedTenantContent(Tenant $tenant, User $user, Space $space): array
    {
        $doc = $this->makeDocument($tenant, $user, $space, ['title' => 'Doc à purger']);
        $def = MetadataDefinition::create(['tenant_id' => $tenant->id, 'name' => 'Client', 'key' => 'client', 'type' => 'text']);
        $doc->metadataValues()->create(['tenant_id' => $tenant->id, 'definition_id' => $def->id, 'value' => 'Acme']);
        $site = Referential::create(['tenant_id' => $tenant->id, 'type' => 'site', 'name' => 'Siège']);
        $doc->referentials()->attach($site->id, ['tenant_id' => $tenant->id, 'type' => 'site']);
        $wf = Workflow::create(['tenant_id' => $tenant->id, 'name' => 'WF Test', 'slug' => 'wf-test', 'is_active' => true]);

        return compact('doc', 'def', 'site', 'wf');
    }

    public function test_reset_purges_content_but_keeps_tenant_users_roles_settings(): void
    {
        $tenant = $this->makeTenant();
        $user = $this->makeUser($tenant, 'user');
        $shared = $this->makeSpace($tenant, 'Partagé');
        $personal = app(PersonalSpaceService::class)->ensure($user);
        $this->seedTenantContent($tenant, $user, $shared);

        $other = $this->makeTenant();
        $otherSpace = $this->makeSpace($other);
        $otherUser = $this->makeUser($other, 'user');
        $this->makeDocument($other, $otherUser, $otherSpace, ['title' => 'Doc autre tenant']);

        app(TenantResetService::class)->reset($tenant);

        $this->assertSame(0, Document::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count(), 'documents purgés');
        $this->assertSame(0, Space::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('is_personal', false)->count(), 'espaces partagés purgés');
        $this->assertSame(1, Space::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('is_personal', true)->count(), 'espace personnel recréé');
        $this->assertSame(0, MetadataDefinition::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());
        $this->assertSame(0, Referential::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());
        $this->assertSame(0, Workflow::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());
        $this->assertSame(0, \DB::table('metadata_values')->where('tenant_id', $tenant->id)->count());
        $this->assertSame(0, \DB::table('document_referential')->where('tenant_id', $tenant->id)->count());
        $this->assertSame(0, \DB::table('document_versions')->where('tenant_id', $tenant->id)->count());

        $this->assertNotNull(Tenant::withoutGlobalScopes()->find($tenant->id), 'tenant conservé');
        $this->assertNotNull(User::withoutGlobalScopes()->find($user->id), 'utilisateur conservé');
        $this->assertSame(1, \DB::table('user_role')->where('user_id', $user->id)->count(), 'rôles conservés');
        $this->assertSame(1, Document::withoutGlobalScopes()->where('tenant_id', $other->id)->count(), 'tenant voisin intact');
    }

    public function test_reset_deletes_storage_files(): void
    {
        Storage::fake('local');
        $tenant = $this->makeTenant();
        $user = $this->makeUser($tenant, 'user');
        $space = $this->makeSpace($tenant);
        $doc = $this->makeDocument($tenant, $user, $space);
        $this->actingAsUser($user);
        $path = $doc->currentVersion->file_path;
        $this->assertNotNull($path);
        Storage::disk('local')->assertExists($path);

        app(TenantResetService::class)->reset($tenant);

        Storage::disk('local')->assertMissing($path);
    }

    public function test_cli_dry_run_does_nothing_and_confirmation_required(): void
    {
        $tenant = $this->makeTenant();
        $user = $this->makeUser($tenant, 'user');
        $space = $this->makeSpace($tenant);
        $this->makeDocument($tenant, $user, $space);

        // Dry-run : rien n'est supprimé.
        $this->artisan('tenant:reset', ['tenant' => $tenant->id, '--dry-run' => true])
            ->expectsOutputToContain('Dry-run')
            ->assertExitCode(0);
        $this->assertSame(1, Document::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());

        // Sans --force ni confirmation → annulé.
        $this->artisan('tenant:reset', ['tenant' => $tenant->id])
            ->expectsConfirmation('Confirmer le reset complet de ce tenant ? (irréversible sans sauvegarde)', 'no')
            ->expectsOutputToContain('Reset annulé')
            ->assertExitCode(0);
        $this->assertSame(1, Document::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());
    }

    public function test_cli_reset_runs_backup_before_purging(): void
    {
        $tenant = $this->makeTenant();
        $user = $this->makeUser($tenant, 'user');
        $space = $this->makeSpace($tenant);
        $this->makeDocument($tenant, $user, $space);

        $backup = \Mockery::mock(BackupService::class);
        $backup->shouldReceive('run')->once()->andReturn(['dir' => 'backups/test', 'sql' => true, 'json' => true, 'pruned' => 0]);
        $this->app->instance(BackupService::class, $backup);

        $this->artisan('tenant:reset', ['tenant' => $tenant->id, '--force' => true])
            ->expectsOutputToContain('Sauvegarde créée')
            ->expectsOutputToContain('Reset terminé')
            ->assertExitCode(0);
        $this->assertSame(0, Document::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());
    }

    public function test_superadmin_can_reset_tenant_via_ui_with_slug_confirmation(): void
    {
        $tenant = $this->makeTenant();
        $user = $this->makeUser($tenant, 'user');
        $space = $this->makeSpace($tenant);
        $this->makeDocument($tenant, $user, $space);

        $sa = $this->makeUser($tenant, 'user', ['is_super_admin' => true]);
        $this->actingAsUser($sa);

        $backup = \Mockery::mock(BackupService::class);
        $backup->shouldReceive('run')->once()->andReturn(['dir' => 'backups/ui', 'sql' => true, 'json' => true, 'pruned' => 0]);
        $this->app->instance(BackupService::class, $backup);

        // Mauvais slug → erreur, rien supprimé.
        $this->post(route('superadmin.tenants.reset', $tenant), ['confirm' => 'mauvais-slug'])->assertSessionHasErrors('confirm');
        $this->assertSame(1, Document::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());

        // Bon slug → purge + audit superadmin.
        $this->post(route('superadmin.tenants.reset', $tenant), ['confirm' => $tenant->slug])->assertRedirect();
        $this->assertSame(0, Document::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'superadmin.tenant.reset']);
    }

    public function test_tenant_admin_can_clear_content_via_settings_ui(): void
    {
        $tenant = $this->makeTenant();
        $user = $this->makeUser($tenant, 'user');
        $space = $this->makeSpace($tenant);
        $this->makeDocument($tenant, $user, $space);

        $admin = $this->makeUser($tenant, 'tenant_admin');
        $this->actingAsUser($admin);

        $backup = \Mockery::mock(BackupService::class);
        $backup->shouldReceive('run')->once()->andReturn(['dir' => 'backups/ui2', 'sql' => true, 'json' => true, 'pruned' => 0]);
        $this->app->instance(BackupService::class, $backup);

        $this->post(route('admin.settings.reset-content'), ['confirm' => $tenant->slug])->assertRedirect();
        $this->assertSame(0, Document::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'tenant.content_reset']);

        // L'admin tenant ne peut pas reset un autre tenant (pas de route dédiée — périmètre garanti par la validation).
        $this->assertNotNull(User::withoutGlobalScopes()->find($user->id), 'utilisateurs conservés');
    }
}
