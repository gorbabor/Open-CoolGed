<?php

namespace Tests\Feature;

use App\Mail\NotificationEmail;
use App\Models\PlatformSettings;
use App\Services\MailSettingsService;
use App\Services\NotificationService;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class DisplayNameAndMailSettingsTest extends TestCase
{
    public function test_default_app_name_is_open_coolged(): void
    {
        $this->assertSame('Open-CoolGed', app_display_name());
    }

    public function test_platform_settings_override_app_name(): void
    {
        PlatformSettings::instance()->set(['app_name' => 'Kaenergie GED']);

        $this->assertSame('Kaenergie GED', app_display_name());
    }

    public function test_tenant_brand_name_overrides_platform_name(): void
    {
        PlatformSettings::instance()->set(['app_name' => 'Open-CoolGed']);

        $tenant = $this->makeTenant();
        $tenant->update(['branding' => ['brand_name' => 'Entreprise Démo SA']]);

        $this->actingAsUser($this->makeUser($tenant, 'user'));

        $this->assertSame('Entreprise Démo SA', app_display_name());
    }

    public function test_tenant_without_brand_name_inherits_platform_name(): void
    {
        PlatformSettings::instance()->set(['app_name' => 'Open-CoolGed']);

        $tenant = $this->makeTenant();
        $tenant->update(['branding' => ['color' => '#123456']]);

        $this->actingAsUser($this->makeUser($tenant, 'user'));

        $this->assertSame('Open-CoolGed', app_display_name());
    }

    public function test_mail_settings_save_encrypts_password(): void
    {
        $tenant = $this->makeTenant();
        $user = $this->makeUser($tenant, 'tenant_admin');
        $this->actingAsUser($user);

        $this->post(route('admin.settings.update'), [
            'mail_enabled' => '1',
            'smtp_host' => 'smtp.example.com',
            'smtp_port' => '587',
            'smtp_username' => 'user@example.com',
            'smtp_password' => 'super-secret',
            'smtp_from_address' => 'no-reply@example.com',
            'smtp_from_name' => 'Entreprise Démo',
        ])->assertRedirect();

        $tenant->refresh();
        $settings = $tenant->settings;

        $this->assertTrue($settings['mail_enabled']);
        $this->assertSame('smtp.example.com', $settings['smtp_host']);
        $this->assertNotSame('super-secret', $settings['smtp_password']);
        $this->assertSame('super-secret', Crypt::decryptString($settings['smtp_password']));
    }

    public function test_mail_settings_service_disables_mailer_when_disabled(): void
    {
        $tenant = $this->makeTenant();
        $service = app(MailSettingsService::class);

        $this->assertFalse($service->enabled($tenant));
    }

    public function test_mail_settings_service_enabled_with_config(): void
    {
        $tenant = $this->makeTenant();
        $tenant->update([
            'settings' => array_merge($tenant->settings, [
                'mail_enabled' => true,
                'smtp_host' => 'smtp.example.com',
                'smtp_port' => 587,
                'smtp_username' => 'user@example.com',
                'smtp_password' => Crypt::encryptString('secret'),
                'smtp_from_address' => 'no-reply@example.com',
                'smtp_from_name' => 'Entreprise Démo',
            ]),
        ]);

        $service = app(MailSettingsService::class);

        $this->assertTrue($service->enabled($tenant->fresh()));
        $this->assertSame('smtp.example.com', $service->configure($tenant->fresh()));
    }

    public function test_notification_email_is_sent_when_mail_enabled(): void
    {
        Mail::fake();

        $tenant = $this->makeTenant();
        $tenant->update([
            'settings' => array_merge($tenant->settings, [
                'mail_enabled' => true,
                'email_notifications' => true,
                'smtp_host' => 'smtp.example.com',
                'smtp_port' => 587,
                'smtp_username' => 'user@example.com',
                'smtp_password' => Crypt::encryptString('secret'),
                'smtp_from_address' => 'no-reply@example.com',
                'smtp_from_name' => 'Entreprise Démo',
            ]),
        ]);

        $user = $this->makeUser($tenant, 'user');
        TenantContext::set($tenant->id);

        app(NotificationService::class)->send($user, 'workflow.task', 'Tâche à traiter', 'Contenu', '/tasks');

        Mail::assertSent(NotificationEmail::class, fn ($mail) => $mail->hasTo($user->email));
    }

    public function test_notification_email_not_sent_when_mail_disabled(): void
    {
        Mail::fake();

        $tenant = $this->makeTenant();
        $user = $this->makeUser($tenant, 'user');
        TenantContext::set($tenant->id);

        app(NotificationService::class)->send($user, 'workflow.task', 'Tâche', 'Contenu', '/tasks');

        Mail::assertNothingSent();
    }

    public function test_test_mail_route_requires_admin_and_returns_success(): void
    {
        Mail::fake();

        $tenant = $this->makeTenant();
        $tenant->update([
            'settings' => array_merge($tenant->settings, [
                'mail_enabled' => true,
                'smtp_host' => 'smtp.example.com',
                'smtp_port' => 587,
                'smtp_username' => 'user@example.com',
                'smtp_password' => Crypt::encryptString('secret'),
                'smtp_from_address' => 'no-reply@example.com',
                'smtp_from_name' => 'Entreprise Démo',
            ]),
        ]);

        $user = $this->makeUser($tenant, 'tenant_admin');
        $this->actingAsUser($user);

        $this->post(route('admin.settings.test-mail'))->assertRedirect();

        Mail::assertSent(NotificationEmail::class, fn ($mail) => $mail->hasTo($user->email));
    }

    public function test_tenant_brand_name_persisted_via_branding(): void
    {
        $tenant = $this->makeTenant();
        $user = $this->makeUser($tenant, 'tenant_admin');
        $this->actingAsUser($user);

        $this->post(route('admin.settings.update'), [
            'brand_name' => 'Mon Entreprise SA',
            'brand_color_custom' => '1',
            'brand_color' => '#123456',
        ])->assertRedirect();

        $tenant->refresh();
        $this->assertSame('Mon Entreprise SA', $tenant->branding['brand_name']);
        $this->assertSame('#123456', $tenant->branding['color']);
    }
}
