<?php

namespace Tests\Feature;

use App\Models\MetadataDefinition;
use App\Models\Referential;
use Tests\TestCase;

class DocumentColumnPreferencesTest extends TestCase
{
    public function test_documents_extra_columns_are_rendered_and_persisted(): void
    {
        $tenant = $this->makeTenant();
        $space = $this->makeSpace($tenant);
        $user = $this->makeUser($tenant, 'user');
        $this->actingAsUser($user);
        $domain = Referential::create(['tenant_id' => $tenant->id, 'type' => 'domain', 'name' => 'Qualité']);
        $site = Referential::create(['tenant_id' => $tenant->id, 'type' => 'site', 'name' => 'Siège']);
        $doc = $this->makeDocument($tenant, $user, $space, ['domain_id' => $domain->id]);
        $doc->referentials()->attach($site->id, ['tenant_id' => $tenant->id, 'type' => 'site']);

        $html = $this->get(route('documents.index', ['cols' => ['domain', 'ref:site']]))->assertOk()->getContent();
        $this->assertStringContainsString('Qualité', $html);
        $this->assertStringContainsString('Siège', $html);
        $user->refresh();
        $this->assertSame(['domain', 'ref:site'], $user->doc_columns);

        $this->get(route('documents.index'))->assertSee('Qualité')->assertSee('Siège');
    }

    public function test_my_documents_columns_render_missing_value_and_persist_separately(): void
    {
        $tenant = $this->makeTenant();
        $space = $this->makeSpace($tenant);
        $user = $this->makeUser($tenant, 'user');
        $this->actingAsUser($user);
        $domain = Referential::create(['tenant_id' => $tenant->id, 'type' => 'domain', 'name' => 'Qualité']);
        $doc = $this->makeDocument($tenant, $user, $space, [
            'owner_id' => $user->id, 'status' => 'approuve_applicable', 'is_active_version' => true, 'domain_id' => $domain->id,
        ]);
        $this->makeDocument($tenant, $user, $space, [
            'owner_id' => $user->id, 'status' => 'approuve_applicable', 'is_active_version' => true,
        ]);

        $html = $this->get(route('v02.my-documents', ['cols' => ['domain', 'ref:site']]))->assertOk()->getContent();
        $this->assertStringContainsString('Colonnes à afficher', $html);
        $this->assertStringContainsString('Qualité', $html);
        $this->assertStringContainsString('—', $html);
        $user->refresh();
        $this->assertSame(['domain', 'ref:site'], $user->my_doc_columns);
        $this->assertNotSame($user->doc_columns, $user->my_doc_columns);
    }

    public function test_my_documents_custom_metadata_column_is_displayed(): void
    {
        $tenant = $this->makeTenant();
        $space = $this->makeSpace($tenant);
        $user = $this->makeUser($tenant, 'user');
        $this->actingAsUser($user);
        $def = MetadataDefinition::create(['tenant_id' => $tenant->id, 'name' => 'Client', 'key' => 'client', 'type' => 'text']);
        $doc = $this->makeDocument($tenant, $user, $space, ['owner_id' => $user->id, 'status' => 'approuve_applicable', 'is_active_version' => true]);
        $doc->metadataValues()->create(['tenant_id' => $tenant->id, 'definition_id' => $def->id, 'value' => 'Acme']);

        $this->get(route('v02.my-documents', ['cols' => [(string) $def->id]]))->assertOk()->assertSee('Client')->assertSee('Acme');
    }
}
