<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Role;
use App\Models\Space;
use App\Models\User;
use App\Services\PersonalSpaceService;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class PersonalSpacesTest extends TestCase
{
    public function test_personal_space_is_created_and_is_unique_per_user(): void
    {
        $tenant = $this->makeTenant();
        $admin = $this->makeUser($tenant, 'tenant_admin');
        $this->actingAsUser($admin);
        $role = Role::where('tenant_id', $tenant->id)->where('slug', 'user')->first();

        $this->post(route('admin.users.store'), ['name' => 'Alice', 'email' => 'alice@test.local', 'password' => 'password123', 'role_id' => $role->id])->assertRedirect();
        $alice = User::where('email', 'alice@test.local')->first();
        $space = Space::withoutGlobalScopes()->where('personal_user_id', $alice->id)->first();
        $this->assertNotNull($space);
        $this->assertTrue($space->is_personal);
        $this->assertStringContainsString('Alice', $space->name);
        $this->assertSame($space->id, app(PersonalSpaceService::class)->ensure($alice)->id);
    }

    public function test_personal_upload_cannot_be_falsified_to_shared_space(): void
    {
        $tenant = $this->makeTenant();
        $user = $this->makeUser($tenant, 'user');
        $shared = $this->makeSpace($tenant, 'Partagé');
        $this->actingAsUser($user);

        $this->post(route('documents.store'), [
            'title' => 'Privé', 'space_id' => $shared->id, 'storage_scope' => 'personal',
            'file' => UploadedFile::fake()->createWithContent('p.txt', 'secret', 'text/plain'),
        ])->assertRedirect();
        $doc = Document::withoutGlobalScopes()->where('title', 'Privé')->first();
        $this->assertNotSame($shared->id, $doc->space_id);
        $this->assertSame($user->id, $doc->owner_id);
    }

    public function test_personal_documents_are_hidden_from_other_users(): void
    {
        $tenant = $this->makeTenant();
        $alice = $this->makeUser($tenant, 'user');
        $bob = $this->makeUser($tenant, 'user');
        $space = app(PersonalSpaceService::class)->ensure($alice);
        $doc = $this->makeDocument($tenant, $alice, $space, ['title' => 'Secret Alice', 'owner_id' => $alice->id]);

        $this->actingAsUser($bob);
        $this->get(route('documents.show', $doc))->assertStatus(403);
        $this->get(route('documents.index', ['personal' => 1]))->assertDontSee('Secret Alice');
    }
}
