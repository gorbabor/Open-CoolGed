<?php

namespace Tests\Feature;

use App\Models\DocumentType;
use App\Models\MetadataDefinition;
use App\Models\Referential;
use App\Models\Tenant;
use App\Support\TenantContext;
use Illuminate\Support\Str;
use Tests\TestCase;

class DocumentCardsViewTest extends TestCase
{
    public function test_view_mode_toggle_persists_in_session(): void
    {
        $tenant = $this->makeTenant();
        $space = $this->makeSpace($tenant);
        $user = $this->makeUser($tenant, 'user');
        $this->actingAsUser($user);
        $this->makeDocument($tenant, $user, $space);

        $this->get(route('documents.index', ['view' => 'cards']))->assertOk()
            ->assertSee('Regrouper par');

        $response = $this->get(route('documents.index'));
        $response->assertOk();
        $this->assertStringContainsString('Regrouper par', $response->getContent());

        $this->get(route('documents.index', ['view' => 'list']))->assertOk()
            ->assertDontSee('Regrouper par');

        $response = $this->get(route('documents.index'));
        $response->assertOk();
        $this->assertStringNotContainsString('Regrouper par', $response->getContent());
    }

    public function test_cards_group_by_space_with_counts(): void
    {
        $tenant = $this->makeTenant();
        $spaceA = $this->makeSpace($tenant, 'Espace Alpha');
        $spaceB = $this->makeSpace($tenant, 'Espace Beta');
        $user = $this->makeUser($tenant, 'user');
        $this->actingAsUser($user);

        $this->makeDocument($tenant, $user, $spaceA);
        $this->makeDocument($tenant, $user, $spaceA);
        $this->makeDocument($tenant, $user, $spaceB);

        $response = $this->get(route('documents.index', ['view' => 'cards', 'group' => 'space']));
        $response->assertOk();
        $html = $response->getContent();

        $this->assertStringContainsString('Regrouper par', $html);
        $this->assertStringContainsString('Espace Alpha', $html);
        $this->assertStringContainsString('>2 documents<', $html);
        $this->assertStringContainsString('>1 document<', $html);
        $this->assertStringContainsString('group=space', $html);
        $this->assertStringContainsString('value='.$spaceA->id, $html);
    }

    public function test_cards_group_by_custom_metadata_with_missing_bucket(): void
    {
        $tenant = $this->makeTenant();
        TenantContext::set($tenant->id);
        $space = $this->makeSpace($tenant);
        $user = $this->makeUser($tenant, 'user');
        $this->actingAsUser($user);

        $def = MetadataDefinition::create([
            'tenant_id' => $tenant->id,
            'name' => 'Service',
            'key' => 'service',
            'type' => 'text',
        ]);

        $docA = $this->makeDocument($tenant, $user, $space, ['title' => 'Doc Finances']);
        $docA->metadataValues()->create(['tenant_id' => $tenant->id, 'definition_id' => $def->id, 'value' => 'Finances']);
        $docB = $this->makeDocument($tenant, $user, $space, ['title' => 'Doc RH']);
        $docB->metadataValues()->create(['tenant_id' => $tenant->id, 'definition_id' => $def->id, 'value' => 'RH']);
        $this->makeDocument($tenant, $user, $space, ['title' => 'Doc sans valeur']);

        $response = $this->get(route('documents.index', ['view' => 'cards', 'group' => 'meta:'.$def->id]));
        $response->assertOk();
        $html = $response->getContent();

        $this->assertStringContainsString('Finances', $html);
        $this->assertStringContainsString('RH', $html);
        $this->assertStringContainsString('Non renseigné', $html);
        $this->assertStringContainsString('value=Finances', $html);
    }

    public function test_cards_group_by_referential_site(): void
    {
        $tenant = $this->makeTenant();
        $space = $this->makeSpace($tenant);
        $user = $this->makeUser($tenant, 'user');
        $this->actingAsUser($user);

        $site = Referential::create(['tenant_id' => $tenant->id, 'type' => 'site', 'name' => 'Site Paris']);

        $doc = $this->makeDocument($tenant, $user, $space);
        $doc->referentials()->attach($site->id, ['tenant_id' => $tenant->id, 'type' => 'site']);
        $this->makeDocument($tenant, $user, $space, ['title' => 'Doc sans site']);

        $response = $this->get(route('documents.index', ['view' => 'cards', 'group' => 'ref:site']));
        $response->assertOk();
        $html = $response->getContent();

        $this->assertStringContainsString('Site Paris', $html);
        $this->assertStringContainsString('>1 document<', $html);
        $this->assertStringContainsString('Non renseigné', $html);
        $this->assertStringContainsString('value='.$site->id, $html);
    }

