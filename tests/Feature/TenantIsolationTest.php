<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\Document;
use App\Support\TenantContext;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    public function test_user_cannot_access_document_of_another_tenant(): void
    {
        $tenantA = $this->makeTenant();
        $tenantB = $this->makeTenant();
        $userA = $this->makeUser($tenantA, 'user');
        $userB = $this->makeUser($tenantB, 'user');
        $spaceB = $this->makeSpace($tenantB);
        $docB = $this->makeDocument($tenantB, $userB, $spaceB);

        $this->actingAsUser($userA);

        // CA-001: direct URL access to tenant B document is impossible (404 via tenant scope).
        $this->get(route('documents.show', $docB))->assertStatus(404);

        // CA-003: even a download attempt is refused.
        $this->get(route('documents.download', $docB))->assertStatus(404);

        // Same tenant document remains accessible.
        $spaceA = $this->makeSpace($tenantA);
        $docA = $this->makeDocument($tenantA, $userA, $spaceA);
        $this->get(route('documents.show', $docA))->assertStatus(200);
    }

    public function test_api_token_cannot_reach_another_tenant(): void
    {
        $tenantA = $this->makeTenant();
        $tenantB = $this->makeTenant();
        $userA = $this->makeUser($tenantA, 'user');
        $userB = $this->makeUser($tenantB, 'user');
        $spaceB = $this->makeSpace($tenantB);
        $docB = $this->makeDocument($tenantB, $userB, $spaceB);

        $token = 'test-token-'.uniqid();
        ApiToken::create([
            'tenant_id' => $tenantA->id,
            'user_id' => $userA->id,
            'name' => 'test',
            'token_hash' => ApiToken::hash($token),
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/documents/'.$docB->id)
            ->assertStatus(404);
    }

    public function test_super_admin_has_no_default_content_access(): void
    {
        $tenant = $this->makeTenant();
        $user = $this->makeUser($tenant, 'user');
        $space = $this->makeSpace($tenant);
        $doc = $this->makeDocument($tenant, $user, $space);

        $superAdmin = $this->makeUser($tenant, 'user', [
            'is_super_admin' => true,
            'email' => 'super-'.uniqid().'@test.local',
        ]);

        TenantContext::set(null);
        $this->actingAs($superAdmin);

        // RM-003: the super administrator does not read tenant content by default.
        $this->get(route('documents.show', $doc))->assertStatus(404);
    }

    public function test_search_only_returns_accessible_documents(): void
    {
        $tenantA = $this->makeTenant();
        $tenantB = $this->makeTenant();
        $userA = $this->makeUser($tenantA, 'user');
        $userB = $this->makeUser($tenantB, 'user');
        $spaceB = $this->makeSpace($tenantB);
        $docB = $this->makeDocument($tenantB, $userB, $spaceB, ['title' => 'CONFIDENTIEL_UNIQUE_XYZ']);

        $this->actingAsUser($userA);

        $response = $this->get(route('search', ['q' => 'CONFIDENTIEL_UNIQUE_XYZ']));
        $response->assertOk();
        // The tenant-B document must not appear in the results.
        $response->assertDontSee('/documents/'.$docB->id);
    }

    public function test_foreign_document_id_in_metadata_requests_is_rejected(): void
    {
        $tenantA = $this->makeTenant();
        $tenantB = $this->makeTenant();
        $userA = $this->makeUser($tenantA, 'user');
        $userB = $this->makeUser($tenantB, 'user');
        $spaceB = $this->makeSpace($tenantB);
        $docB = $this->makeDocument($tenantB, $userB, $spaceB);

        $this->actingAsUser($userA);

        $this->post(route('documents.metadata', $docB), ['reference' => 'hack'])->assertStatus(404);
        $this->post(route('documents.comment', $docB), ['body' => 'hack'])->assertStatus(404);
    }
}
