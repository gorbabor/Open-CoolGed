<?php

namespace Tests\Feature;

use Tests\TestCase;

class AccessPeaufinageTest extends TestCase
{
    public function test_create_form_requires_documents_create_permission(): void
    {
        $tenant = $this->makeTenant();

        foreach (['validator', 'auditor'] as $role) {
            $user = $this->makeUser($tenant, $role);
            $this->actingAsUser($user);
            $this->get(route('documents.create'))->assertStatus(403);
        }

        $user = $this->makeUser($tenant, 'user');
        $this->actingAsUser($user);
        $this->get(route('documents.create'))->assertOk();
    }

    public function test_import_buttons_are_hidden_without_create_permission(): void
    {
        $tenant = $this->makeTenant();
        $space = $this->makeSpace($tenant);
        $creator = $this->makeUser($tenant, 'user');
        $this->makeDocument($tenant, $creator, $space);

        $auditor = $this->makeUser($tenant, 'auditor');
        $this->actingAsUser($auditor);

        $index = $this->get(route('documents.index'))->assertOk();
        $this->assertStringNotContainsString(route('documents.create'), $index->getContent());

        $dashboard = $this->get(route('dashboard'))->assertOk();
        $this->assertStringNotContainsString(route('documents.create'), $dashboard->getContent());

        $this->actingAsUser($creator);
        $this->assertStringContainsString(route('documents.create'), $this->get(route('documents.index'))->getContent());
        $this->assertStringContainsString(route('documents.create'), $this->get(route('dashboard'))->getContent());
    }

    public function test_spaces_management_requires_admin_spaces_permission(): void
    {
        $tenant = $this->makeTenant();
        $space = $this->makeSpace($tenant);
        $folder = $this->makeFolder($tenant, $space);

        foreach (['user', 'manager', 'validator', 'auditor'] as $role) {
            $user = $this->makeUser($tenant, $role);
            $this->actingAsUser($user);

            $this->post(route('spaces.store'), ['name' => 'Espace interdit'])->assertStatus(403);
            $this->post(route('spaces.rename', $space), ['name' => 'Renommage interdit'])->assertStatus(403);
            $this->post(route('folders.store', $space), ['name' => 'Dossier interdit'])->assertStatus(403);
            $this->post(route('folders.rename', $folder), ['name' => 'Renommage interdit'])->assertStatus(403);
            $this->delete(route('folders.delete', $folder))->assertStatus(403);
            $this->delete(route('spaces.delete', $space))->assertStatus(403);
        }

        $this->assertDatabaseMissing('spaces', ['tenant_id' => $tenant->id, 'name' => 'Espace interdit']);

        $admin = $this->makeUser($tenant, 'tenant_admin');
        $this->actingAsUser($admin);
        $this->post(route('spaces.store'), ['name' => 'Espace admin'])->assertRedirect();
        $this->assertDatabaseHas('spaces', ['tenant_id' => $tenant->id, 'name' => 'Espace admin']);
        $this->post(route('spaces.rename', $space), ['name' => 'Espace renommé'])->assertRedirect();
        $this->assertDatabaseHas('spaces', ['id' => $space->id, 'name' => 'Espace renommé']);
    }

    public function test_spaces_page_hides_management_controls_for_members(): void
    {
        $tenant = $this->makeTenant();
        $space = $this->makeSpace($tenant);

        $user = $this->makeUser($tenant, 'user');
        $this->actingAsUser($user);
        $html = $this->get(route('spaces.index'))->assertOk()->getContent();
        $this->assertStringNotContainsString('data-bs-target="#newSpace"', $html);
        $this->assertStringNotContainsString(route('spaces.rename', $space), $html);
        $this->assertStringNotContainsString(route('folders.store', $space), $html);
        $this->assertStringNotContainsString(route('folders.rename', $this->makeFolder($tenant, $space)), $html);
        $this->assertStringNotContainsString(route('spaces.delete', $space), $html);

        $admin = $this->makeUser($tenant, 'tenant_admin');
        $this->actingAsUser($admin);
        $html2 = $this->get(route('spaces.index'))->assertOk()->getContent();
        $this->assertStringContainsString('data-bs-target="#newSpace"', $html2);
        $this->assertStringContainsString(route('spaces.rename', $space), $html2);
        $this->assertStringContainsString(route('spaces.delete', $space), $html2);
    }

    public function test_admin_ai_page_requires_admin_ai_permission(): void
    {
        $tenant = $this->makeTenant();

        foreach (['user', 'manager'] as $role) {
            $user = $this->makeUser($tenant, $role);
            $this->actingAsUser($user);
            $this->get(route('admin.ai'))->assertStatus(403);
        }

        $admin = $this->makeUser($tenant, 'tenant_admin');
        $this->actingAsUser($admin);
        $this->get(route('admin.ai'))->assertOk();
    }
}
