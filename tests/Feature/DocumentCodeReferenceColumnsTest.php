<?php

namespace Tests\Feature;

use Tests\TestCase;

class DocumentCodeReferenceColumnsTest extends TestCase
{
    public function test_documents_reference_and_code_columns_are_optional_and_rendered(): void
    {
        $tenant = $this->makeTenant();
        $space = $this->makeSpace($tenant);
        $user = $this->makeUser($tenant, 'user');
        $this->actingAsUser($user);
        $this->makeDocument($tenant, $user, $space, [
            'title' => 'Doc codé', 'reference' => '001', 'document_code' => 'KAE-DOC-001',
        ]);
        $this->makeDocument($tenant, $user, $space, ['title' => 'Doc sans code']);

        // Sans sélection : les colonnes ne sont pas affichées.
        $html = $this->get(route('documents.index'))->assertOk()->getContent();
        $this->assertStringNotContainsString('>KAE-DOC-001<', $html);

        // Avec sélection : colonnes affichées, valeurs correctes, « — » pour les absents.
        $response = $this->get(route('documents.index', ['cols' => ['reference', 'document_code']]));
        $response->assertOk();
        $html = $response->getContent();
        $this->assertStringContainsString('>Référence<', $html);
        $this->assertStringContainsString('>Code<', $html);
        $this->assertStringContainsString('>KAE-DOC-001<', $html);
        $this->assertStringContainsString('>001<', $html);
        $user->refresh();
        $this->assertSame(['reference', 'document_code'], $user->doc_columns);
    }

    public function test_documents_reference_is_no_longer_shown_under_title(): void
    {
        $tenant = $this->makeTenant();
        $space = $this->makeSpace($tenant);
        $user = $this->makeUser($tenant, 'user');
        $this->actingAsUser($user);
        $this->makeDocument($tenant, $user, $space, [
            'title' => 'Doc titre', 'reference' => 'REF-UNIQUE-777',
        ]);

        $html = $this->get(route('documents.index'))->assertOk()->getContent();
        // La référence n'apparaît pas en sous-texte du titre quand la colonne n'est pas choisie.
        $titlePos = strpos($html, '>Doc titre<');
        $this->assertNotFalse($titlePos);
        $this->assertFalse(strpos(substr($html, $titlePos, 300), 'REF-UNIQUE-777'));
    }

    public function test_documents_sort_by_code_and_reference(): void
    {
        $tenant = $this->makeTenant();
        $space = $this->makeSpace($tenant);
        $user = $this->makeUser($tenant, 'user');
        $this->actingAsUser($user);
        $this->makeDocument($tenant, $user, $space, ['title' => 'Doc B', 'document_code' => 'KAE-002']);
        $this->makeDocument($tenant, $user, $space, ['title' => 'Doc A', 'document_code' => 'KAE-001']);
        $this->makeDocument($tenant, $user, $space, ['title' => 'Doc C', 'document_code' => 'KAE-003']);

        $response = $this->get(route('documents.index', ['cols' => ['document_code'], 'sort' => 'document_code', 'dir' => 'asc']));
        $response->assertOk();
        $html = $response->getContent();
        $this->assertTrue(strpos($html, '>Doc A<') < strpos($html, '>Doc B<'), 'asc : KAE-001 avant KAE-002');
        $this->assertTrue(strpos($html, '>Doc B<') < strpos($html, '>Doc C<'), 'asc : KAE-002 avant KAE-003');
    }

    public function test_my_documents_code_sortable_and_reference_optional(): void
    {
        $tenant = $this->makeTenant();
        $space = $this->makeSpace($tenant);
        $user = $this->makeUser($tenant, 'user');
        $this->actingAsUser($user);
        $this->makeDocument($tenant, $user, $space, [
            'title' => 'V02 B', 'owner_id' => $user->id, 'status' => 'approuve_applicable', 'is_active_version' => true,
            'document_code' => 'KAE-002', 'reference' => '002',
        ]);
        $this->makeDocument($tenant, $user, $space, [
            'title' => 'V02 A', 'owner_id' => $user->id, 'status' => 'approuve_applicable', 'is_active_version' => true,
            'document_code' => 'KAE-001', 'reference' => '001',
        ]);

        // Tri par code.
        $html = $this->get(route('v02.my-documents', ['sort' => 'document_code', 'dir' => 'asc']))->assertOk()->getContent();
        $this->assertTrue(strpos($html, '>V02 A<') < strpos($html, '>V02 B<'), 'tri code asc');

        // Colonne Référence optionnelle.
        $html = $this->get(route('v02.my-documents', ['cols' => ['reference']]))->assertOk()->getContent();
        $this->assertStringContainsString('>Référence<', $html);
        $this->assertStringContainsString('>002<', $html);
        $user->refresh();
        $this->assertSame(['reference'], $user->my_doc_columns);
    }
}
