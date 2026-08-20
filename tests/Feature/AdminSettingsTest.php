<?php

namespace Tests\Feature;

use App\Models\DocumentType;
use App\Models\PlatformSettings;
use App\Models\Role;
use App\Models\Share;
use App\Models\Tenant;
use App\Services\AuditService;
use App\Services\OfficeService;
use App\Services\TenantSettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class AdminSettingsTest extends TestCase
{
    public function test_mime_policy_change_is_applied_immediately(): void
    {
        $tenant = $this->makeTenant();
        $space = $this->makeSpace($tenant);
        $user = $this->makeUser($tenant, 'user');
        $this->actingAsUser($user);

        // Restrict the tenant policy to PDF only.
        TenantSettings::for($tenant)->set(['allowed_mimes' => ['application/pdf']]);

        $this->post(route('documents.store'), [
            'title' => 'TXT interdit',
            'space_id' => $space->id,
            'file' => UploadedFile::fake()->createWithContent('doc.txt', 'contenu', 'text/plain'),
        ])->assertSessionHasErrors('file');

        $this->assertDatabaseMissing('documents', ['title' => 'TXT interdit']);
    }

    public function test_auto_lock_can_be_disabled(): void
    {
        $tenant = $this->makeTenant();
        $space = $this->makeSpace($tenant);
        $user = $this->makeUser($tenant, 'user');
        $doc = $this->makeDocument($tenant, $user, $space);

        TenantSettings::for($tenant)->set(['auto_lock_on_edit' => false]);

        $this->actingAsUser($user);
        $session = app(OfficeService::class)->startEdit($user, $doc);

        $doc->refresh();
        $this->assertNull($doc->currentVersion->lock_token);
        $this->assertNotNull($session->id);
    }

    public function test_comment_required_blocks_empty_comment(): void
    {
        $tenant = $this->makeTenant();
        $space = $this->makeSpace($tenant);
        $user = $this->makeUser($tenant, 'user');
        $doc = $this->makeDocument($tenant, $user, $space);

        TenantSettings::for($tenant)->set(['comment_required' => true]);

        $this->actingAsUser($user);
        $this->post(route('documents.upload-version', $doc), [
            'file' => UploadedFile::fake()->createWithContent('v2.txt', 'v2', 'text/plain'),
            'comment' => '',
        ])->assertSessionHasErrors('comment');

        $doc->refresh();
        $this->assertSame(1, $doc->versions()->count());
    }

    public function test_external_share_requires_expiry_and_token_access(): void
    {
        $tenant = $this->makeTenant();
        $space = $this->makeSpace($tenant);
        $user = $this->makeUser($tenant, 'user');
        $doc = $this->makeDocument($tenant, $user, $space);

        TenantSettings::for($tenant)->set(['external_sharing_enabled' => true]);
        $this->actingAsUser($user);

        // Without expiry → refused.
        $this->post(route('documents.share', $doc), ['external' => '1', 'permission' => 'view'])
            ->assertSessionHasErrors('expires_at');

        // Valid external share with password.
        $response = $this->post(route('documents.share', $doc), [
            'external' => '1',
            'permission' => 'view',
            'expires_at' => now()->addDays(7)->format('Y-m-d'),
            'password' => 'secret4',
        ]);
        $response->assertSessionHas('success');

        $share = Share::withoutGlobalScopes()->where('document_id', $doc->id)->where('is_external', true)->first();
        $this->assertNotNull($share);
        $this->assertNotNull($share->token);
        $this->assertNotNull($share->password_hash);
        $this->assertTrue($share->isActive());

        // Extract the raw token from the success message to test the public flow.
        preg_match('#/share/([A-Za-z0-9]+)#', session('success'), $m);
        $raw = $m[1] ?? null;
        $this->assertNotNull($raw);

        // Public flow: logout to simulate an external visitor (guest routes).
        Auth::logout();

        // Public page: locked by password.
        $this->get(route('share.external.show', $raw))->assertOk()->assertSee('protégé par un mot de passe');

        // Wrong password refused.
        $this->post(route('share.external.unlock', $raw), ['password' => 'wrong'])->assertSessionHasErrors('password');

        // Correct password unlocks.
        $this->post(route('share.external.unlock', $raw), ['password' => 'secret4'])
            ->assertRedirect(route('share.external.show', $raw));

        // Download works with the session unlocked.
        $this->get(route('share.external.download', $raw))->assertOk();

        // Revocation kills the link.
        $share->update(['revoked_at' => now()]);
        $this->get(route('share.external.show', $raw))->assertNotFound();
    }

    public function test_external_share_disabled_by_default(): void
    {
        $tenant = $this->makeTenant();
        $space = $this->makeSpace($tenant);
        $user = $this->makeUser($tenant, 'user');
        $doc = $this->makeDocument($tenant, $user, $space);

        $this->actingAsUser($user);
        $this->post(route('documents.share', $doc), [
            'external' => '1',
            'permission' => 'view',
            'expires_at' => now()->addDays(7)->format('Y-m-d'),
        ])->assertSessionHasErrors('external');
    }

    public function test_system_role_cannot_be_deleted(): void
    {
        $tenant = $this->makeTenant();
        $admin = $this->makeUser($tenant, 'tenant_admin');
        $this->actingAsUser($admin);

        $role = Role::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('slug', 'user')->first();

        $this->delete(route('admin.roles.delete', $role))->assertSessionHasErrors('role');
        $this->assertDatabaseHas('roles', ['id' => $role->id]);
    }

    public function test_type_with_documents_cannot_be_deleted(): void
    {
        $tenant = $this->makeTenant();
        $space = $this->makeSpace($tenant);
        $user = $this->makeUser($tenant, 'user');
        $type = DocumentType::create([
            'tenant_id' => $tenant->id,
            'name' => 'Contrat',
            'slug' => 'contrat-'.uniqid(),
        ]);
        $this->makeDocument($tenant, $user, $space, ['document_type_id' => $type->id]);

        $admin = $this->makeUser($tenant, 'tenant_admin');
        $this->actingAsUser($admin);

        $this->delete(route('admin.types.delete', $type))->assertSessionHasErrors('type');
        $this->assertDatabaseHas('document_types', ['id' => $type->id]);
    }

    public function test_platform_settings_apply_to_new_tenants(): void
    {
        $platform = PlatformSettings::instance();
        $platform->set([
            'external_sharing_enabled' => true,
            'comment_required' => true,
            'allowed_mimes' => ['application/pdf'],
        ]);

        // Same merge the controller performs at tenant creation (D-ADM4).
        $tenantSettings = ['ai_enabled' => true, 'ai_providers' => ['mock']];
        $platform->applyToNewTenant($tenantSettings);

        $this->assertTrue($tenantSettings['external_sharing_enabled']);
        $this->assertTrue($tenantSettings['comment_required']);
        $this->assertSame(['application/pdf'], $tenantSettings['allowed_mimes']);
    }

    public function test_audit_export_returns_csv(): void
    {
        $tenant = $this->makeTenant();
        $admin = $this->makeUser($tenant, 'tenant_admin');
        $this->actingAsUser($admin);

        app(AuditService::class)->log('admin.settings.updated', 'tenant', $tenant->id);

        $response = $this->get(route('admin.audit.export'));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('admin.settings.updated', $response->streamedContent());
    }

    public function test_admin_actions_are_audited(): void
    {
        $tenant = $this->makeTenant();
        $admin = $this->makeUser($tenant, 'tenant_admin');
        $this->actingAsUser($admin);

        $this->post(route('admin.settings.update'), [
            'language' => 'fr',
            'password_min_length' => 10,
            'allowed_mimes' => ['application/pdf'],
            'auto_lock_on_edit' => '1',
            'external_sharing_enabled' => '1',
        ])->assertRedirect();

        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.settings.updated', 'user_id' => $admin->id]);

        // Branding update audited too.
        $this->post(route('admin.settings.branding'), ['brand_color' => '#ff0000', 'brand_logo_url' => 'https://example.com/l.png'])
            ->assertRedirect();
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.branding.updated', 'user_id' => $admin->id]);
    }

    public function test_settings_saves_branding_and_text_ai_providers(): void
    {
        $tenant = $this->makeTenant();
        $admin = $this->makeUser($tenant, 'tenant_admin');
        $this->actingAsUser($admin);

        // Branding intégré au formulaire principal + providers IA en champ texte.
        $this->post(route('admin.settings.update'), [
            'language' => 'fr',
            'timezone' => 'Europe/Paris',
            'brand_color' => '#123456',
            'brand_logo_url' => 'https://example.com/logo.png',
            'ai_enabled' => '1',
            'ai_providers' => 'mock, openai',
            'allowed_mimes' => ['application/pdf'],
        ])->assertRedirect();

        $tenant->refresh();
        $this->assertSame('#123456', $tenant->branding['color']);
        $this->assertSame('https://example.com/logo.png', $tenant->branding['logo_url']);
        $this->assertSame(['mock', 'openai'], $tenant->settings['ai_providers']);
        $this->assertSame('Europe/Paris', $tenant->settings['timezone']);
    }

    public function test_settings_page_shows_readable_mime_labels(): void
    {
        $tenant = $this->makeTenant();
        $admin = $this->makeUser($tenant, 'tenant_admin');
        $this->actingAsUser($admin);

        $html = $this->get(route('admin.settings'))->getContent();

        // Libellés lisibles au lieu des MIME bruts, avec le MIME en tooltip.
        $this->assertStringContainsString('Word (DOCX)', $html);
        $this->assertStringContainsString('Excel (XLSX)', $html);
        $this->assertStringContainsString('Markdown (MD)', $html);
        $this->assertStringContainsString('title="application/pdf"', $html);

        // Branding intégré au formulaire principal (pas de 2e formulaire séparé).
        $this->assertStringContainsString('name="brand_color"', $html);
        $this->assertStringContainsString('name="brand_logo_url"', $html);
        $this->assertStringNotContainsString('admin.settings.branding', $html);
    }

    public function test_settings_page_renders_when_ai_providers_stored_as_string(): void
    {
        // Régression prod : tenants.settings.ai_providers stocké en chaîne → TypeError implode().
        $tenant = $this->makeTenant(['settings' => ['ai_enabled' => true, 'ai_providers' => 'mock,openai']]);
        $admin = $this->makeUser($tenant, 'tenant_admin');
        $this->actingAsUser($admin);

        $this->get(route('admin.settings'))
            ->assertOk()
            ->assertSee('mock,openai');
    }

    public function test_branding_is_applied_in_layout(): void
    {
        $tenant = $this->makeTenant();
        $user = $this->makeUser($tenant, 'user');
        $tenant->update(['branding' => ['color' => '#123456', 'logo_url' => 'https://example.com/logo.png']]);

        $space = $this->makeSpace($tenant);
        $this->makeDocument($tenant, $user, $space);

        $this->actingAsUser($user);
        $html = $this->get(route('dashboard'))->getContent();

        $this->assertStringContainsString('--brand: #123456', $html);
        $this->assertStringContainsString('https://example.com/logo.png', $html);
    }
}
