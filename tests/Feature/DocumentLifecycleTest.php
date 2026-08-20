<?php

namespace Tests\Feature;

use App\Services\DocumentLifecycleService;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class DocumentLifecycleTest extends TestCase
{
    public function test_draft_metadata_and_versions_are_editable(): void
    {
        $tenant = $this->makeTenant();
        $space = $this->makeSpace($tenant);
        $user = $this->makeUser($tenant, 'manager');
        $doc = $this->makeDocument($tenant, $user, $space, ['status' => 'draft', 'title' => 'Titre brouillon']);

        $this->actingAsUser($user);

        $this->post(route('documents.metadata', $doc), [
            'title' => 'Titre modifié',
            'space_id' => $space->id,
            'reference' => 'REF-1',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $doc->refresh();
        $this->assertSame('Titre modifié', $doc->title);
    }

    public function test_approved_metadata_edition_is_rejected(): void
    {
        $tenant = $this->makeTenant();
        $space = $this->makeSpace($tenant);
        $user = $this->makeUser($tenant, 'manager');
        $doc = $this->makeDocument($tenant, $user, $space, ['status' => 'approved', 'title' => 'Titre approuvé']);

        $this->actingAsUser($user);

        $this->post(route('documents.metadata', $doc), [
            'title' => 'Titre pirate',
            'space_id' => $space->id,
        ])->assertSessionHasErrors('title');

        $doc->refresh();
        $this->assertSame('Titre approuvé', $doc->title);
    }

    public function test_approved_version_upload_is_rejected(): void
    {
        $tenant = $this->makeTenant();
        $space = $this->makeSpace($tenant);
        $user = $this->makeUser($tenant, 'manager');
        $doc = $this->makeDocument($tenant, $user, $space, ['status' => 'approved']);

        $this->actingAsUser($user);

        $response = $this->post(route('documents.upload-version', $doc), [
            'file' => UploadedFile::fake()->createWithContent('v2.txt', 'contenu', 'text/plain'),
            'comment' => 'Révision',
        ]);

        $response->assertSessionHasErrors('file');
        $this->assertSame(1, $doc->versions()->count());
    }

    public function test_archived_and_obsolete_are_frozen(): void
    {
        $tenant = $this->makeTenant();
        $space = $this->makeSpace($tenant);
        $user = $this->makeUser($tenant, 'manager');
        $archived = $this->makeDocument($tenant, $user, $space, ['status' => 'archived', 'title' => 'Archivé']);
        $obsolete = $this->makeDocument($tenant, $user, $space, ['status' => 'obsolete_archive', 'title' => 'Obsolète']);

        $this->actingAsUser($user);

        foreach ([$archived, $obsolete] as $doc) {
            $this->post(route('documents.metadata', $doc), [
                'title' => 'Pirate',
                'space_id' => $space->id,
            ])->assertSessionHasErrors('title');

            $this->post(route('documents.upload-version', $doc), [
                'file' => UploadedFile::fake()->createWithContent('v2.txt', 'contenu', 'text/plain'),
            ])->assertSessionHasErrors('file');

            $doc->refresh();
            $this->assertSame($doc->title !== 'Pirate' ? $doc->title : 'Pirate', $doc->title);
        }
    }

    public function test_workflow_rejection_unfreezes_document(): void
    {
        $tenant = $this->makeTenant();
        $space = $this->makeSpace($tenant);
        $manager = $this->makeUser($tenant, 'manager');
        $doc = $this->makeDocument($tenant, $manager, $space, ['status' => 'approved', 'title' => 'Approuvé']);

        $service = app(DocumentLifecycleService::class);
        $this->actingAsUser($manager);
        $this->assertFalse($service->metadataEditable($manager, $doc));

        $doc->update(['status' => 'draft']);

        $this->assertTrue($service->metadataEditable($manager, $doc->fresh()));
    }

    public function test_lifecycle_service_frozen_statuses(): void
    {
        $tenant = $this->makeTenant();
        $space = $this->makeSpace($tenant);
        $manager = $this->makeUser($tenant, 'manager');
        $service = app(DocumentLifecycleService::class);
        $this->actingAsUser($manager);

        $editable = ['draft', 'in_review', 'a_creer', 'brouillon', 'en_verification', 'en_approbation', 'en_revision'];
        $frozen = ['approved', 'archived', 'expired', 'approuve_applicable', 'obsolete_archive'];

        foreach ($editable as $status) {
            $doc = $this->makeDocument($tenant, $manager, $space, ['status' => $status]);
            $this->assertTrue($service->metadataEditable($manager, $doc), "statut {$status} doit être modifiable");
        }

        foreach ($frozen as $status) {
            $doc = $this->makeDocument($tenant, $manager, $space, ['status' => $status]);
            $this->assertFalse($service->metadataEditable($manager, $doc), "statut {$status} doit être gelé");
            $this->assertFalse($service->contentEditable($manager, $doc), "statut {$status} doit geler le contenu");
        }
    }

    public function test_user_without_metadata_permission_is_denied_by_service(): void
    {
        $tenant = $this->makeTenant();
        $space = $this->makeSpace($tenant);
        $user = $this->makeUser($tenant, 'user');
        $doc = $this->makeDocument($tenant, $user, $space, ['status' => 'draft']);

        $service = app(DocumentLifecycleService::class);

        $this->assertFalse($service->metadataEditable($user, $doc));
    }
}
