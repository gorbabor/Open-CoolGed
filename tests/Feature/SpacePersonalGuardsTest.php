<?php

namespace Tests\Feature;

use App\Services\PersonalSpaceService;
use Tests\TestCase;

class SpacePersonalGuardsTest extends TestCase
{
    public function test_spaces_page_excludes_personal_spaces(): void
    {
        $tenant = $this->makeTenant();
        $user = $this->makeUser($tenant, 'user');
        $personal = app(PersonalSpaceService::class)->ensure($user);
        $shared = $this->makeSpace($tenant, 'Partagé');

        foreach (['user', 'tenant_admin'] as $role) {
            $actor = $role === 'user' ? $user : $this->makeUser($tenant, 'tenant_admin');
            $this->actingAsUser($actor);
            $html = $this->get(route('spaces.index'))->assertOk()->getContent();
            $this->assertStringContainsString($shared->name, $html);
            $this->assertStringNotContainsString($personal->name, $html);
        }
    }

    public function test_admin_cannot_rename_or_delete_a_personal_space(): void
    {
        $tenant = $this->makeTenant();
        $user = $this->makeUser($tenant, 'user');
        $personal = app(PersonalSpaceService::class)->ensure($user);
        $admin = $this->makeUser($tenant, 'tenant_admin');
        $this->actingAsUser($admin);

        $this->post(route('spaces.rename', $personal), ['name' => 'Espace public détourné'])->assertStatus(403);
        $this->delete(route('spaces.delete', $personal))->assertStatus(403);

        $personal->refresh();
        $this->assertSame('Personnel — '.$user->name, $personal->name);
        $this->assertDatabaseHas('spaces', ['id' => $personal->id]);
    }
}
