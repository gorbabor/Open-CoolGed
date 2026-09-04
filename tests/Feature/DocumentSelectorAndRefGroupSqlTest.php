<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\MetadataDefinition;
use App\Services\DocumentGroupService;
use Tests\TestCase;

class DocumentSelectorAndRefGroupSqlTest extends TestCase
{
    public function test_column_selector_visible_without_metadata_definitions(): void
    {
        $tenant = $this->makeTenant();
        $this->assertSame(0, MetadataDefinition::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());

        $user = $this->makeUser($tenant, 'user');
        $this->actingAsUser($user);
        $space = $this->makeSpace($tenant);
        $this->makeDocument($tenant, $user, $space, ['title' => 'Doc A', 'reference' => 'REF-A']);

        $html = $this->get(route('documents.index'))->assertOk()->getContent();
        $this->assertStringContainsString('Colonnes à afficher…', $html);
        $this->assertStringContainsString('value="reference"', $html);
        $this->assertStringContainsString('value="document_code"', $html);

        $this->get(route('documents.index', ['cols' => ['reference']]))->assertOk();
        $this->assertSame(['reference'], $user->fresh()->doc_columns);

        $html2 = $this->get(route('documents.index'))->assertOk()->getContent();
        $this->assertStringContainsString('>Référence<', $html2);
    }

    public function test_ref_group_sql_uses_qualified_pivot_column(): void
    {
        $tenant = $this->makeTenant();
        $user = $this->makeUser($tenant, 'user');
        $this->actingAsUser($user);
        $space = $this->makeSpace($tenant);
        $doc = $this->makeDocument($tenant, $user, $space);

        $service = app(DocumentGroupService::class);
        $base = Document::query()->whereIn('documents.id', [$doc->id]);

        $none = $service->applyFilter($base->clone(), 'ref:site', '__none__')->toSql();
        $this->assertStringNotContainsString('pivot', $none);
        $this->assertStringContainsString('document_referential', $none);

        $some = $service->applyFilter($base->clone(), 'ref:site', '123')->toSql();
        $this->assertStringNotContainsString('pivot', $some);
        $this->assertStringContainsString('document_referential', $some);
    }
}
