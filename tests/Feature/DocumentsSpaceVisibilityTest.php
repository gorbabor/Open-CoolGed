<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\RolePermission;
use App\Services\PersonalSpaceService;
use Tests\TestCase;

class DocumentsSpaceVisibilityTest extends TestCase
{
    private function denyViewOnSpace($tenant, string $roleSlug, $space): void
    {
        $role = Role::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('slug', $roleSlug)->first();
        $perm = Permission::where('slug', 'documents.view')->first();
        RolePermission::firstOrCreate(
            ['role_id' => $role->id, 'permission_id' => $perm->id, 'scope_type' => 'space', 'scope_id' => $space->id],
            ['denied' => true]
        );
    }

    public function test_space_selector_hides_denied_spaces_for_user(): void
    {
        $tenant = $this->makeTenant();
        $user = $this->makeUser($tenant, 'user');
        $spaceA = $this->makeSpace($tenant, 'Espace Visible');
        $spaceB = $this->makeSpace($tenant, 'Espace Masque');
        $this->denyViewOnSpace($tenant, 'user', $spaceB);

        $docA = $this->makeDocument($tenant, $user, $spaceA, ['title' => 'Doc visible']);
        $this->makeDocument($tenant, $user, $spaceB, ['title' => 'Doc masque']);

        $this->actingAsUser($user);
        $html = $this->get(route('documents.index'))->assertOk()->getContent();
        $this->assertStringContainsString($spaceA->name, $html);
        $this->assertStringNotContainsString($spaceB->name, $html);
        $this->assertStringContainsString('Doc visible', $html);
        $this->assertStringNotContainsString('Doc masque', $html);

        $create = $this->get(route('documents.create'))->assertOk()->getContent();
        $this->assertStringContainsString($spaceA->name, $create);
        $this->assertStringNotContainsString($spaceB->name, $create);
    }

    public function test_v02_space_filter_hides_denied_spaces(): void
    {
        $tenant = $this->makeTenant();
        $user = $this->makeUser($tenant, 'user');
        $spaceA = $this->makeSpace($tenant, 'Section Visible');
        $spaceB = $this->makeSpace($tenant, 'Section Masquee');
        $this->denyViewOnSpace($tenant, 'user', $spaceB);

        $this->makeDocument($tenant, $user, $spaceA, ['title' => 'Doc V02 visible', 'status' => 'approuve_applicable', 'is_active_version' => true, 'owner_id' => $user->id]);
        $this->makeDocument($tenant, $user, $spaceB, ['title' => 'Doc V02 masque', 'status' => 'approuve_applicable', 'is_active_version' => true, 'owner_id' => $user->id]);

        $this->actingAsUser($user);
        $html = $this->get(route('v02.my-documents'))->assertOk()->getContent();
        $this->assertStringContainsString($spaceA->name, $html);
        $this->assertStringNotContainsString($spaceB->name, $html);
        $this->assertStringContainsString('Doc V02 visible', $html);
        $this->assertStringNotContainsString('Doc V02 masque', $html);
    }

    public function test_personal_spaces_never_appear_in_documents_space_selectors(): void
    {
        $tenant = $this->makeTenant();
        $user = $this->makeUser($tenant, 'user');
        $personal = app(PersonalSpaceService::class)->ensure($user);
        $shared = $this->makeSpace($tenant, 'Partage');

        $this->actingAsUser($user);
        foreach ([route('documents.index'), route('documents.create')] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            $this->assertStringContainsString($shared->name, $html);
            $this->assertStringNotContainsString($personal->name, $html);
        }
    }

    public function test_tenant_admin_still_offers_all_shared_spaces(): void
    {
        $tenant = $this->makeTenant();
        $admin = $this->makeUser($tenant, 'tenant_admin');
        $spaceA = $this->makeSpace($tenant, 'Espace A');
        $spaceB = $this->makeSpace($tenant, 'Espace B');

        $this->actingAsUser($admin);
        $html = $this->get(route('documents.index'))->assertOk()->getContent();
        $this->assertStringContainsString($spaceA->name, $html);
        $this->assertStringContainsString($spaceB->name, $html);
    }
}
