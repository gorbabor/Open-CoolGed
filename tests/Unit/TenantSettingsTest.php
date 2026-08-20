<?php

namespace Tests\Unit;

use App\Services\TenantSettings;
use Tests\TestCase;

class TenantSettingsTest extends TestCase
{
    public function test_defaults_are_returned_when_unset(): void
    {
        $tenant = $this->makeTenant(['settings' => null]);

        $settings = TenantSettings::for($tenant);

        $this->assertTrue($settings->get('auto_lock_on_edit'));
        $this->assertFalse($settings->get('external_sharing_enabled'));
        $this->assertFalse($settings->get('mfa_required_admin'));
        $this->assertSame(120, $settings->get('session_expiration_minutes'));
        $this->assertSame(['mock'], $settings->get('ai_providers'));
    }

    public function test_set_persists_and_get_returns_value(): void
    {
        $tenant = $this->makeTenant();
        $settings = TenantSettings::for($tenant);

        $settings->set([
            'external_sharing_enabled' => true,
            'external_share_max_days' => 7,
            'mfa_required_admin' => true,
        ]);

        $fresh = TenantSettings::for($tenant->fresh());

        $this->assertTrue($fresh->get('external_sharing_enabled'));
        $this->assertSame(7, $fresh->get('external_share_max_days'));
        $this->assertTrue($fresh->get('mfa_required_admin'));
        $this->assertTrue($fresh->externalSharingEnabled());
    }

    public function test_all_merges_defaults_with_tenant_values(): void
    {
        $tenant = $this->makeTenant();
        TenantSettings::for($tenant)->set(['comment_required' => true]);

        $all = TenantSettings::for($tenant->fresh())->all();

        $this->assertTrue($all['comment_required']);
        $this->assertSame(30, $all['external_share_max_days']);
        $this->assertArrayHasKey('password_min_length', $all);
    }

    public function test_allowed_mimes_can_be_overridden_per_tenant(): void
    {
        $tenant = $this->makeTenant();
        $settings = TenantSettings::for($tenant);

        $defaults = $settings->allowedMimes();
        $this->assertContains('application/pdf', $defaults);
        $this->assertContains('text/markdown', $defaults);

        // Restrict the policy to PDF only.
        $settings->set(['allowed_mimes' => ['application/pdf']]);

        $this->assertSame(['application/pdf'], TenantSettings::for($tenant->fresh())->allowedMimes());
    }

    public function test_string_list_settings_are_normalized_to_arrays(): void
    {
        // Données héritées d'une version antérieure : valeurs stockées en chaîne.
        $tenant = $this->makeTenant(['settings' => [
            'ai_enabled' => true,
            'ai_providers' => 'mock, openai',
            'allowed_mimes' => 'application/pdf,text/plain',
        ]]);

        $settings = TenantSettings::for($tenant);

        $this->assertSame(['mock', 'openai'], $settings->get('ai_providers'));
        $this->assertSame(['application/pdf', 'text/plain'], $settings->get('allowed_mimes'));
        $this->assertSame(['mock', 'openai'], $settings->all()['ai_providers']);
        $this->assertSame(['application/pdf', 'text/plain'], $settings->all()['allowed_mimes']);
    }
}
