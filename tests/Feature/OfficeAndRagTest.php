<?php

namespace Tests\Feature;

use App\Models\OfficeSession;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RolePermission;
use App\Services\MfaService;
use App\Services\OfficeService;
use App\Services\RagService;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class OfficeAndRagTest extends TestCase
{
    public function test_viewer_navigation_links_escape_the_embedding_iframe(): void
    {
        $tenant = $this->makeTenant();
        $space = $this->makeSpace($tenant);
        $user = $this->makeUser($tenant, 'user');
        $doc = $this->makeDocument($tenant, $user, $space);

        $this->actingAsUser($user);

        // Le viewer est embarqué dans un iframe par la fiche document (documents.show).
        // Ses liens de navigation doivent cibler le niveau supérieur (target="_top"),
        // sinon la fiche entière se recharge dans le frame (menus dupliqués).
        $response = $this->get(route('office.viewer', $doc));
        $response->assertOk();
        $html = $response->getContent();

        $this->assertStringContainsString('target="_top"', $html);
        $this->assertStringContainsString('Fiche document', $html);
        $this->assertStringContainsString('> Retour</a>', $html);
        $this->assertMatchesRegularExpression('/<a[^>]*href="[^"]*documents\/'.$doc->id.'[^"]*"[^>]*target="_top"/', $html);

        // La fiche document elle-même embarque bien le viewer dans un iframe.
        $fiche = $this->get(route('documents.show', $doc));
        $fiche->assertOk();
        $this->assertStringContainsString('<iframe', $fiche->getContent());
    }

    public function test_office_fallback_reimport_creates_new_version(): void
    {
        $tenant = $this->makeTenant();
        $space = $this->makeSpace($tenant);
        $user = $this->makeUser($tenant, 'user');
        $doc = $this->makeDocument($tenant, $user, $space);

        $this->actingAsUser($user);
        $session = app(OfficeService::class)->startEdit($user, $doc);

        // Document locked during the session.
        $doc->refresh();
        $this->assertNotNull($doc->currentVersion->lock_token);

        // CA-019: fallback — download then re-import produces a new version.
        $download = $this->get(route('office.download', $session->raw_token));
        $download->assertOk();

        $reimport = $this->post(route('office.reimport', $doc), [
            'file' => UploadedFile::fake()->createWithContent('edite.txt', 'contenu édité', 'text/plain'),
        ]);
        $reimport->assertRedirect();

        $doc->refresh();
        $this->assertSame(2, $doc->versions()->count());
        $this->assertSame('2.0', $doc->currentVersion->version);

        // Token-scoped download is refused once expired.
        OfficeSession::find($session->id)->update(['expires_at' => now()->subMinute()]);
        $this->get(route('office.download', $session->raw_token))->assertStatus(404);
    }

    public function test_office_return_creates_version_and_releases_lock(): void
    {
        $tenant = $this->makeTenant();
        $space = $this->makeSpace($tenant);
        $user = $this->makeUser($tenant, 'user');
        $doc = $this->makeDocument($tenant, $user, $space);

        $this->actingAsUser($user);
        $session = app(OfficeService::class)->startEdit($user, $doc);

        $return = $this->post(route('office.return', $session->raw_token), [
            'file' => UploadedFile::fake()->createWithContent('retour.txt', 'fichier édité en ligne', 'text/plain'),
        ]);
        $return->assertRedirect();

        $doc->refresh();
        $this->assertSame(2, $doc->versions()->count());
        $this->assertNull($doc->currentVersion->lock_token);
        $this->assertNotNull(OfficeSession::find($session->id)->returned_at);
    }

    public function test_rag_never_returns_inaccessible_segments(): void
    {
        $tenant = $this->makeTenant();
        $space = $this->makeSpace($tenant);
        $owner = $this->makeUser($tenant, 'user');
        $user = $this->makeUser($tenant, 'user');

        $accessible = $this->makeDocument($tenant, $owner, $space, ['content' => 'Budget marketing 2026 approuvé par la direction.']);
        $secret = $this->makeDocument($tenant, $owner, $space, ['content' => 'SALAIRE DIRECTEUR CONFIDENTIEL 999999']);

        // Deny 'view' on the secret document for the 'user' role.
        $role = Role::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('slug', 'user')->first();
        $view = Permission::where('slug', 'documents.view')->first();
        RolePermission::create([
            'role_id' => $role->id,
            'permission_id' => $view->id,
            'scope_type' => 'document',
            'scope_id' => $secret->id,
            'denied' => true,
        ]);

        $this->actingAsUser($user);
        app(RagService::class)->indexDocument($accessible);
        app(RagService::class)->indexDocument($secret);

        $result = app(RagService::class)->answer($user, 'salaire directeur');

        // RM-019: only segments of accessible documents are returned.
        $this->assertStringNotContainsString('SALAIRE', $result['answer']);
        $titles = array_column($result['sources'], 'document_title');
        $this->assertNotContains($secret->title, $titles);
    }

    public function test_suspended_tenant_cannot_login(): void
    {
        $tenant = $this->makeTenant(['status' => 'suspended']);
        $user = $this->makeUser($tenant, 'user');

        $response = $this->post(route('login.post'), [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        // CA-017: session refused, user stays guest.
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_mfa_challenge_flow(): void
    {
        $tenant = $this->makeTenant();
        $secret = app(MfaService::class)->generateSecret();
        $user = $this->makeUser($tenant, 'user', ['mfa_enabled' => true, 'mfa_secret' => $secret]);

        $response = $this->post(route('login.post'), [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('mfa.verify'));
        $this->assertGuest();

        $code = app(MfaService::class)->currentCode($secret);
        $this->post(route('mfa.verify.post'), ['code' => $code])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }
}