    public function test_cards_group_by_native_status(): void
    {
        $tenant = $this->makeTenant();
        $space = $this->makeSpace($tenant);
        $user = $this->makeUser($tenant, 'user');
        $this->actingAsUser($user);

        $this->makeDocument($tenant, $user, $space, ['title' => 'Brouillon 1', 'status' => 'draft']);
        $this->makeDocument($tenant, $user, $space, ['title' => 'Approuvé 1', 'status' => 'approved']);
        $this->makeDocument($tenant, $user, $space, ['title' => 'Approuvé 2', 'status' => 'approved']);

        $response = $this->get(route('documents.index', ['view' => 'cards', 'group' => 'status']));
        $response->assertOk();
        $html = $response->getContent();

        $this->assertStringContainsString('Brouillon', $html);
        $this->assertStringContainsString('Approuvé', $html);
        $this->assertStringContainsString('>2 documents<', $html);
        $this->assertStringContainsString('>1 document<', $html);
    }

    public function test_click_card_opens_filtered_list_with_badge_and_back(): void
    {
        $tenant = $this->makeTenant();
        $spaceA = $this->makeSpace($tenant, 'Espace Alpha');
        $spaceB = $this->makeSpace($tenant, 'Espace Beta');
        $user = $this->makeUser($tenant, 'user');
        $this->actingAsUser($user);

        $docA = $this->makeDocument($tenant, $user, $spaceA, ['title' => 'Doc dans Alpha']);
        $this->makeDocument($tenant, $user, $spaceB, ['title' => 'Doc dans Beta']);

        $response = $this->get(route('documents.index', ['view' => 'list', 'group' => 'space', 'value' => $spaceA->id]));
        $response->assertOk();
        $html = $response->getContent();

        $this->assertStringContainsString('Regroupement :', $html);
        $this->assertStringContainsString('Espace Alpha', $html);
        $this->assertStringContainsString('Retour aux cartes', $html);
        $this->assertStringContainsString('Doc dans Alpha', $html);
        $this->assertStringNotContainsString('Doc dans Beta', $html);
        $this->assertStringContainsString('/documents/'.$docA->id, $html);
    }

    public function test_back_to_cards_keeps_dimension(): void
    {
        $tenant = $this->makeTenant();
        $space = $this->makeSpace($tenant);
        $user = $this->makeUser($tenant, 'user');
        $this->actingAsUser($user);
        $this->makeDocument($tenant, $user, $space);

        $response = $this->get(route('documents.index', ['view' => 'cards', 'group' => 'space']));
        $response->assertOk();
        $html = $response->getContent();

        $this->assertStringContainsString('name="group"', $html);
        $this->assertStringContainsString('value="space" selected', $html);
    }

    public function test_card_labels_are_not_truncated(): void
    {
        $tenant = $this->makeTenant();
        $spaceA = $this->makeSpace($tenant, 'Espace avec un très long nom de plusieurs mots pour tester');
        $spaceB = $this->makeSpace($tenant, 'Espace B');
        $user = $this->makeUser($tenant, 'user');
        $this->actingAsUser($user);
        $this->makeDocument($tenant, $user, $spaceA);
        $this->makeDocument($tenant, $user, $spaceB);

        $response = $this->get(route('documents.index', ['view' => 'cards', 'group' => 'space']));
        $response->assertOk();
        $html = $response->getContent();

        // Le libellé complet est rendu (pas de text-truncate ni d'ellipsis imposés par la classe).
        $this->assertStringContainsString('Espace avec un très long nom de plusieurs mots pour tester', $html);
        $this->assertStringNotContainsString('text-truncate', $html);
        $this->assertStringContainsString('overflow-wrap: anywhere', $html);
    }

    public function test_cards_counts_respect_permissions(): void
    {
        $tenantA = $this->makeTenant();
        $tenantB = $this->makeTenant();
        $spaceA = $this->makeSpace($tenantA, 'Espace Tenant A');
        $userA = $this->makeUser($tenantA, 'user');
        $this->actingAsUser($userA);
        $this->makeDocument($tenantA, $userA, $spaceA, ['title' => 'Doc visible A']);

        $spaceB = $this->makeSpace($tenantB, 'Espace Tenant B');
        $userB = $this->makeUser($tenantB, 'user');
        $this->makeDocument($tenantB, $userB, $spaceB, ['title' => 'Doc invisible B']);

        $response = $this->get(route('documents.index', ['view' => 'cards', 'group' => 'space']));
        $response->assertOk();
        $html = $response->getContent();

        $this->assertStringContainsString('Espace Tenant A', $html);
        $this->assertStringNotContainsString('Espace Tenant B', $html);
        $this->assertStringNotContainsString('Doc invisible B', $html);
    }

