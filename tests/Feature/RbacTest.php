<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\RolePermission;
use Tests\TestCase;

class RbacTest extends TestCase
{
    public function test_user_without_download_permission_cannot_download(): void
    {
        $tenant = $this->makeTenant();
        $space = $this->makeSpace($tenant);
        $owner = $this->makeUser($tenant, 'user');
        $doc = $this->makeDocument($tenant, $owner, $space);

        // Create a "read-only" role without download.
        $role = Role::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('slug', 'user')->first();

        $download = Permission::where('slug', 'documents.download')->first();
        RolePermission::where('role_id', $role->id)->where('permission_id', $download->id)->delete();

        $viewer = $this->makeUser($tenant, 'user');
        $this->actingAsUser($viewer);

        $this->get(route('documents.show', $doc))->assertStatus(200);
        $this->get(route('documents.download', $doc))->assertStatus(403);
    }

    public function test_explicit_deny_overrides_inherited_allow(): void
    {
        $tenant = $this->makeTenant();
        $space = $this->makeSpace($tenant);
        $owner = $this->makeUser($tenant, 'user');
        $doc = $this->makeDocument($tenant, $owner, $space);

        $role = Role::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('slug', 'user')->first();
        $view = Permission::where('slug', 'documents.view')->first();

        // RM-008: explicit deny on the document scope beats the tenant-wide allow.
        RolePermission::create([
            'role_id' => $role->id,
            'permission_id' => $view->id,
            'scope_type' => 'document',
            'scope_id' => $doc->id,
            'denied' => true,
        ]);

        $viewer = $this->makeUser($tenant, 'user');
        $this->actingAsUser($viewer);

        $this->get(route('documents.show', $doc))->assertStatus(403);
    }

    public function test_role_permission_scope_limits_access_to_space(): void
    {
        $tenant = $this->makeTenant();
        $spaceA = $this->makeSpace($tenant);
        $spaceB = $this->makeSpace($tenant);
        $owner = $this->makeUser($tenant, 'user');
        $docA = $this->makeDocument($tenant, $owner, $spaceA);
        $docB = $this->makeDocument($tenant, $owner, $spaceB);

        $role = Role::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('slug', 'user')->first();
        $view = Permission::where('slug', 'documents.view')->first();
        RolePermission::where('role_id', $role->id)->where('permission_id', $view->id)->delete();

        RolePermission::create([
            'role_id' => $role->id,
            'permission_id' => $view->id,
            'scope_type' => 'space',
            'scope_id' => $spaceA->id,
            'denied' => false,
        ]);

        $member = $this->makeUser($tenant, 'user');
        $this->actingAsUser($member);

        $this->get(route('documents.show', $docA))->assertStatus(200);
        $this->get(route('documents.show', $docB))->assertStatus(403);
    }

    public function test_suspended_user_loses_all_access(): void
    {
        $tenant = $this->makeTenant();
        $space = $this->makeSpace($tenant);
        $owner = $this->makeUser($tenant, 'user');
        $doc = $this->makeDocument($tenant, $owner, $space);
        $member = $this->makeUser($tenant, 'user', ['status' => 'suspended']);

        $this->actingAsUser($member);
        $this->get(route('documents.show', $doc))->assertStatus(403);
    }
}
