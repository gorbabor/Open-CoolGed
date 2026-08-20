<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RolePermission;
use App\Services\PermissionService;
use App\Support\TenantContext;
use Tests\TestCase;

class GroupRoleTest extends TestCase
{
    public function test_group_can_have_multiple_roles_and_role_multiple_groups(): void
    {
        $tenant = $this->makeTenant();
        $groupA = Group::create(['tenant_id' => $tenant->id, 'name' => 'Direction']);
        $groupB = Group::create(['tenant_id' => $tenant->id, 'name' => 'Projets']);

        $roleManager = Role::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('slug', 'manager')->first();
        $roleValidator = Role::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('slug', 'validator')->first();

        // CA-RBAC1: several roles on one group, several groups on one role.
        TenantContext::set($tenant->id);
        $groupA->roles()->attach([$roleManager->id => ['tenant_id' => $tenant->id], $roleValidator->id => ['tenant_id' => $tenant->id]]);
        $roleValidator->groups()->attach($groupB->id, ['tenant_id' => $tenant->id]);

        $this->assertSame(2, $groupA->roles()->count());
        $this->assertTrue($groupA->roles()->where('roles.id', $roleManager->id)->exists());
        $this->assertTrue($roleValidator->groups()->where('groups.id', $groupB->id)->exists());
        TenantContext::set(null);
    }

    public function test_user_inherits_roles_from_his_groups(): void
    {
        $tenant = $this->makeTenant();
        $user = $this->makeUser($tenant, 'user'); // direct role: user
        $group = Group::create(['tenant_id' => $tenant->id, 'name' => 'Direction']);

        $roleManager = Role::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('slug', 'manager')->first();
        $group->roles()->attach($roleManager->id, ['tenant_id' => $tenant->id]);
        $user->groups()->attach($group->id, ['tenant_id' => $tenant->id]);

        // CA-RBAC2: effective = direct (user) ∪ group roles (manager).
        $this->actingAsUser($user);
        $service = app(PermissionService::class);
        $effective = $service->effectiveRoleIds($user->fresh());

        $this->assertContains($roleManager->id, $effective);
        $directRole = $user->roles()->first();
        $this->assertContains($directRole->id, $effective);

        // The user now has manager capabilities (documents.archive is manager-only).
        $space = $this->makeSpace($tenant);
        $doc = $this->makeDocument($tenant, $user, $space);
        $this->assertTrue($service->can($user->fresh(), 'documents.archive', $doc));
    }

    public function test_group_role_deny_overrides_direct_allow(): void
    {
        $tenant = $this->makeTenant();
        $user = $this->makeUser($tenant, 'user'); // direct: documents.view allowed
        $group = Group::create(['tenant_id' => $tenant->id, 'name' => 'Restreint']);

        $roleUser = Role::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('slug', 'user')->first();
        $space = $this->makeSpace($tenant);
        $doc = $this->makeDocument($tenant, $user, $space);

        // Attach a group role that explicitly DENIES documents.view on this document.
        $view = Permission::where('slug', 'documents.view')->first();
        $denyRole = Role::create(['tenant_id' => $tenant->id, 'name' => 'NoView', 'slug' => 'noview-'.uniqid()]);
        RolePermission::create([
            'role_id' => $denyRole->id,
            'permission_id' => $view->id,
            'scope_type' => 'document',
            'scope_id' => $doc->id,
            'denied' => true,
        ]);
        $group->roles()->attach($denyRole->id, ['tenant_id' => $tenant->id]);
        $user->groups()->attach($group->id, ['tenant_id' => $tenant->id]);

        // CA-RBAC3: RM-008 extended — a group-role deny beats the direct allow.
        $this->assertFalse(app(PermissionService::class)->can($user->fresh(), 'documents.view', $doc));
    }

    public function test_group_roles_ui_persists_and_is_audited(): void
    {
        $tenant = $this->makeTenant();
        $group = Group::create(['tenant_id' => $tenant->id, 'name' => 'Direction']);
        $admin = $this->makeUser($tenant, 'tenant_admin');
        $member = $this->makeUser($tenant, 'user');
        $group->users()->attach($member->id, ['tenant_id' => $tenant->id]);

        $roleManager = Role::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('slug', 'manager')->first();

        $this->actingAsUser($admin);
        $this->post(route('admin.groups.roles', $group), ['role_ids' => [$roleManager->id]])
            ->assertRedirect();

        // CA-RBAC5: persisted + audited.
        $this->assertDatabaseHas('group_role', ['group_id' => $group->id, 'role_id' => $roleManager->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.group.roles.updated', 'user_id' => $admin->id]);

        // The member inherits the group role immediately.
        $this->assertTrue(app(PermissionService::class)->effectiveRoleIds($member->fresh()) !== []);
        $this->assertContains($roleManager->id, app(PermissionService::class)->effectiveRoleIds($member->fresh()));
    }

    public function test_users_page_shows_effective_permissions(): void
    {
        $tenant = $this->makeTenant();
        $group = Group::create(['tenant_id' => $tenant->id, 'name' => 'Direction']);
        $member = $this->makeUser($tenant, 'user');
        $group->users()->attach($member->id, ['tenant_id' => $tenant->id]);

        $roleManager = Role::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('slug', 'manager')->first();
        $group->roles()->attach($roleManager->id, ['tenant_id' => $tenant->id]);

        $admin = $this->makeUser($tenant, 'tenant_admin');
        $this->actingAsUser($admin);

        // CA-RBAC6: the effective permissions column reflects direct + group roles.
        $response = $this->get(route('admin.users'));
        $response->assertOk();
        $html = $response->getContent();
        $this->assertStringContainsString('Permissions effectives', $html);
        $this->assertStringContainsString($member->name, $html);

        $view = $response->viewData('effectivePermissions');
        $this->assertArrayHasKey($member->id, $view);
        $this->assertContains('documents.archive', $view[$member->id]);
        $this->assertContains('documents.view', $view[$member->id]);
    }

    public function test_group_role_scopes_apply_like_direct_roles(): void
    {
        $tenant = $this->makeTenant();
        $user = $this->makeUser($tenant, 'user');
        $spaceA = $this->makeSpace($tenant);
        $spaceB = $this->makeSpace($tenant);
        $owner = $this->makeUser($tenant, 'user');
        $docA = $this->makeDocument($tenant, $owner, $spaceA);
        $docB = $this->makeDocument($tenant, $owner, $spaceB);

        $group = Group::create(['tenant_id' => $tenant->id, 'name' => 'Compta']);

        // Restrictive role: documents.view allowed only on space A.
        $roleUser = Role::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('slug', 'user')->first();
        $view = Permission::where('slug', 'documents.view')->first();
        RolePermission::where('role_id', $roleUser->id)->where('permission_id', $view->id)->delete();

        $scopedRole = Role::create(['tenant_id' => $tenant->id, 'name' => 'SpaceA', 'slug' => 'spacea-'.uniqid()]);
        RolePermission::create([
            'role_id' => $scopedRole->id,
            'permission_id' => $view->id,
            'scope_type' => 'space',
            'scope_id' => $spaceA->id,
            'denied' => false,
        ]);
        $group->roles()->attach($scopedRole->id, ['tenant_id' => $tenant->id]);
        $user->groups()->attach($group->id, ['tenant_id' => $tenant->id]);

        // CA-RBAC4: group roles respect scopes exactly like direct roles.
        $this->actingAsUser($user);
        $service = app(PermissionService::class);
        $this->assertTrue($service->can($user->fresh(), 'documents.view', $docA));
        $this->assertFalse($service->can($user->fresh(), 'documents.view', $docB));
    }
}
