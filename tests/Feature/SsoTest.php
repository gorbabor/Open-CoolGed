<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Tests\TestCase;

class SsoTest extends TestCase
{
    public function test_sso_disabled_by_default(): void
    {
        $tenant = $this->makeTenant();
        $user = $this->makeUser($tenant, 'user');

        // Per-tenant flag not enabled: SSO flow refused.
        $this->get(route('sso.start', ['tenant' => $tenant->slug, 'email' => $user->email]))
            ->assertStatus(404);
    }

    public function test_sso_flow_logs_user_into_its_tenant(): void
    {
        config(['ged.sso_enabled' => true]);
        $tenant = $this->makeTenant(['settings' => ['ai_enabled' => true, 'ai_providers' => ['mock'], 'sso_enabled' => true]]);
        $user = $this->makeUser($tenant, 'user');

        $this->get(route('sso.start', ['tenant' => $tenant->slug, 'email' => $user->email]))
            ->assertRedirect(route('sso.callback', ['tenant' => $tenant->slug, 'email' => $user->email]));

        $this->get(route('sso.callback', ['tenant' => $tenant->slug, 'email' => $user->email]))
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_sso_never_authenticates_user_of_another_tenant(): void
    {
        config(['ged.sso_enabled' => true]);
        $tenantA = $this->makeTenant(['settings' => ['sso_enabled' => true, 'ai_enabled' => true, 'ai_providers' => ['mock']]]);
        $tenantB = $this->makeTenant();
        $userB = $this->makeUser($tenantB, 'user');

        $this->get(route('sso.callback', ['tenant' => $tenantA->slug, 'email' => $userB->email]))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
