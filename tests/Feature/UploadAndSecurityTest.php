<?php

namespace Tests\Feature;

use App\Models\Role;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class UploadAndSecurityTest extends TestCase
{
    public function test_folder_of_another_space_is_rejected(): void
    {
        $tenant = $this->makeTenant();
        $spaceA = $this->makeSpace($tenant);
        $spaceB = $this->makeSpace($tenant);
        $folderB = $this->makeFolder($tenant, $spaceB);
        $user = $this->makeUser($tenant, 'user');
        $this->actingAsUser($user);

        // Server-side guard: a folder belonging to space B cannot be used with space A.
        $response = $this->post(route('documents.store'), [
            'title' => 'Dossier incohérent',
            'space_id' => $spaceA->id,
            'folder_id' => $folderB->id,
            'file' => UploadedFile::fake()->createWithContent('doc.txt', 'contenu', 'text/plain'),
        ]);

        $response->assertSessionHasErrors('file');
        $this->assertDatabaseMissing('documents', ['title' => 'Dossier incohérent']);
    }

    public function test_create_page_filters_folders_by_space(): void
    {
        $tenant = $this->makeTenant();
        $spaceA = $this->makeSpace($tenant, 'Espace A');
        $spaceB = $this->makeSpace($tenant, 'Espace B');
        $folderA = $this->makeFolder($tenant, $spaceA);
        $folderB = $this->makeFolder($tenant, $spaceB);
        $user = $this->makeUser($tenant, 'user');
        $this->actingAsUser($user);

        // The folder options carry their space (data-space) so the client-side
        // filter can prevent the "dossier not in this space" mismatch in the UI.
        $response = $this->get(route('documents.create'));

        $response->assertOk();
        $html = $response->getContent();
        $this->assertStringContainsString('id="spaceSelect"', $html);
        $this->assertStringContainsString('id="folderSelect"', $html);
        $this->assertStringContainsString('data-space="'.$folderA->space_id.'"', $html);
        $this->assertStringContainsString('data-space="'.$folderB->space_id.'"', $html);
    }

    public function test_forbidden_mime_type_is_rejected(): void
    {
        $tenant = $this->makeTenant();
        $space = $this->makeSpace($tenant);
        $user = $this->makeUser($tenant, 'user');
        $this->actingAsUser($user);

        // CA-012: an executable is not an allowed document type.
        $file = UploadedFile::fake()->createWithContent('virus.exe', 'MZ...');

        $response = $this->post(route('documents.store'), [
            'title' => 'Fichier interdit',
            'space_id' => $space->id,
            'file' => $file,
        ]);

        $response->assertSessionHasErrors('file');
        $this->assertDatabaseMissing('documents', ['title' => 'Fichier interdit']);
    }

    public function test_oversized_file_is_rejected(): void
    {
        $tenant = $this->makeTenant(['max_file_size_mb' => 1]);
        $space = $this->makeSpace($tenant);
        $user = $this->makeUser($tenant, 'user');
        $this->actingAsUser($user);

        $file = UploadedFile::fake()->create('big.pdf', 2048); // 2 MB > 1 MB quota

        $response = $this->post(route('documents.store'), [
            'title' => 'Gros fichier',
            'space_id' => $space->id,
            'file' => $file,
        ]);

        $response->assertSessionHasErrors('file');
        $this->assertDatabaseMissing('documents', ['title' => 'Gros fichier']);
    }

    public function test_quota_blocked_before_creation(): void
    {
        $tenant = $this->makeTenant(['storage_quota_mb' => 0]);
        $space = $this->makeSpace($tenant);
        $user = $this->makeUser($tenant, 'user');
        $this->actingAsUser($user);

        $file = UploadedFile::fake()->createWithContent('doc.txt', 'contenu', 'text/plain');

        $response = $this->post(route('documents.store'), [
            'title' => 'Doc sans quota',
            'space_id' => $space->id,
            'file' => $file,
        ]);

        $response->assertSessionHasErrors('file');
        $this->assertDatabaseMissing('documents', ['title' => 'Doc sans quota']);
    }

    public function test_user_quota_is_enforced(): void
    {
        $tenant = $this->makeTenant(['user_quota' => 1]);
        $this->makeUser($tenant, 'user');
        $admin = $this->makeUser($tenant, 'tenant_admin');
        $this->actingAsUser($admin);

        $response = $this->post(route('admin.users.store'), [
            'name' => 'Trop de monde',
            'email' => 'extra@test.local',
            'role_id' => Role::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('slug', 'user')->first()->id,
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertDatabaseMissing('users', ['email' => 'extra@test.local']);
    }

    public function test_files_are_stored_outside_webroot(): void
    {
        $tenant = $this->makeTenant();
        $space = $this->makeSpace($tenant);
        $user = $this->makeUser($tenant, 'user');
        $this->actingAsUser($user);
        $doc = $this->makeDocument($tenant, $user, $space);

        // CA-013: stored file lives outside public/ and has no permanent public URL.
        $path = $doc->currentVersion->file_path;
        $this->assertStringStartsWith('documents/', $path);
        $this->assertFileDoesNotExist(public_path($path));
        $this->assertFileExists(storage_path('app/private/'.$path));
    }

    public function test_no_secret_leaks_in_rendered_pages(): void
    {
        $tenant = $this->makeTenant();
        $space = $this->makeSpace($tenant);
        $user = $this->makeUser($tenant, 'user');
        $doc = $this->makeDocument($tenant, $user, $space);
        $this->actingAsUser($user);

        $html = $this->get(route('documents.show', $doc))->getContent();

        // CA-011: application secrets never reach the browser.
        $appKey = (string) env('APP_KEY');
        if ($appKey !== '') {
            $this->assertStringNotContainsString(substr($appKey, 0, 24), $html);
        }
        $this->assertStringNotContainsString('DB_PASSWORD=', $html);
        $this->assertStringNotContainsString('APP_KEY=', $html);
    }

    public function test_list_is_paginated(): void
    {
        $tenant = $this->makeTenant();
        $space = $this->makeSpace($tenant);
        $user = $this->makeUser($tenant, 'user');

        for ($i = 0; $i < 25; $i++) {
            $this->makeDocument($tenant, $user, $space);
        }

        $this->actingAsUser($user);
        $response = $this->get(route('documents.index'));

        $response->assertOk();
        $paginator = $response->viewData('documents');
        $this->assertSame(25, $paginator->total());
        $this->assertSame(20, $paginator->perPage());
        $this->assertSame(2, $paginator->lastPage());
    }
}
