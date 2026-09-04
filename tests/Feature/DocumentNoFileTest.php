<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Services\V02DocumentService;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class DocumentNoFileTest extends TestCase
{
    public function test_creation_without_file_creates_document_without_version(): void
    {
        $tenant = $this->makeTenant();
        $space = $this->makeSpace($tenant);
        $user = $this->makeUser($tenant, 'user');
        $this->actingAsUser($user);

        $this->post(route('documents.store'), [
            'title' => 'Fiche sans fichier',
            'description' => 'Description saisie sans document chargé',
            'space_id' => $space->id,
            'confidentiality' => 'internal',
        ])->assertRedirect();

        $doc = Document::withoutGlobalScopes()->where('title', 'Fiche sans fichier')->first();
        $this->assertNotNull($doc, 'document créé');
        $this->assertSame('Description saisie sans document chargé', $doc->description);
        $this->assertSame(0, $doc->versions()->count(), 'aucune version créée');
        $this->assertNull($doc->current_version_id);
        $this->assertSame('draft', $doc->status);
    }

    public function test_list_and_show_render_document_without_file(): void
    {
        $tenant = $this->makeTenant();
        $space = $this->makeSpace($tenant);
        $user = $this->makeUser($tenant, 'user');
        $this->actingAsUser($user);
        $doc = $this->post(route('documents.store'), [
            'title' => 'Fiche sans fichier B', 'space_id' => $space->id,
        ])->assertRedirect();
        $doc = Document::withoutGlobalScopes()->where('title', 'Fiche sans fichier B')->first();

        $this->get(route('documents.show', $doc))->assertOk()->assertSee('sans fichier');
        $this->get(route('documents.index'))->assertOk()->assertSee('Fiche sans fichier B');
    }

    public function test_file_can_be_attached_later_as_first_version(): void
    {
        $tenant = $this->makeTenant();
        $space = $this->makeSpace($tenant);
        $user = $this->makeUser($tenant, 'user');
        $this->actingAsUser($user);
        $this->post(route('documents.store'), ['title' => 'Fiche à compléter', 'space_id' => $space->id])->assertRedirect();
        $doc = Document::withoutGlobalScopes()->where('title', 'Fiche à compléter')->first();

        $this->post(route('documents.upload-version', $doc), [
            'file' => UploadedFile::fake()->createWithContent('complet.txt', 'contenu', 'text/plain'),
            'comment' => 'Version initiale',
        ])->assertRedirect();

        $doc->refresh();
        $this->assertSame(1, $doc->versions()->count());
        $this->assertSame('1.0', $doc->currentVersion->version);
        $this->assertSame('text/plain', $doc->currentVersion->mime_type);
    }

    public function test_document_without_file_cannot_be_approved(): void
    {
        $tenant = $this->makeTenant();
        $space = $this->makeSpace($tenant);
        $user = $this->makeUser($tenant, 'user');
        $this->actingAsUser($user);
        $this->post(route('documents.store'), ['title' => 'Fiche non approuvable', 'space_id' => $space->id])->assertRedirect();
        $doc = Document::withoutGlobalScopes()->where('title', 'Fiche non approuvable')->first();

        $blockers = app(V02DocumentService::class)->approvalBlockers($doc);
        $this->assertNotEmpty($blockers, 'l’approbation est bloquée sans fichier');
        $this->assertStringContainsString('fichier', mb_strtolower($blockers[0]));
    }
}
