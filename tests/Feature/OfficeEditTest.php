<?php

namespace Tests\Feature;

use App\Models\DocumentVersion;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RolePermission;
use App\Services\OfficeService;
use Tests\TestCase;

class OfficeEditTest extends TestCase
{
    public function test_start_edit_on_text_redirects_to_embedded_editor(): void
    {
        $tenant = $this->makeTenant();
        $space = $this->makeSpace($tenant);
        $user = $this->makeUser($tenant, 'user');
        $doc = $this->makeDocument($tenant, $user, $space, ['content' => 'hello world']);

        $this->actingAsUser($user);
        $response = $this->get(route('office.start', $doc));

        $response->assertRedirect();
        $this->assertStringContainsString('/office/embedded/', $response->headers->get('Location'));

        // Lock taken (check-out).
        $doc->refresh();
        $this->assertNotNull($doc->currentVersion->lock_token);
    }

    public function test_embedded_save_creates_new_version_and_releases_lock(): void
    {
        $tenant = $this->makeTenant();
        $space = $this->makeSpace($tenant);
        $user = $this->makeUser($tenant, 'user');
        $doc = $this->makeDocument($tenant, $user, $space, ['content' => 'contenu initial']);

        $this->actingAsUser($user);
        $session = app(OfficeService::class)->startEdit($user, $doc);

        $response = $this->post(route('office.embedded.save', $session->raw_token), [
            'content' => 'contenu modifié en ligne',
        ]);

        $response->assertRedirect();

        $doc->refresh();

        // RM-005: a NEW version is created, history is kept.
        $this->assertSame(2, $doc->versions()->count());
        $this->assertSame('2.0', $doc->currentVersion->version);
        $this->assertSame('contenu modifié en ligne', $doc->currentVersion->extracted_text);

        // Lock released (on the checked-out version too), session closed, audit written.
        $oldVersion = DocumentVersion::withoutGlobalScopes()
            ->where('document_id', $doc->id)->orderBy('id')->first();
        $this->assertNull($oldVersion->lock_token);
        $this->assertNull($doc->currentVersion->lock_token);
        $this->assertNotNull($session->fresh()->returned_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'office.session.returned', 'resource_type' => 'office_session']);
    }

    public function test_embedded_save_rejects_invalid_or_foreign_token(): void
    {
        $tenant = $this->makeTenant();
        $space = $this->makeSpace($tenant);
        $user = $this->makeUser($tenant, 'user');
        $doc = $this->makeDocument($tenant, $user, $space, ['content' => 'initial']);

        $this->actingAsUser($user);

        $this->post(route('office.embedded.save', 'invalid-token'), ['content' => 'x'])
            ->assertSessionHasErrors('office');
        $this->assertSame(1, $doc->versions()->count());
    }

    public function test_embedded_save_by_another_user_is_refused(): void
    {
        $tenant = $this->makeTenant();
        $space = $this->makeSpace($tenant);
        $owner = $this->makeUser($tenant, 'user');
        $other = $this->makeUser($tenant, 'user');
        $doc = $this->makeDocument($tenant, $owner, $space, ['content' => 'initial']);

        $this->actingAsUser($owner);
        $session = app(OfficeService::class)->startEdit($owner, $doc);

        $this->actingAsUser($other);
        $this->post(route('office.embedded.save', $session->raw_token), ['content' => 'piraté'])
            ->assertSessionHasErrors('office');

        $doc->refresh();
        $this->assertSame(1, $doc->versions()->count());
        $this->assertSame('initial', $doc->currentVersion->extracted_text);
    }

    public function test_preview_content_requires_preview_permission(): void
    {
        $tenant = $this->makeTenant();
        $space = $this->makeSpace($tenant);
        $owner = $this->makeUser($tenant, 'user');
        $doc = $this->makeDocument($tenant, $owner, $space, ['content' => 'secret contenu']);

        // Remove preview AND edit permissions from the 'user' role:
        // viewing the content is only allowed with preview (or edit).
        $role = Role::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('slug', 'user')->first();
        foreach (['documents.preview', 'documents.edit'] as $slug) {
            $perm = Permission::where('slug', $slug)->first();
            RolePermission::where('role_id', $role->id)->where('permission_id', $perm->id)->delete();
        }

        $viewer = $this->makeUser($tenant, 'user');
        $this->actingAsUser($viewer);

        $this->get(route('documents.preview-content', [$doc, $doc->currentVersion->id]))->assertStatus(403);
    }

    public function test_preview_content_streams_with_permission(): void
    {
        $tenant = $this->makeTenant();
        $space = $this->makeSpace($tenant);
        $user = $this->makeUser($tenant, 'user');
        $doc = $this->makeDocument($tenant, $user, $space, ['content' => 'contenu à prévisualiser']);

        $this->actingAsUser($user);
        $response = $this->get(route('documents.preview-content', [$doc, $doc->currentVersion->id]));

        $response->assertOk();
        // Streamed as octet-stream: viewers parse the bytes; real MIME kept on download.
        $this->assertStringContainsString('application/octet-stream', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('contenu à prévisualiser', $response->streamedContent());
    }

    public function test_viewer_page_is_accessible_for_viewable_formats(): void
    {
        $tenant = $this->makeTenant();
        $space = $this->makeSpace($tenant);
        $user = $this->makeUser($tenant, 'user');
        $doc = $this->makeDocument($tenant, $user, $space, ['content' => 'aperçu texte']);

        $this->actingAsUser($user);
        $this->get(route('office.viewer', $doc))->assertOk();
    }

    public function test_start_edit_on_pdf_redirects_to_pdf_editor(): void
    {
        $tenant = $this->makeTenant();
        $space = $this->makeSpace($tenant);
        $user = $this->makeUser($tenant, 'user');
        $doc = $this->makeDocument($tenant, $user, $space);

        // Replace the version with a PDF one.
        $pdf = DocumentVersion::withoutGlobalScopes()->where('document_id', $doc->id)->first();
        $pdf->update(['mime_type' => 'application/pdf', 'file_name' => 'doc.pdf']);

        $this->actingAsUser($user);
        $response = $this->get(route('office.start', $doc));

        $response->assertRedirect();
        $this->assertStringContainsString('/office/pdf/', $response->headers->get('Location'));
    }

    public function test_pdf_stream_is_padded_against_download_managers(): void
    {
        $tenant = $this->makeTenant();
        $space = $this->makeSpace($tenant);
        $user = $this->makeUser($tenant, 'user');
        $doc = $this->makeDocument($tenant, $user, $space);

        // Replace the version with a PDF one.
        $pdf = DocumentVersion::withoutGlobalScopes()->where('document_id', $doc->id)->first();
        $pdfPath = storage_path('app/private/'.$pdf->file_path);
        file_put_contents($pdfPath, "%PDF-1.7\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF");
        $pdf->update(['mime_type' => 'application/pdf', 'file_name' => 'doc.pdf', 'size' => filesize($pdfPath)]);

        $this->actingAsUser($user);
        $response = $this->get(route('documents.preview-content', [$doc, $pdf->id]));

        $response->assertOk();
        $content = $response->streamedContent();

        // 64 spaces padding, then %PDF (keeps IDM from detecting the PDF at offset 0,
        // while pdf.js/pdf-lib accept the header within the first 1024 bytes).
        $this->assertSame(64, strspn($content, ' '));
        $this->assertStringStartsWith(str_repeat(' ', 64).'%PDF', $content);
    }

    public function test_office_formats_still_fall_back(): void
    {
        $tenant = $this->makeTenant();
        $space = $this->makeSpace($tenant);
        $user = $this->makeUser($tenant, 'user');
        $doc = $this->makeDocument($tenant, $user, $space);

        $version = DocumentVersion::withoutGlobalScopes()->where('document_id', $doc->id)->first();
        $version->update(['mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'file_name' => 'doc.docx']);

        $this->actingAsUser($user);
        $response = $this->get(route('office.start', $doc));

        $response->assertRedirect();
        $this->assertStringContainsString('/office/download/', $response->headers->get('Location'));
    }
}
