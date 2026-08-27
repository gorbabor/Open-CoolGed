<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Referential;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\Tenant;
use App\Services\SystemRoleService;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class RolesSyncAndSuperAdminNavTest extends TestCase
{
    public function test_roles_sync_adds_missing_permissions_to_existing_roles(): void
    {
        SystemRoleService::seedPermissions();

        // Tenant créé : rôle tenant_admin SANS la permission admin.referentials
        // (simule un tenant créé avant l'ajout de la permission au ROLE_DEFS).
        $tenant = $this->makeTenant();
        $role = Role::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('slug', 'tenant_admin')->first();
        $perm = Permission::where('slug', 'admin.referentials')->first();
        RolePermission::where('role_id', $role->id)->where('permission_id', $perm->id)->delete();

        $this->assertFalse($role->permissions()->where('permission_id', $perm->id)->exists(), 'précondition : permission absente');

        $exit = Artisan::call('roles:sync');
        $this->assertSame(0, $exit);

        $role->refresh();
        $this->assertTrue(
            $role->permissions()->where('permission_id', $perm->id)->exists(),
            'roles:sync doit ré-ajouter admin.referentials au rôle existant'
        );
    }

    public function test_roles_sync_is_idempotent(): void
    {
        $tenant = $this->makeTenant();
        $role = Role::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('slug', 'tenant_admin')->first();
        $before = $role->permissions()->count();

        Artisan::call('roles:sync');
        Artisan::call('roles:sync');

        $role->refresh();
        $this->assertSame($before, $role->permissions()->count(), 'aucune duplication au second passage');
    }

    public function test_roles_sync_handles_all_tenants(): void
    {
        $tenantA = $this->makeTenant();
        $tenantB = $this->makeTenant();

        foreach ([$tenantA, $tenantB] as $tenant) {
            $role = Role::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('slug', 'manager')->first();
            $perm = Permission::where('slug', 'documents.archive')->first();
            RolePermission::where('role_id', $role->id)->where('permission_id', $perm->id)->delete();
        }

        Artisan::call('roles:sync');

        foreach ([$tenantA, $tenantB] as $tenant) {
            $role = Role::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('slug', 'manager')->first();
            $this->assertTrue(
                $role->permissions()->where('permission_id', Permission::where('slug', 'documents.archive')->first()->id)->exists(),
                "tenant {$tenant->id} : permission restaurée"
            );
        }
    }

    public function test_super_admin_sidebar_shows_admin_tenant_links(): void
    {
        $tenant = $this->makeTenant();
        $sa = $this->makeUser($tenant, 'user', ['is_super_admin' => true]);
        $this->actingAsUser($sa);

        $response = $this->get(route('superadmin.tenants'));
        $response->assertOk();
        $html = $response->getContent();

        // Le super admin doit voir des liens vers les écrans admin du tenant.
        $this->assertStringContainsString('Référentiels', $html);
        $this->assertStringContainsString('Utilisateurs', $html);
        $this->assertStringContainsString('Import CSV', $html);
        $this->assertStringContainsString(route('admin.referentials'), $html);
    }

    public function test_super_admin_can_create_referential_via_ui(): void
    {
        $tenant = $this->makeTenant();
        $sa = $this->makeUser($tenant, 'user', ['is_super_admin' => true]);
        $this->actingAsUser($sa);

        $this->post(route('admin.referentials.store'), [
            'type' => 'domain',
            'name' => 'Super Admin QMS',
            'code' => 'SA-QMS',
        ])->assertRedirect();

        $this->assertDatabaseHas('referentials', [
            'tenant_id' => $tenant->id,
            'type' => 'domain',
            'name' => 'Super Admin QMS',
        ]);
    }

    public function test_super_admin_can_update_referential_via_ui(): void
    {
        $tenant = $this->makeTenant();
        $sa = $this->makeUser($tenant, 'user', ['is_super_admin' => true]);
        $this->actingAsUser($sa);

        $ref = Referential::create(['tenant_id' => $tenant->id, 'type' => 'site', 'name' => 'Site Siège']);

        $this->post(route('admin.referentials.update', $ref), [
            'name' => 'Site Siège 2',
            'code' => 'SS2',
        ])->assertRedirect();

        $this->assertDatabaseHas('referentials', ['id' => $ref->id, 'name' => 'Site Siège 2']);
    }
}
