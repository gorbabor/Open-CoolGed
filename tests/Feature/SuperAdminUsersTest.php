<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class SuperAdminUsersTest extends TestCase
{
    private function makeSuperAdmin(string $suffix = ''): User
    {
        return User::create([
            'name' => 'Super '.$suffix,
            'email' => 'super-'.uniqid().'-'.$suffix.'@kaeged.local',
            'password' => 'superadmin123',
            'is_super_admin' => true,
            'status' => 'active',
        ]);
    }

    public function test_super_admins_list_is_displayed(): void
    {
        $super = $this->makeSuperAdmin('A');
        $other = $this->makeSuperAdmin('B');
        $this->actingAs($super);

        $html = $this->get(route('superadmin.tenants'))->getContent();

        $this->assertStringContainsString('Super administrateurs', $html);
        $this->assertStringContainsString($super->name, $html);
        $this->assertStringContainsString($other->name, $html);
        $this->assertStringContainsString($other->email, $html);
    }

    public function test_super_admin_can_be_suspended_and_reactivated(): void
    {
        $super = $this->makeSuperAdmin('A');
        $target = $this->makeSuperAdmin('Cible');
        $this->actingAs($super);

        $this->post(route('superadmin.users.toggle', $target))
            ->assertRedirect()
            ->assertSessionHas('success');

        $target->refresh();
        $this->assertTrue($target->isSuspended());
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'superadmin.user.toggled',
            'user_id' => $super->id,
            'resource_type' => 'user',
            'resource_id' => $target->id,
        ]);

        $this->post(route('superadmin.users.toggle', $target))
            ->assertRedirect()
            ->assertSessionHas('success');
        $target->refresh();
        $this->assertTrue($target->isActive());
    }

    public function test_non_super_admin_user_returns_404(): void
    {
        $super = $this->makeSuperAdmin('A');
        $tenant = $this->makeTenant();
        $user = $this->makeUser($tenant, 'user');
        $this->actingAs($super);

        $this->post(route('superadmin.users.toggle', $user))->assertNotFound();
    }

    public function test_last_active_super_admin_cannot_be_suspended(): void
    {
        $super = $this->makeSuperAdmin('Seul');
        $this->actingAs($super);

        $this->post(route('superadmin.users.toggle', $super))
            ->assertRedirect()
            ->assertSessionHasErrors('superadmin');

        $super->refresh();
        $this->assertTrue($super->isActive());
        $this->assertDatabaseMissing('audit_logs', ['action' => 'superadmin.user.toggled']);
    }

    public function test_suspended_super_admin_cannot_login(): void
    {
        $super = $this->makeSuperAdmin('A');
        $target = $this->makeSuperAdmin('Bloqué');
        $target->update(['status' => 'suspended']);

        $this->post(route('login'), [
            'email' => $target->email,
            'password' => 'superadmin123',
        ])->assertSessionHasErrors('email');
    }
}