    public function test_cards_counts_combinable_with_existing_filters(): void
    {
        $tenant = $this->makeTenant();
        $spaceA = $this->makeSpace($tenant, 'Espace Alpha');
        $spaceB = $this->makeSpace($tenant, 'Espace Beta');
        $user = $this->makeUser($tenant, 'user');
        $this->actingAsUser($user);

        $type1 = $this->makeType($tenant, 'Type Un');
        $type2 = $this->makeType($tenant, 'Type Deux');
        $this->makeDocument($tenant, $user, $spaceA, ['title' => 'A1', 'document_type_id' => $type1->id]);
        $this->makeDocument($tenant, $user, $spaceA, ['title' => 'A2', 'document_type_id' => $type2->id]);
        $this->makeDocument($tenant, $user, $spaceB, ['title' => 'B1', 'document_type_id' => $type1->id]);

        $response = $this->get(route('documents.index', ['view' => 'cards', 'group' => 'type', 'space_id' => $spaceA->id]));
        $response->assertOk();
        $html = $response->getContent();

        $this->assertStringContainsString('Type Un', $html);
        $this->assertStringContainsString('Type Deux', $html);
        $this->assertSame(2, substr_count($html, '>1 document<'));
        $this->assertSame(0, substr_count($html, '>2 documents<'));
    }

    public function test_click_card_preserves_sort_param(): void
    {
        $tenant = $this->makeTenant();
        $spaceA = $this->makeSpace($tenant, 'Espace Alpha');
        $spaceB = $this->makeSpace($tenant, 'Espace Beta');
        $user = $this->makeUser($tenant, 'user');
        $this->actingAsUser($user);
        $this->makeDocument($tenant, $user, $spaceA);
        $this->makeDocument($tenant, $user, $spaceB);

        $response = $this->get(route('documents.index', ['view' => 'cards', 'group' => 'space', 'sort' => 'title', 'dir' => 'asc']));
        $response->assertOk();
        $html = $response->getContent();

        $this->assertStringContainsString('sort=title', $html);
        $this->assertStringContainsString('dir=asc', $html);
        $this->assertStringContainsString('view=list', $html);
    }

    public function test_unknown_group_falls_back_to_default_dimension(): void
    {
        $tenant = $this->makeTenant();
        $space = $this->makeSpace($tenant);
        $user = $this->makeUser($tenant, 'user');
        $this->actingAsUser($user);
        $this->makeDocument($tenant, $user, $space);

        $response = $this->get(route('documents.index', ['view' => 'cards', 'group' => 'password; DROP']));
        $response->assertOk();
        $this->assertStringContainsString('value="space" selected', $response->getContent());
    }

    public function test_v02_page_cards_group_by_criticality(): void
    {
        $tenant = $this->makeTenant();
        $space = $this->makeSpace($tenant);
        $user = $this->makeUser($tenant, 'user');
        $this->actingAsUser($user);

        $this->makeDocument($tenant, $user, $space, [
            'title' => 'Doc standard', 'owner_id' => $user->id,
            'status' => 'approuve_applicable', 'is_active_version' => true, 'criticality' => 'standard',
        ]);
        $this->makeDocument($tenant, $user, $space, [
            'title' => 'Doc critique', 'owner_id' => $user->id,
            'status' => 'approuve_applicable', 'is_active_version' => true, 'criticality' => 'critical',
        ]);
        $this->makeDocument($tenant, $user, $space, [
            'title' => 'Doc brouillon caché', 'owner_id' => $user->id,
            'status' => 'draft', 'is_active_version' => true, 'criticality' => 'standard',
        ]);

        $response = $this->get(route('v02.my-documents', ['view' => 'cards', 'group' => 'criticality']));
        $response->assertOk();
        $html = $response->getContent();

        $this->assertStringContainsString('Regrouper par', $html);
        $this->assertStringContainsString('standard', $html);
        $this->assertStringContainsString('critical', $html);
        $this->assertStringNotContainsString('Doc brouillon caché', $html);

        $list = $this->get(route('v02.my-documents', ['view' => 'list', 'group' => 'criticality', 'value' => 'critical']));
        $list->assertOk();
        $listHtml = $list->getContent();
        $this->assertStringContainsString('Regroupement :', $listHtml);
        $this->assertStringContainsString('Doc critique', $listHtml);
        $this->assertStringNotContainsString('Doc standard', $listHtml);
    }

    public function test_selector_lists_all_dimensions(): void
    {
        $tenant = $this->makeTenant();
        TenantContext::set($tenant->id);
        $space = $this->makeSpace($tenant);
        $user = $this->makeUser($tenant, 'user');
        $this->actingAsUser($user);
        $this->makeDocument($tenant, $user, $space);

        MetadataDefinition::create(['tenant_id' => $tenant->id, 'name' => 'Fournisseur', 'key' => 'fournisseur', 'type' => 'text']);

        $response = $this->get(route('documents.index', ['view' => 'cards']));
        $response->assertOk();
        $html = $response->getContent();

        foreach (['value="space"', 'value="type"', 'value="status"', 'value="confidentiality"', 'value="domain"', 'value="process"', 'value="ref:job"', 'value="ref:country"', 'value="meta:'] as $option) {
            $this->assertStringContainsString($option, $html);
        }
    }

    private function makeType(Tenant $tenant, string $name): DocumentType
    {
        return DocumentType::create(['tenant_id' => $tenant->id, 'name' => $name, 'slug' => Str::slug($name).'-'.uniqid()]);
    }
}
