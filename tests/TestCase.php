<?php

namespace Tests;

use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Folder;
use App\Models\Role;
use App\Models\Space;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SystemRoleService;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\UploadedFile;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        SystemRoleService::seedPermissions();
        TenantContext::set(null);
    }

    protected function tearDown(): void
    {
        TenantContext::set(null);
        parent::tearDown();
    }

    protected function makeTenant(array $attrs = []): Tenant
    {
        $tenant = Tenant::create(array_merge([
            'name' => 'Tenant '.uniqid(),
            'slug' => 'tenant-'.uniqid(),
            'status' => 'active',
            'plan' => 'standard',
            'storage_quota_mb' => 5120,
            'user_quota' => 50,
            'max_file_size_mb' => 50,
            'settings' => ['ai_enabled' => true, 'ai_providers' => ['mock']],
        ], $attrs));

        app(SystemRoleService::class)->ensureFor($tenant);

        return $tenant;
    }

    protected function makeUser(Tenant $tenant, string $roleSlug = 'user', array $attrs = []): User
    {
        $user = User::create(array_merge([
            'tenant_id' => $tenant->id,
            'name' => 'User '.uniqid(),
            'email' => uniqid().'@test.local',
            'password' => 'password123',
            'status' => 'active',
        ], $attrs));

        $role = Role::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('slug', $roleSlug)->first();
        if ($role) {
            $user->roles()->attach($role->id, ['tenant_id' => $tenant->id]);
        }

        return $user;
    }

    protected function makeSpace(Tenant $tenant, string $name = 'Espace'): Space
    {
        return Space::create([
            'tenant_id' => $tenant->id,
            'name' => $name.' '.uniqid(),
        ]);
    }

    protected function makeFolder(Tenant $tenant, Space $space): Folder
    {
        return Folder::create([
            'tenant_id' => $tenant->id,
            'space_id' => $space->id,
            'name' => 'Dossier '.uniqid(),
        ]);
    }

    protected function makeDocument(Tenant $tenant, User $creator, Space $space, array $attrs = []): Document
    {
        $content = $attrs['content'] ?? 'Contenu de test '.uniqid();
        $file = UploadedFile::fake()->createWithContent('doc-'.uniqid().'.txt', $content, 'text/plain');

        // Work under the tenant context so scoped writes (current_version_id) persist.
        $previous = TenantContext::get();
        TenantContext::set($tenant->id);

        try {
            $document = Document::create(array_merge([
                'tenant_id' => $tenant->id,
                'space_id' => $space->id,
                'title' => 'Document '.uniqid(),
                'status' => 'draft',
                'confidentiality' => 'internal',
                'created_by' => $creator->id,
            ], $attrs));

            $path = $file->store('documents/'.$tenant->id, 'local');

            $version = DocumentVersion::create([
                'tenant_id' => $tenant->id,
                'document_id' => $document->id,
                'version' => '1.0',
                'file_path' => $path,
                'file_name' => $file->getClientOriginalName(),
                'mime_type' => 'text/plain',
                'size' => $file->getSize(),
                'checksum' => hash_file('sha256', $file->getRealPath()),
                'extracted_text' => $content,
                'comment' => 'Version initiale',
                'created_by' => $creator->id,
            ]);

            $document->update(['current_version_id' => $version->id]);

            $result = $document->fresh();
        } finally {
            TenantContext::set($previous);
        }

        return $result;
    }

    protected function actingAsUser(User $user)
    {
        TenantContext::set($user->tenant_id);

        return $this->actingAs($user);
    }
}
