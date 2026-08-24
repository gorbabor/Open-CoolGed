<?php

namespace Tests\Feature;

use App\Models\PlatformSettings;
use App\Themes\ThemeRegistry;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ThemesAndProfileTest extends TestCase
{
    public function test_registry_exposes_twelve_themes_with_light_and_dark(): void
    {
        $registry = app(ThemeRegistry::class);

        $themes = $registry->all();
        $this->assertCount(12, $themes);
        $this->assertArrayHasKey('kami', $themes);
        $this->assertArrayHasKey('light', $themes['kami']['palettes']);
        $this->assertArrayHasKey('dark', $themes['kami']['palettes']);
        $this->assertArrayHasKey('font', $themes['kami']);
        $this->assertArrayHasKey('radius', $themes['kami']);
    }

    public function test_default_theme_is_ocean(): void
    {
        $registry = app(ThemeRegistry::class);
        $this->assertSame('ocean', $registry->resolveTheme());
        $this->assertSame('auto', $registry->resolveMode());
    }

    public function test_platform_theme_is_used_as_fallback(): void
    {
        PlatformSettings::instance()->set(['theme' => 'forest', 'theme_mode' => 'dark']);

        $registry = app(ThemeRegistry::class);
        $this->assertSame('forest', $registry->resolveTheme());
        $this->assertSame('dark', $registry->resolveMode());
    }

    public function test_tenant_theme_overrides_platform(): void
    {
        PlatformSettings::instance()->set(['theme' => 'ocean']);

        $tenant = $this->makeTenant();
        $tenant->update(['branding' => ['theme' => 'forest']]);
        $user = $this->makeUser($tenant, 'user');
        $this->actingAsUser($user);

        $registry = app(ThemeRegistry::class);
        $this->assertSame('forest', $registry->resolveTheme());
    }

    public function test_user_theme_mode_overrides_tenant_mode(): void
    {
        $tenant = $this->makeTenant();
        $tenant->update(['branding' => ['theme_mode' => 'light']]);
        $user = $this->makeUser($tenant, 'user', ['theme_mode' => 'dark']);
        $this->actingAsUser($user);

        $registry = app(ThemeRegistry::class);
        $this->assertSame('dark', $registry->resolveMode());
    }

    public function test_user_can_update_theme_mode_from_profile(): void
    {
        $tenant = $this->makeTenant();
        $user = $this->makeUser($tenant, 'user');
        $this->actingAsUser($user);

        $this->post(route('profile.theme-mode'), ['theme_mode' => 'dark'])->assertRedirect();

        $this->assertSame('dark', $user->fresh()->theme_mode);
    }

    public function test_user_can_update_personal_theme_from_profile(): void
    {
        $tenant = $this->makeTenant();
        $user = $this->makeUser($tenant, 'user');
        $this->actingAsUser($user);

        $this->post(route('profile.theme'), ['theme' => 'ocean'])->assertRedirect();
        $this->assertSame('ocean', $user->fresh()->theme);

        $this->post(route('profile.theme'), ['theme' => ''])->assertRedirect();
        $this->assertNull($user->fresh()->theme);
    }

    public function test_user_theme_overrides_tenant_theme(): void
    {
        $tenant = $this->makeTenant();
        $tenant->update(['branding' => ['theme' => 'forest']]);
        $user = $this->makeUser($tenant, 'user', ['theme' => 'ocean']);
        $this->actingAsUser($user);

        $registry = app(ThemeRegistry::class);
        $this->assertSame('ocean', $registry->resolveTheme());
    }

    public function test_user_can_change_password_with_current_password(): void
    {
        $tenant = $this->makeTenant();
        $user = $this->makeUser($tenant, 'user');
        $this->actingAsUser($user);

        $this->post(route('profile.update-password'), [
            'current_password' => 'password123',
            'password' => 'nouveau-mot-de-passe-123',
            'password_confirmation' => 'nouveau-mot-de-passe-123',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('nouveau-mot-de-passe-123', $user->fresh()->password));
    }

    public function test_password_change_requires_correct_current_password(): void
    {
        $tenant = $this->makeTenant();
        $user = $this->makeUser($tenant, 'user');
        $this->actingAsUser($user);

        $this->post(route('profile.update-password'), [
            'current_password' => 'mauvais-mdp',
            'password' => 'nouveau-mot-de-passe-123',
            'password_confirmation' => 'nouveau-mot-de-passe-123',
        ])->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('password123', $user->fresh()->password));
    }

    public function test_password_change_respects_tenant_min_length(): void
    {
        $tenant = $this->makeTenant();
        $tenant->update(['settings' => array_merge($tenant->settings, ['password_min_length' => 12])]);
        $user = $this->makeUser($tenant, 'user');
        $this->actingAsUser($user);

        $this->post(route('profile.update-password'), [
            'current_password' => 'password123',
            'password' => 'court',
            'password_confirmation' => 'court',
        ])->assertSessionHasErrors('password');
    }

    public function test_theme_mode_validates_enum(): void
    {
        $tenant = $this->makeTenant();
        $user = $this->makeUser($tenant, 'user');
        $this->actingAsUser($user);

        $this->post(route('profile.theme-mode'), ['theme_mode' => 'bleu'])->assertSessionHasErrors('theme_mode');
    }

    public function test_theme_validates_known_slug(): void
    {
        $tenant = $this->makeTenant();
        $user = $this->makeUser($tenant, 'user');
        $this->actingAsUser($user);

        $this->post(route('profile.theme'), ['theme' => 'inconnu'])->assertSessionHasErrors('theme');
    }

    public function test_brand_color_only_saved_when_custom_checked(): void
    {
        $tenant = $this->makeTenant();
        $user = $this->makeUser($tenant, 'tenant_admin');
        $this->actingAsUser($user);

        $this->post(route('admin.settings.update'), [
            'theme' => 'ocean',
            'brand_color' => '#ff0000',
        ])->assertRedirect();

        $tenant->refresh();
        $this->assertSame('ocean', $tenant->branding['theme']);
        $this->assertNull($tenant->branding['color']);
    }

    public function test_dark_mode_overrides_bootstrap_text_colors(): void
    {
        $response = $this->get(route('login'));

        $html = $response->getContent();

        // Les classes Bootstrap fixes doivent être surchargées par les variables du thème.
        $this->assertStringContainsString('.text-muted { color: var(--color-muted) !important; }', $html);
        $this->assertStringContainsString('.text-dark { color: var(--color-foreground) !important; }', $html);
        $this->assertStringContainsString('.table-light, .table-light th, .table-light td { background-color: var(--color-surface); color: var(--color-foreground); }', $html);
        $this->assertStringContainsString('.bg-light { background-color: var(--color-surface) !important; color: var(--color-foreground); }', $html);
        $this->assertStringContainsString('.bg-white { background-color: var(--color-surface) !important; color: var(--color-foreground); }', $html);

        // Formulaires : texte des champs/selects/options/labels/cases à cocher lisibles en sombre.
        $this->assertStringContainsString('.form-control, .form-select { border-radius: var(--radius); background: var(--color-background); color: var(--color-foreground);', $html);
        $this->assertStringContainsString('.form-select option { background-color: var(--color-surface); color: var(--color-foreground); }', $html);
        $this->assertStringContainsString('.form-control:focus, .form-select:focus { border-color: var(--color-accent); box-shadow: 0 0 0 .2rem rgba(var(--color-accent-rgb), .1); color: var(--color-foreground); background-color: var(--color-background); }', $html);
        $this->assertStringContainsString('label, .form-label, .form-check-label, .form-text { color: var(--color-foreground); }', $html);
        $this->assertStringContainsString('.form-check-input { background-color: var(--color-background); border-color: var(--color-muted); }', $html);
    }

    public function test_new_tenant_defaults_to_platform_theme(): void
    {
        PlatformSettings::instance()->set(['theme' => 'ocean']);
        $tenant = $this->makeTenant();

        // Sans branding.theme, la résolution retombe sur le thème plateforme (ocean).
        $this->assertNull($tenant->branding['theme'] ?? null);
    }
}
