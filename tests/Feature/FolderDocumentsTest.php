<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Folder;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RolePermission;
use App\Services\PersonalSpaceService;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FolderDocumentsTest extends TestCase
{
    private function makeSubfolder($tenant, $space, ?Folder $parent, string $name): Folder
    {
        return Folder::create([
            'tenant_id' => $tenant->id,
            'space_id' => $space->id,
            'parent_id' => $parent?->id,
            'name' => $name,
        ]);
    }

    private function denyViewOnSpace($tenant, string $roleSlug, $space): void
    {
        $role = Role::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('slug', $roleSlug)->first();
        $perm = Permission::where('slug', 'documents.view')->first();
        RolePermission::firstOrCreate(
            ['role_id' => $role->id, 'permission_id' => $perm->id, 'scope_type' => 'space', 'scope_id' => $space->id],
            ['denied' => true]
        );
    }

    public function test_folder_documents_lists_only_the_documents_of_the_folder(): void
    {
        $tenant = $this->makeTenant();
        $user = $this->makeUser($tenant, 'user');
        $space = $this->makeSpace($tenant, 'Section');
        $folder = $this->makeSubfolder($tenant, $space, null, 'Contrats');
        $this->makeDocument($tenant, $user, $space, ['title' => 'Doc du dossier', 'folder_id' => $folder->id, 'status' => 'approved']);
        $this->makeDocument($tenant, $user, $space, ['title' => 'Doc hors dossier']);
        $this->actingAsUser($user);

        $html = $this->get(route('folders.documents', $folder))->assertOk()->getContent();
        $this->assertStringContainsString('Doc du dossier', $html);
        $this->assertStringNotContainsString('Doc hors dossier', $html);
        $this->assertStringContainsString('v1.0', $html);
        $this->assertStringContainsString('Approuvé', $html);
        $this->assertStringContainsString('Voir tous les documents du dossier', $html);
        $this->assertStringContainsString('folder_id='.$folder->id, $html);
    }

    public function test_folder_documents_hides_documents_the_user_cannot_view(): void
    {
        $tenant = $this->makeTenant();
        $user = $this->makeUser($tenant, 'user');
        $space = $this->makeSpace($tenant, 'Section');
        $folder = $this->makeSubfolder($tenant, $space, null, 'Contrats');
        $this->makeDocument($tenant, $user, $space, ['title' => 'Doc visible', 'folder_id' => $folder->id]);
        $denied = $this->makeDocument($tenant, $user, $space, ['title' => 'Doc interdit', 'folder_id' => $folder->id]);

        $role = Role::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('slug', 'user')->first();
        $perm = Permission::where('slug', 'documents.view')->first();
        RolePermission::create([
            'role_id' => $role->id,
            'permission_id' => $perm->id,
            'scope_type' => 'document',
            'scope_id' => $denied->id,
            'denied' => true,
        ]);

        $this->actingAsUser($user);
        $html = $this->get(route('folders.documents', $folder))->assertOk()->getContent();
        $this->assertStringContainsString('Doc visible', $html);
        $this->assertStringNotContainsString('Doc interdit', $html);
    }

    public function test_folder_documents_requires_view_permission_on_the_space(): void
    {
        $tenant = $this->makeTenant();
        $user = $this->makeUser($tenant, 'user');
        $space = $this->makeSpace($tenant, 'Section masquée');
        $folder = $this->makeSubfolder($tenant, $space, null, 'Contrats');
        $this->makeDocument($tenant, $user, $space, ['title' => 'Doc masqué', 'folder_id' => $folder->id]);
        $this->denyViewOnSpace($tenant, 'user', $space);

        $this->actingAsUser($user);
        $this->get(route('folders.documents', $folder))->assertForbidden();
    }

    public function test_folder_documents_returns_404_for_another_tenant_folder(): void
    {
        $tenantA = $this->makeTenant();
        $userA = $this->makeUser($tenantA, 'user');
        $tenantB = $this->makeTenant();
        $userB = $this->makeUser($tenantB, 'user');
        $spaceB = $this->makeSpace($tenantB, 'Espace B');
        $folderB = $this->makeSubfolder($tenantB, $spaceB, null, 'Dossier B');
        $this->makeDocument($tenantB, $userB, $spaceB, ['title' => 'Doc B', 'folder_id' => $folderB->id]);

        $this->actingAsUser($userA);
        $this->get(route('folders.documents', $folderB))->assertNotFound();
    }

    public function test_folder_documents_returns_404_for_personal_space_folders(): void
    {
        $tenant = $this->makeTenant();
        $user = $this->makeUser($tenant, 'user');
        $personal = app(PersonalSpaceService::class)->ensure($user);
        $folder = $this->makeSubfolder($tenant, $personal, null, 'Perso');

        $this->actingAsUser($user);
        $this->get(route('folders.documents', $folder))->assertNotFound();
    }

    public function test_folder_documents_shows_an_empty_state(): void
    {
        $tenant = $this->makeTenant();
        $user = $this->makeUser($tenant, 'user');
        $space = $this->makeSpace($tenant, 'Section');
        $folder = $this->makeSubfolder($tenant, $space, null, 'Contrats');
        $this->actingAsUser($user);

        $html = $this->get(route('folders.documents', $folder))->assertOk()->getContent();
        $this->assertStringContainsString('Aucun document dans ce dossier.', $html);
        $this->assertStringNotContainsString('Voir tous les documents du dossier', $html);
    }

    public function test_tree_renders_folder_toggles_with_lazy_load_urls(): void
    {
        $tenant = $this->makeTenant();
        $user = $this->makeUser($tenant, 'user');
        $space = $this->makeSpace($tenant, 'Section');
        $folder = $this->makeSubfolder($tenant, $space, null, 'Contrats');
        $this->actingAsUser($user);

        $html = $this->get(route('spaces.index'))->assertOk()->getContent();
        $this->assertStringContainsString('folder-toggle', $html);
        $this->assertStringContainsString('data-url="'.route('folders.documents', $folder).'"', $html);
        $this->assertStringContainsString('folder-documents d-none', $html);
    }

    public function test_folder_documents_are_capped_at_fifty_with_a_total_mention(): void
    {
        $tenant = $this->makeTenant();
        $user = $this->makeUser($tenant, 'user');
        $space = $this->makeSpace($tenant, 'Section');
        $folder = $this->makeSubfolder($tenant, $space, null, 'Contrats');

        $previous = TenantContext::get();
        TenantContext::set($tenant->id);
        try {
            for ($i = 1; $i <= 51; $i++) {
                Document::create([
                    'tenant_id' => $tenant->id,
                    'space_id' => $space->id,
                    'folder_id' => $folder->id,
                    'title' => sprintf('Doc %02d', $i),
                    'status' => 'draft',
                    'confidentiality' => 'internal',
                    'created_by' => $user->id,
                ]);
            }
        } finally {
            TenantContext::set($previous);
        }

        DB::table('documents')->where('folder_id', $folder->id)->orderBy('id')->pluck('id')
            ->each(fn ($id, $index) => DB::table('documents')->where('id', $id)->update(['updated_at' => now()->subMinutes($index + 1)]));

        $this->actingAsUser($user);
        $html = $this->get(route('folders.documents', $folder))->assertOk()->getContent();
        $this->assertStringContainsString('50 premiers sur 51 documents.', $html);
        $this->assertStringNotContainsString('Doc 51', $html);
    }
}
