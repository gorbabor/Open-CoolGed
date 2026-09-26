<?php

namespace Tests\Feature;

use App\Services\MfaService;
use Tests\TestCase;

class MenuVisibilityAndStartupTest extends TestCase
{
    private function adminForm(array $visible, ?string $startup = null): array
    {
        return [
            'menu_form' => '1',
            'menu_visible' => array_fill_keys($visible, '1'),
            'menu_startup' => $startup ?? '',
        ];
    }

    public function test_tenant_admin_can_hide_menus_and_set_the_startup_menu(): void
    {
        $tenant = $this->makeTenant();
        $admin = $this->makeUser($tenant, 'tenant_admin');
        $user = $this->makeUser($tenant, 'user');
        $this->actingAsUser($admin);

        $this->post(route('admin.settings.update'), $this->adminForm(
            ['dashboard', 'documents', 'spaces'],
            'documents'
        ))->assertRedirect()->assertSessionHasNoErrors();

        $settings = $tenant->fresh()->settings;
        $this->assertSame('documents', $settings['menu']['startup']);
        $this->assertNotContains('documents', $settings['menu']['hidden']);
        $this->assertContains('search', $settings['menu']['hidden']);
        $this->assertContains('notifications', $settings['menu']['hidden']);

        $this->actingAsUser($user);
        $html = $this->get(route('dashboard'))->assertOk()->getContent();
        $this->assertStringContainsString('bi bi-files', $html);
        $this->assertStringNotContainsString('bi bi-search', $html);
        $this->assertStringNotContainsString('bi bi-bell', $html);
    }

    public function test_admin_visibility_requires_at_least_one_menu_and_a_visible_startup(): void
    {
        $tenant = $this->makeTenant();
        $admin = $this->makeUser($tenant, 'tenant_admin');
        $this->actingAsUser($admin);

        $this->post(route('admin.settings.update'), [
            'menu_form' => '1',
            'menu_visible' => [],
            'menu_startup' => '',
        ])->assertSessionHasErrors('menu_visible');

        $this->post(route('admin.settings.update'), $this->adminForm(['dashboard'], 'search'))
            ->assertSessionHasErrors('menu_startup');

        $settings = $tenant->fresh()->settings;
        $this->assertArrayNotHasKey('menu', $settings);
    }

