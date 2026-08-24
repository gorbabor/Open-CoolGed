<?php

namespace Tests\Feature;

use App\Models\MetadataDefinition;
use Tests\TestCase;

class DocumentsIndexTest extends TestCase
{
    public function test_list_is_sorted_by_title_asc(): void
    {
        $tenant = $this->makeTenant();
        $space = $this->makeSpace($tenant);
        $user = $this->makeUser($tenant, 'user');
        $this->actingAsUser($user);

        $this->makeDocument($tenant, $user, $space, ['title' => 'Zebra']);
        $this->makeDocument($tenant, $user, $space, ['title' => 'Alpha']);
        $this->makeDocument($tenant, $user, $space, ['title' => 'Milan']);

        $response = $this->get(route('documents.index', ['sort' => 'title', 'dir' => 'asc']));

        $response->assertOk();
        $html = $response->getContent();
        $this->assertTrue(strpos($html, 'Alpha') < strpos($html, 'Milan'));
        $this->assertTrue(strpos($html, 'Milan') < strpos($html, 'Zebra'));
    }

    public function test_list_is_sorted_by_title_desc(): void
    {
        $tenant = $this->makeTenant();
        $space = $this->makeSpace($tenant);
        $user = $this->makeUser($tenant, 'user');
        $this->actingAsUser($user);

        $this->makeDocument($tenant, $user, $space, ['title' => 'Zebra']);
        $this->makeDocument($tenant, $user, $space, ['title' => 'Alpha']);
        $this->makeDocument($tenant, $user, $space, ['title' => 'Milan']);

        $response = $this->get(route('documents.index', ['sort' => 'title', 'dir' => 'desc']));

        $response->assertOk();
        $html = $response->getContent();
        $this->assertTrue(strpos($html, 'Zebra') < strpos($html, 'Milan'));
        $this->assertTrue(strpos($html, 'Milan') < strpos($html, 'Alpha'));
    }

    public function test_unknown_sort_column_is_ignored(): void
    {
        $tenant = $this->makeTenant();
        $space = $this->makeSpace($tenant);
        $user = $this->makeUser($tenant, 'user');
        $this->actingAsUser($user);

        $this->makeDocument($tenant, $user, $space, ['title' => 'Zebra']);
        $this->makeDocument($tenant, $user, $space, ['title' => 'Alpha']);

        // Colonne arbitraire : pas d'erreur, retour au tri par défaut (updated_at desc).
        $response = $this->get(route('documents.index', ['sort' => 'password; DROP TABLE', 'dir' => 'asc']));
        $response->assertOk();
    }

    public function test_metadata_column_selection_persists_in_session(): void
    {
        $tenant = $this->makeTenant();
        $space = $this->makeSpace($tenant);
        $user = $this->makeUser($tenant, 'user');
        $this->actingAsUser($user);

        $def = MetadataDefinition::create([
            'tenant_id' => $tenant->id,
            'name' => 'Montant HT',
            'key' => 'montant_ht',
            'type' => 'text',
        ]);

        $doc = $this->makeDocument($tenant, $user, $space, ['title' => 'Facture test']);
        $doc->metadataValues()->create([
            'tenant_id' => $tenant->id,
            'definition_id' => $def->id,
            'value' => '1250',
        ]);

        // 1re requête : on sélectionne la colonne.
        $this->get(route('documents.index', ['cols' => [$def->id]]))->assertOk();

        // 2e requête sans cols : la colonne persiste (session) et la valeur s'affiche.
        $response = $this->get(route('documents.index'));
        $response->assertOk();
        $html = $response->getContent();
        $this->assertStringContainsString('Montant HT', $html);
        $this->assertStringContainsString('1250', $html);
    }

    public function test_metadata_column_can_be_cleared(): void
    {
        $tenant = $this->makeTenant();
        $space = $this->makeSpace($tenant);
        $user = $this->makeUser($tenant, 'user');
        $this->actingAsUser($user);

        $def = MetadataDefinition::create([
            'tenant_id' => $tenant->id,
            'name' => 'Fournisseur',
            'key' => 'fournisseur',
            'type' => 'text',
        ]);

        $this->get(route('documents.index', ['cols' => [$def->id]]))->assertOk();
        // Effacement explicite : paramètre cols présent mais vide (via la requête brute).
        $this->get('/documents?cols=')->assertOk();

        $response = $this->get(route('documents.index'));
        $response->assertOk();
        // La colonne du tableau disparaît (le label reste dans le sélecteur de colonnes).
        $this->assertStringNotContainsString('<th>Fournisseur</th>', $response->getContent());
    }
}
