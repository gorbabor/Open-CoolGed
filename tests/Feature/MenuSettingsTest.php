<?php

namespace Tests\Feature;

use Tests\TestCase;

class MenuSettingsTest extends TestCase
{
    public function test_sidebar_uses_default_menu_labels_and_order(): void
    {
        $tenant = $this->makeTenant();
        $user = $this->makeUser($tenant, 'user');
        $this->actingAsUser($user);

        $html = $this->get(route('dashboard'))->assertOk()->getContent();
        $this->assertStringContainsString('Tableau de bord', $html);
        $this->assertStringContainsString('Mes documents personnels', $html);
        $this->assertTrue(strpos($html, 'Tableau de bord') < strpos($html, 'Mes documents personnels'));
    }

    public function test_tenant_admin_can_rename_and_reorder_menus(): void
    {
        $tenant = $this->makeTenant();
        $admin = $this->makeUser($tenant, 'tenant_admin');
        $user = $this->makeUser($tenant, 'user');
        $this->actingAsUser($admin);

        $this->post(route('admin.settings.update'), [
            'menu_labels' => ['documents' => 'GED', 'spaces' => 'Sections', 'administration' => 'Gestion'],
            'menu_order' => ['documents' => 1, 'spaces' => 2, 'dashboard' => 3],
        ])->assertRedirect();

        $settings = $tenant->fresh()->settings;
        $this->assertSame('GED', $settings['menu']['labels']['documents']);
        $this->assertSame(['documents', 'spaces', 'dashboard'], array_slice($settings['menu']['order'], 0, 3));

        $this->actingAsUser($user);
        $html = $this->get(route('dashboard'))->assertOk()->getContent();
        $this->assertStringContainsString('GED', $html);
        $this->assertStringContainsString('Sections', $html);
        $posGed = strpos($html, 'GED');
        $posSections = strpos($html, 'Sections');
        $this->assertTrue($posGed < $posSections, 'Ordre personnalisé appliqué (GED avant Sections)');
        $posDashboardLink = strpos($html, route('dashboard').'"');
        $this->assertNotFalse($posDashboardLink);
        $this->assertTrue($posSections < $posDashboardLink, 'Tableau de bord déplacé après Sections');

        // Le libellé du groupe Administration n'apparaît que pour un profil qui le voit.
        $this->actingAsUser($admin);
        $adminHtml = $this->get(route('dashboard'))->assertOk()->getContent();
        $this->assertStringContainsString('Gestion', $adminHtml);
    }

    public function test_empty_labels_fall_back_to_defaults_and_unknown_keys_are_ignored(): void
    {
        $tenant = $this->makeTenant();
        $admin = $this->makeUser($tenant, 'tenant_admin');
        $this->actingAsUser($admin);

        $this->post(route('admin.settings.update'), [
            'menu_labels' => ['documents' => '', 'inconnu' => 'Hack'],
            'menu_order' => ['inconnu' => 1],
        ])->assertRedirect();

        $settings = $tenant->fresh()->settings;
        $this->assertArrayNotHasKey('inconnu', $settings['menu']['labels'] ?? []);
        $this->assertSame([], $settings['menu']['order'] ?? []);

        $html = $this->get(route('dashboard'))->assertOk()->getContent();
        $this->assertStringContainsString('Documents', $html);
    }

    public function test_settings_page_shows_menu_tab_and_current_values(): void
    {
        $tenant = $this->makeTenant();
        $admin = $this->makeUser($tenant, 'tenant_admin');
        $this->actingAsUser($admin);

        $this->post(route('admin.settings.update'), [
            'menu_labels' => ['documents' => 'GED'],
        ])->assertRedirect();

        $html = $this->get(route('admin.settings'))->assertOk()->getContent();
        $this->assertStringContainsString('id="tab-menu"', $html);
        $this->assertStringContainsString('name="menu_labels[documents]"', $html);
        $this->assertStringContainsString('value="GED"', $html);
        $this->assertStringContainsString('name="menu_order[documents]"', $html);
    }
}