    public function test_user_can_only_restrict_menus_never_reenable_tenant_hidden_ones(): void
    {
        $tenant = $this->makeTenant();
        $admin = $this->makeUser($tenant, 'tenant_admin');
        $user = $this->makeUser($tenant, 'user');
        $this->actingAsUser($admin);

        $this->post(route('admin.settings.update'), $this->adminForm(
            ['dashboard', 'documents', 'personal', 'v02', 'spaces', 'tasks', 'notifications'],
            'spaces'
        ))->assertSessionHasNoErrors();

        $this->actingAsUser($user);
        $this->post(route('profile.menu-prefs'), [
            'menu_visible' => ['dashboard' => '1', 'documents' => '1', 'search' => '1', 'workflows' => '1'],
            'menu_startup' => 'documents',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $user->refresh();
        // Les clés masquées par l'organisation ne peuvent pas être réactivées.
        $this->assertNotContains('search', $user->menu_hidden);
        $this->assertNotContains('workflows', $user->menu_hidden);
        $this->assertContains('personal', $user->menu_hidden);
        $this->assertContains('tasks', $user->menu_hidden);

        $html = $this->get(route('dashboard'))->assertOk()->getContent();
        $this->assertStringContainsString('bi bi-speedometer2', $html);
        $this->assertStringContainsString('bi bi-files', $html);
        $this->assertStringNotContainsString('bi bi-search', $html);
        $this->assertStringNotContainsString('bi bi-diagram-3', $html);
        $this->assertStringNotContainsString('bi bi-collection', $html);
    }

    public function test_user_visibility_requires_at_least_one_menu_and_a_visible_startup(): void
    {
        $tenant = $this->makeTenant();
        $user = $this->makeUser($tenant, 'user');
        $this->actingAsUser($user);

        $this->post(route('profile.menu-prefs'), ['menu_visible' => [], 'menu_startup' => ''])
            ->assertSessionHasErrors('menu_visible');

        $this->post(route('profile.menu-prefs'), [
            'menu_visible' => ['dashboard' => '1', 'documents' => '1'],
            'menu_startup' => 'search',
        ])->assertSessionHasErrors('menu_startup');

        $user->refresh();
        $this->assertNull($user->menu_hidden);
        $this->assertNull($user->menu_startup);
    }

    public function test_login_redirects_to_the_startup_menu_with_user_override(): void
    {
        $tenant = $this->makeTenant();
        $admin = $this->makeUser($tenant, 'tenant_admin');
        $this->actingAsUser($admin);
        $this->post(route('admin.settings.update'), $this->adminForm(
            ['dashboard', 'documents', 'personal', 'v02', 'spaces', 'search', 'workflows', 'tasks', 'notifications'],
            'spaces'
        ))->assertSessionHasNoErrors();

        $user = $this->makeUser($tenant, 'user');
        $other = $this->makeUser($tenant, 'user', ['menu_startup' => 'documents']);

        $this->post('/logout');

        // Défaut de l'organisation : Espaces.
        $this->post('/login', ['email' => $user->email, 'password' => 'password123'])
            ->assertRedirect(route('spaces.index'));

        $this->post('/logout');

        // Choix personnel : Documents.
        $this->post('/login', ['email' => $other->email, 'password' => 'password123'])
            ->assertRedirect(route('documents.index'));
    }

    public function test_login_falls_back_when_the_startup_menu_is_hidden(): void
    {
        $tenant = $this->makeTenant();
        $admin = $this->makeUser($tenant, 'tenant_admin');
        $this->actingAsUser($admin);
        $this->post(route('admin.settings.update'), $this->adminForm(
            ['dashboard', 'documents', 'personal', 'v02', 'spaces', 'tasks', 'notifications'],
            'spaces'
        ))->assertSessionHasNoErrors();

        // Menu de démarrage utilisateur masqué par l'organisation → repli sur le défaut tenant.
        $user = $this->makeUser($tenant, 'user', ['menu_startup' => 'search']);

        $this->post('/logout');
        $this->post('/login', ['email' => $user->email, 'password' => 'password123'])
            ->assertRedirect(route('spaces.index'));

        // Défaut tenant masqué ensuite → repli tableau de bord.
        $this->actingAsUser($admin);
        $this->post(route('admin.settings.update'), $this->adminForm(['dashboard', 'documents']))
            ->assertSessionHasNoErrors();

        $this->post('/logout');
        $this->post('/login', ['email' => $user->email, 'password' => 'password123'])
            ->assertRedirect(route('dashboard'));
    }

    public function test_mfa_verification_redirects_to_the_startup_menu(): void
    {
        $tenant = $this->makeTenant();
        $secret = app(MfaService::class)->generateSecret();
        $user = $this->makeUser($tenant, 'user', [
            'mfa_enabled' => true,
            'mfa_secret' => $secret,
            'menu_startup' => 'documents',
        ]);

        $this->post('/login', ['email' => $user->email, 'password' => 'password123'])
            ->assertRedirect(route('mfa.verify'));

        $this->post('/mfa/verify', ['code' => app(MfaService::class)->currentCode($secret)])
            ->assertRedirect(route('documents.index'));
    }

    public function test_superadmin_login_still_lands_on_the_tenants_screen(): void
    {
        $tenant = $this->makeTenant();
        $superadmin = $this->makeUser($tenant, 'user', ['is_super_admin' => true]);

        $this->post('/login', ['email' => $superadmin->email, 'password' => 'password123'])
            ->assertRedirect(route('superadmin.tenants'));
    }

    public function test_sidebar_never_ends_up_empty_when_the_organization_hides_user_menus(): void
    {
        $tenant = $this->makeTenant();
        $admin = $this->makeUser($tenant, 'tenant_admin');
        $user = $this->makeUser($tenant, 'user');
        $this->actingAsUser($user);

        $this->post(route('profile.menu-prefs'), ['menu_visible' => ['documents' => '1']])
            ->assertSessionHasNoErrors();

        $this->actingAsUser($admin);
        $this->post(route('admin.settings.update'), $this->adminForm(['tasks'], 'tasks'))
            ->assertSessionHasNoErrors();

        $user->unsetRelation('tenant');
        $this->actingAsUser($user);
        $html = $this->get(route('dashboard'))->assertOk()->getContent();
        $this->assertStringContainsString('bi bi-check2-square', $html);
    }

    public function test_profile_menu_section_only_offers_menus_still_shown_by_the_organization(): void
    {
        $tenant = $this->makeTenant();
        $admin = $this->makeUser($tenant, 'tenant_admin');
        $user = $this->makeUser($tenant, 'user');
        $this->actingAsUser($admin);

        $this->post(route('admin.settings.update'), $this->adminForm(
            ['dashboard', 'documents', 'spaces'],
            'documents'
        ))->assertSessionHasNoErrors();

        $this->actingAsUser($user);
        $html = $this->get(route('profile'))->assertOk()->getContent();

        $this->assertStringContainsString('Menus affichés', $html);
        $this->assertStringContainsString('name="menu_visible[dashboard]"', $html);
        $this->assertStringContainsString('name="menu_visible[documents]"', $html);
        $this->assertStringNotContainsString('name="menu_visible[search]"', $html);
        $this->assertStringNotContainsString('name="menu_visible[notifications]"', $html);
        $this->assertStringContainsString('name="menu_startup"', $html);
        $this->assertStringContainsString('— Selon l\'organisation —', $html);
    }

    public function test_menu_preferences_endpoint_ignores_unknown_keys(): void
    {
        $tenant = $this->makeTenant();
        $user = $this->makeUser($tenant, 'user');
        $this->actingAsUser($user);

        $this->post(route('profile.menu-prefs'), [
            'menu_visible' => ['dashboard' => '1', 'inconnu' => '1'],
            'menu_startup' => 'inconnu',
        ])->assertSessionHasErrors('menu_startup');

        $this->post(route('profile.menu-prefs'), [
            'menu_visible' => ['dashboard' => '1', 'inconnu' => '1'],
        ])->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertNotContains('inconnu', $user->menu_hidden);
        $this->assertContains('documents', $user->menu_hidden);
    }
}
