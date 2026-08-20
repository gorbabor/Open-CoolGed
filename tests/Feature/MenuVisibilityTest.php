<?php

namespace Tests\Feature;

use Tests\TestCase;

class MenuVisibilityTest extends TestCase
{
    public function test_admin_menu_is_hidden_for_user_without_admin_permissions(): void
    {
        $tenant = $this->makeTenant();
        $space = $this->makeSpace($tenant);
        $user = $this->makeUser($tenant, 'user');
        $this->makeDocument($tenant, $user, $space);

        $this->actingAsUser($user);
        $html = $this->get(route('dashboard'))->getContent();

        $this->assertStringNotContainsString('Administration', $html);
        $this->assertStringNotContainsString('/admin/users', $html);
        $this->assertStringNotContainsString('/admin/settings', $html);
        $this->assertStringNotContainsString('/admin/referentials', $html);
    }

    public function test_admin_menu_is_visible_for_tenant_admin(): void
    {
        $tenant = $this->makeTenant();
        $space = $this->makeSpace($tenant);
        $admin = $this->makeUser($tenant, 'tenant_admin');
        $this->makeDocument($tenant, $admin, $space);

        $this->actingAsUser($admin);
        $html = $this->get(route('dashboard'))->getContent();

        $this->assertStringContainsString('Administration', $html);
        $this->assertStringContainsString('/admin/users', $html);
        $this->assertStringContainsString('/admin/referentials', $html);
        $this->assertStringContainsString('/admin/settings', $html);
    }

    public function test_admin_menu_shows_only_permitted_submenus(): void
    {
        $tenant = $this->makeTenant();
        $space = $this->makeSpace($tenant);
        $auditor = $this->makeUser($tenant, 'auditor');
        $this->makeDocument($tenant, $auditor, $space);

        $this->actingAsUser($auditor);
        $html = $this->get(route('dashboard'))->getContent();

        // Le rôle auditor n'a que admin.audit : le parent est visible, les autres sous-menus non.
        $this->assertStringContainsString('Administration', $html);
        $this->assertStringContainsString('/admin/audit', $html);
        $this->assertStringNotContainsString('/admin/users', $html);
        $this->assertStringNotContainsString('/admin/settings', $html);
    }
}
