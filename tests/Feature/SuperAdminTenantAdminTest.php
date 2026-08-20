<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Services\SystemRoleService;
use Tests\TestCase;

class SuperAdminTenantAdminTest extends TestCase
{
    private function makeSuperAdmin(): User
    {
        return User::create([
            'name' => 'Super Admin Test',
            'email' => 'super-'.uniqid().'@kaeged.local',
            'password' => 'superadmin123',
            'is_super_admin' => true,
            'status' => 'active',
        ]);
    }

    public function test_tenant_can_be_created_with_initial_admin(): void
    {
        $super = $this->makeSuperAdmin();
        $this->actingAs($super);

        $this->post(route('superadmin.tenants.store'), [
            'name' => 'Entreprise Test',
            'admin_name' => 'Admin Test',
            'admin_email' => 'admin-test-'.uniqid().'@entreprise.com',
            'admin_password' => 'motdepasse123',
        ])->assertRedirect()->assertSessionHas('success');

        $tenant = Tenant::withoutGlobalScopes()->where('name', 'Entreprise Test')->first();
        $this->assertNotNull($tenant);

        $admin = User::withoutGlobalScopes()->where('tenant_id', $tenant->id)->first();
        $this->assertNotNull($admin);
        $this->assertSame('active', $admin->status);

        // L'admin initial porte le rôle tenant_admin du tenant (pivot user_role).
        $roles = app(SystemRoleService::class)->ensureFor($tenant);
        $this->assertDatabaseHas('user_role', [
            'user_id' => $admin->id,
            'role_id' => $roles['tenant_admin']->id,
            'tenant_id' => $tenant->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'superadmin.tenant_admin.created',
            'user_id' => $super->id,
            'resource_type' => 'user',
        ]);
    }

    public function test_tenant_without_admin_keeps_previous_behaviour(): void
    {
        $super = $this->makeSuperAdmin();
        $this->actingAs($super);

        $this->post(route('superadmin.tenants.store'), ['name' => 'Entreprise Seule'])
            ->assertRedirect()->assertSessionHas('success');

        $tenant = Tenant::withoutGlobalScopes()->where('name', 'Entreprise Seule')->first();
        $this->assertNotNull($tenant);
        $this->assertSame(0, $tenant->users()->count());
    }

    public function test_admin_can_be_attached_to_existing_tenant(): void
    {
        $super = $this->makeSuperAdmin();
        $tenant = $this->makeTenant();
        $this->actingAs($super);

        $this->post(route('superadmin.tenants.admin', $tenant), [
            'name' => 'Admin Rattaché',
            'email' => 'rattache-'.uniqid().'@entreprise.com',
            'password' => 'motdepasse123',
        ])->assertRedirect()->assertSessionHas('success');

        $admin = User::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('email', 'like', 'rattache-%')->first();
        $this->assertNotNull($admin);

        $roles = app(SystemRoleService::class)->ensureFor($tenant);
        $this->assertDatabaseHas('user_role', [
            'user_id' => $admin->id,
            'role_id' => $roles['tenant_admin']->id,
            'tenant_id' => $tenant->id,
        ]);

        $this->assertDatabaseHas('audit_logs', ['action' => 'superadmin.tenant_admin.created', 'user_id' => $super->id]);
    }

    public function test_duplicate_email_is_rejected(): void
    {
        $super = $this->makeSuperAdmin();
        $this->actingAs($super);

        $this->post(route('superadmin.tenants.store'), [
            'name' => 'Entreprise Dup',
            'admin_name' => 'Admin',
            'admin_email' => $super->email,
            'admin_password' => 'motdepasse123',
        ])->assertSessionHasErrors('admin_email');

        $this->post(route('superadmin.tenants.admin', $this->makeTenant()), [
            'name' => 'Admin',
            'email' => $super->email,
            'password' => 'motdepasse123',
        ])->assertSessionHasErrors('email');
    }

    public function test_short_password_is_rejected(): void
    {
        $super = $this->makeSuperAdmin();
        $tenant = $this->makeTenant();
        $this->actingAs($super);

        $this->post(route('superadmin.tenants.store'), [
            'name' => 'Entreprise Mdp',
            'admin_name' => 'Admin',
            'admin_email' => 'mdp-'.uniqid().'@x.com',
            'admin_password' => 'court',
        ])->assertSessionHasErrors('admin_password');

        $this->post(route('superadmin.tenants.admin', $tenant), [
            'name' => 'Admin',
            'email' => 'mdp2-'.uniqid().'@x.com',
            'password' => 'court',
        ])->assertSessionHasErrors('password');
    }

    public function test_missing_admin_fields_are_rejected_when_partial(): void
    {
        $super = $this->makeSuperAdmin();
        $this->actingAs($super);

        // Email fourni sans nom ni mot de passe → erreur sur admin_name (required_with).
        $this->post(route('superadmin.tenants.store'), [
            'name' => 'Entreprise Partiel',
            'admin_email' => 'partiel-'.uniqid().'@x.com',
        ])->assertSessionHasErrors('admin_name');
    }

    public function test_attaching_admin_to_missing_tenant_returns_404(): void
    {
        $super = $this->makeSuperAdmin();
        $this->actingAs($super);

        $this->post(route('superadmin.tenants.admin', 999999), [
            'name' => 'Admin',
            'email' => 'ghost-'.uniqid().'@x.com',
            'password' => 'motdepasse123',
        ])->assertNotFound();
    }
}
