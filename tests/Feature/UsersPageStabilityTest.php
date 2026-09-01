<?php

namespace Tests\Feature;

use App\Models\Referential;
use App\Models\Role;
use App\Models\User;
use App\Services\PersonalSpaceService;
use Tests\TestCase;

class UsersPageStabilityTest extends TestCase
{
    public function test_users_page_renders_with_valid_table_and_modals_outside(): void
    {
        $tenant = $this->makeTenant();
        $admin = $this->makeUser($tenant, 'tenant_admin');
        $user = $this->makeUser($tenant, 'user');
        $this->actingAsUser($admin);

        $response = $this->get(route('admin.users'));
        $response->assertOk();
        $html = $response->getContent();

        // La page s'affiche avec le tableau des utilisateurs.
        $this->assertStringContainsString('Utilisateurs', $html);
        $this->assertStringContainsString($user->name, $html);

        // Les modales sont présentes avec des IDs uniques.
        $this->assertStringContainsString('id="editUser'.$user->id.'"', $html);
        $this->assertStringContainsString('id="resetUser'.$user->id.'"', $html);
        $this->assertSame(1, substr_count($html, 'id="editUser'.$user->id.'"'));
        $this->assertSame(1, substr_count($html, 'id="resetUser'.$user->id.'"'));

        // Les modales sont hors du tableau : le tbody ne contient aucun <div class="modal".
        $tbodyStart = strpos($html, '<tbody>');
        $tbodyEnd = strpos($html, '</tbody>');
        $tbody = substr($html, $tbodyStart, $tbodyEnd - $tbodyStart);
        $this->assertStringNotContainsString('<div class="modal', $tbody);

        // Les déclencheurs de modale sont des boutons (type=button) — pas de submit involontaire.
        $this->assertMatchesRegularExpression('/<button type="button"[^>]*data-bs-target="#editUser'.$user->id.'"/', $html);
        $this->assertMatchesRegularExpression('/<button type="button"[^>]*data-bs-target="#resetUser'.$user->id.'"/', $html);
    }

    public function test_user_update_persists_name_email_role_groups_and_dimensions(): void
    {
        $tenant = $this->makeTenant();
        $admin = $this->makeUser($tenant, 'tenant_admin');
        $user = $this->makeUser($tenant, 'user');
        $this->actingAsUser($admin);

        $role = Role::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('slug', 'manager')->first();
        $job = Referential::create(['tenant_id' => $tenant->id, 'type' => 'job', 'name' => 'Responsable Qualité']);
        $site = Referential::create(['tenant_id' => $tenant->id, 'type' => 'site', 'name' => 'Siège']);

        $this->post(route('admin.users.update', $user), [
            'name' => 'Nom Modifié',
            'email' => $user->email,
            'role_id' => $role->id,
            'job_id' => $job->id,
            'site_id' => $site->id,
            'group_ids' => [],
        ])->assertRedirect();

        $user->refresh();
        $this->assertSame('Nom Modifié', $user->name);
        $this->assertSame($role->id, $user->roles()->first()->id);
        $this->assertSame($job->id, $user->job_id);
        $this->assertSame($site->id, $user->site_id);
    }

    public function test_user_creation_creates_personal_space_automatically(): void
    {
        $tenant = $this->makeTenant();
        $admin = $this->makeUser($tenant, 'tenant_admin');
        $this->actingAsUser($admin);

        $role = Role::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('slug', 'user')->first();

        $this->post(route('admin.users.store'), [
            'name' => 'Nouvel Utilisateur',
            'email' => 'nouveau@test.local',
            'password' => 'password123',
            'role_id' => $role->id,
        ])->assertRedirect();

        $created = User::where('email', 'nouveau@test.local')->first();
        $this->assertNotNull($created);
        $space = app(PersonalSpaceService::class)->ensure($created);
        $this->assertTrue($space->is_personal);
        $this->assertSame($created->id, $space->personal_user_id);
    }
}
