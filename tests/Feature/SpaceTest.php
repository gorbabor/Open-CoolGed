<?php

namespace Tests\Feature;

use Tests\TestCase;

class SpaceTest extends TestCase
{
    public function test_space_can_be_renamed_and_audited(): void
    {
        $tenant = $this->makeTenant();
        $user = $this->makeUser($tenant, 'user');
        $space = $this->makeSpace($tenant);

        $this->actingAsUser($user);
        $this->post(route('spaces.rename', $space), ['name' => 'Section Gouvernance'])
            ->assertRedirect();

        $this->assertDatabaseHas('spaces', ['id' => $space->id, 'name' => 'Section Gouvernance']);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'space.renamed',
            'resource_type' => 'space',
            'resource_id' => $space->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_space_rename_requires_a_name(): void
    {
        $tenant = $this->makeTenant();
        $user = $this->makeUser($tenant, 'user');
        $space = $this->makeSpace($tenant);

        $this->actingAsUser($user);
        $this->post(route('spaces.rename', $space), ['name' => ''])->assertSessionHasErrors('name');
        $this->assertDatabaseHas('spaces', ['id' => $space->id, 'name' => $space->name]);
    }
}
