<?php

namespace Tests\Feature;

use App\Models\DocumentType;
use Tests\TestCase;

class DocumentMetadataEditTest extends TestCase
{
    public function test_metadata_edits_title_space_folder_type(): void
    {
        $tenant = $this->makeTenant();
        $spaceA = $this->makeSpace($tenant);
        $spaceB = $this->makeSpace($tenant);
        $folderB = $this->makeFolder($tenant, $spaceB);
        $type = DocumentType::create([
            'tenant_id' => $tenant->id,
            'name' => 'Contrat',
            'slug' => 'contrat-'.uniqid(),
        ]);
        $user = $this->makeUser($tenant, 'manager');
        $doc = $this->makeDocument($tenant, $user, $spaceA);

        $this->actingAsUser($user);

        $this->post(route('documents.metadata', $doc), [
            'title' => 'Nouveau titre',
            'space_id' => $spaceB->id,
            'folder_id' => $folderB->id,
            'document_type_id' => $type->id,
            'reference' => 'REF-EDITED',
            'confidentiality' => 'confidential',
        ])->assertRedirect();

        $doc->refresh();

        $this->assertSame('Nouveau titre', $doc->title);
        $this->assertSame($spaceB->id, $doc->space_id);
        $this->assertSame($folderB->id, $doc->folder_id);
        $this->assertSame($type->id, $doc->document_type_id);
        $this->assertSame('REF-EDITED', $doc->reference);
        $this->assertSame('confidential', $doc->confidentiality);
    }

    public function test_metadata_rejects_folder_of_another_space(): void
    {
        $tenant = $this->makeTenant();
        $spaceA = $this->makeSpace($tenant);
        $spaceB = $this->makeSpace($tenant);
        $folderB = $this->makeFolder($tenant, $spaceB);
        $user = $this->makeUser($tenant, 'manager');
        $doc = $this->makeDocument($tenant, $user, $spaceA, ['title' => 'Titre initial']);

        $this->actingAsUser($user);

        $this->post(route('documents.metadata', $doc), [
            'title' => 'Titre pirate',
            'space_id' => $spaceA->id,
            'folder_id' => $folderB->id,
        ])->assertSessionHasErrors('folder_id');

        $doc->refresh();

        $this->assertSame('Titre initial', $doc->title);
        $this->assertNull($doc->folder_id);
    }
}
