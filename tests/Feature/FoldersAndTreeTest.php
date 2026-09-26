<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Folder;
use App\Models\Space;
use Tests\TestCase;

class FoldersAndTreeTest extends TestCase
{
    private function makeSubfolder($tenant, Space $space, ?Folder $parent, string $name): Folder
    {
        return Folder::create([
            'tenant_id' => $tenant->id,
            'space_id' => $space->id,
            'parent_id' => $parent?->id,
            'name' => $name,
        ]);
    }

    public function test_subfolders_can_be_created_up_to_five_levels(): void
    {
        $tenant = $this->makeTenant();
        $admin = $this->makeUser($tenant, 'tenant_admin');
        $space = $this->makeSpace($tenant, 'Section');
        $this->actingAsUser($admin);

        $parent = null;
        for ($level = 1; $level <= 5; $level++) {
            $this->post(route('folders.store', $space), [
                'name' => 'Niveau '.$level,
                'parent_id' => $parent?->id,
            ])->assertRedirect();

            $parent = Folder::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('name', 'Niveau '.$level)->first();
            $this->assertNotNull($parent, 'Niveau '.$level.' créé');
            $this->assertSame($level, $parent->depth());
        }

        // Au-delà de 5 niveaux : refusé avec message, aucun dossier créé.
        $this->post(route('folders.store', $space), [
            'name' => 'Niveau 6',
            'parent_id' => $parent->id,
        ])->assertSessionHasErrors('parent_id');

        $this->assertDatabaseMissing('folders', ['tenant_id' => $tenant->id, 'name' => 'Niveau 6']);
    }

    public function test_parent_must_belong_to_the_same_space(): void
    {
        $tenant = $this->makeTenant();
        $admin = $this->makeUser($tenant, 'tenant_admin');
        $spaceA = $this->makeSpace($tenant, 'Espace A');
        $spaceB = $this->makeSpace($tenant, 'Espace B');
        $parentInB = $this->makeSubfolder($tenant, $spaceB, null, 'Parent B');
        $this->actingAsUser($admin);

        $this->post(route('folders.store', $spaceA), [
            'name' => 'Enfant interdit',
            'parent_id' => $parentInB->id,
        ])->assertSessionHasErrors('parent_id');

        $this->assertDatabaseMissing('folders', ['tenant_id' => $tenant->id, 'name' => 'Enfant interdit']);
    }

    public function test_tree_renders_nested_folders_with_counts(): void
    {
        $tenant = $this->makeTenant();
        $user = $this->makeUser($tenant, 'user');
        $space = $this->makeSpace($tenant, 'Section');
        $root = $this->makeSubfolder($tenant, $space, null, 'Dossier Racine');
        $child = $this->makeSubfolder($tenant, $space, $root, 'Sous-dossier Enfant');
        $this->makeDocument($tenant, $user, $space, ['title' => 'Doc enfant', 'folder_id' => $child->id]);
        $this->actingAsUser($user);

        $html = $this->get(route('spaces.index'))->assertOk()->getContent();
        $this->assertStringContainsString('Dossier Racine', $html);
        $this->assertStringContainsString('Sous-dossier Enfant', $html);
        $this->assertStringContainsString('1 doc.', $html);
        $this->assertStringContainsString('folder-count', $html);
        $this->assertTrue(strpos($html, 'Dossier Racine') < strpos($html, 'Sous-dossier Enfant'), 'Imbrication affichée parent avant enfant');
        $this->assertStringContainsString('folder-children', $html);
    }

    public function test_tree_marks_levels_and_collapses_subfolders_by_default(): void
    {
        $tenant = $this->makeTenant();
        $user = $this->makeUser($tenant, 'user');
        $space = $this->makeSpace($tenant, 'Section');
        $root = $this->makeSubfolder($tenant, $space, null, 'Dossier Racine');
        $child = $this->makeSubfolder($tenant, $space, $root, 'Sous-dossier Enfant');
        $this->makeSubfolder($tenant, $space, null, 'Dossier Vide');
        $this->makeDocument($tenant, $user, $space, ['title' => 'Doc enfant', 'folder_id' => $child->id]);
        $this->actingAsUser($user);

        $html = $this->get(route('spaces.index'))->assertOk()->getContent();

        // Hiérarchie visuelle : niveau 1 = icône pleine + nom semi-gras, niveau 2 = icône contour.
        $this->assertStringContainsString('bi-folder-fill', $html);
        $this->assertStringContainsString('bi-folder2', $html);
        $this->assertStringContainsString('folder-toggle fw-semibold', $html);
        $this->assertStringContainsString('folder-toggle folder-sub', $html);

        // Sous-dossiers repliés par défaut (liste masquée + chevron fermé).
        $this->assertStringContainsString('folder-children d-none', $html);
        $this->assertStringContainsString('class="folder-caret"', $html);
        $this->assertStringContainsString('aria-expanded="false"', $html);

        // Chevron uniquement sur les dossiers parents ; espace réservé pour les feuilles.
        $this->assertSame(1, substr_count($html, 'class="folder-caret"'));
        $this->assertSame(2, substr_count($html, 'class="caret-spacer"'));

        // Compteur : pastille affichée seulement si le dossier contient des documents.
        $this->assertSame(1, substr_count($html, 'class="folder-count"'));
        $this->assertStringContainsString('>1 doc.</span>', $html);
    }

    public function test_delete_folder_is_blocked_when_it_has_children(): void
    {
        $tenant = $this->makeTenant();
        $admin = $this->makeUser($tenant, 'tenant_admin');
        $space = $this->makeSpace($tenant, 'Section');
        $root = $this->makeSubfolder($tenant, $space, null, 'Parent');
        $this->makeSubfolder($tenant, $space, $root, 'Enfant');
        $this->actingAsUser($admin);

        $this->delete(route('folders.delete', $root))->assertSessionHasErrors('folder');
        $this->assertDatabaseHas('folders', ['id' => $root->id]);
    }

    public function test_documents_filter_and_path_use_folder_hierarchy(): void
    {
        $tenant = $this->makeTenant();
        $user = $this->makeUser($tenant, 'user');
        $space = $this->makeSpace($tenant, 'Section');
        $root = $this->makeSubfolder($tenant, $space, null, 'Contrats');
        $child = $this->makeSubfolder($tenant, $space, $root, '2026');
        $doc = $this->makeDocument($tenant, $user, $space, ['title' => 'Doc 2026', 'folder_id' => $child->id]);
        $this->makeDocument($tenant, $user, $space, ['title' => 'Doc hors dossier']);
        $this->actingAsUser($user);

        // Filtre dossier : seul le document du sous-dossier remonte.
        $html = $this->get(route('documents.index', ['folder_id' => $child->id]))->assertOk()->getContent();
        $this->assertStringContainsString('Doc 2026', $html);
        $this->assertStringNotContainsString('Doc hors dossier', $html);

        // Options indentées (profondeur 2 → préfixe « — »).
        $this->assertStringContainsString('— 2026', $html);

        // Chemin complet sur la fiche.
        $show = $this->get(route('documents.show', $doc))->assertOk()->getContent();
        $this->assertStringContainsString('Contrats › 2026', $show);
    }
}
