<?php

namespace Tests\Feature;

use App\Models\MetadataDefinition;
use App\Models\Tenant;
use App\Support\TenantContext;
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

    private function makeMeta(Tenant $tenant, string $name, string $type, array $values): MetadataDefinition
    {
        TenantContext::set($tenant->id);

        $def = MetadataDefinition::create([
            'tenant_id' => $tenant->id,
            'name' => $name,
            'key' => strtolower(str_replace(' ', '_', $name)),
            'type' => $type,
        ]);

        foreach ($values as $title => $value) {
            $doc = $this->makeDocument($tenant, $this->makeUser($tenant, 'user'), $this->makeSpace($tenant), ['title' => $title]);
            $doc->metadataValues()->create([
                'tenant_id' => $tenant->id,
                'definition_id' => $def->id,
                'value' => $value,
            ]);
        }

        return $def;
    }

    public function test_metadata_column_is_sortable_textually(): void
    {
        $tenant = $this->makeTenant();
        $def = $this->makeMeta($tenant, 'Service', 'text', ['Alpha' => 'Finances', 'Beta' => 'RH', 'Gamma' => 'Direction']);

        $user = $this->makeUser($tenant, 'user');
        $this->actingAsUser($user);
        $this->get(route('documents.index', ['cols' => [$def->id]]))->assertOk();

        $response = $this->get(route('documents.index', ['cols' => [$def->id], 'sort' => 'meta:'.$def->id, 'dir' => 'asc']));
        $response->assertOk();
        $html = $response->getContent();
        $this->assertTrue(strpos($html, 'Gamma') < strpos($html, 'Alpha'), 'Direction doit précéder Finances');
        $this->assertTrue(strpos($html, 'Alpha') < strpos($html, 'Beta'), 'Finances doit précéder RH');
    }

    public function test_metadata_numeric_sort_is_numeric(): void
    {
        $tenant = $this->makeTenant();
        $def = $this->makeMeta($tenant, 'Montant HT', 'number', ['Petit' => '10', 'Grand' => '100', 'Moyen' => '50']);

        $user = $this->makeUser($tenant, 'user');
        $this->actingAsUser($user);
        $this->get(route('documents.index', ['cols' => [$def->id]]))->assertOk();

        $response = $this->get(route('documents.index', ['cols' => [$def->id], 'sort' => 'meta:'.$def->id, 'dir' => 'asc']));
        $response->assertOk();
        $html = $response->getContent();
        // Tri numérique : 10 < 50 < 100 (pas alphabétique 10 < 100 < 50).
        $this->assertTrue(strpos($html, '>Petit<') < strpos($html, '>Moyen<'));
        $this->assertTrue(strpos($html, '>Moyen<') < strpos($html, '>Grand<'));
    }

    public function test_metadata_sort_puts_missing_values_last(): void
    {
        $tenant = $this->makeTenant();
        $def = $this->makeMeta($tenant, 'Service', 'text', ['Alpha' => 'Finances', 'Beta' => 'RH']);
        // Document sans valeur.
        $space = $this->makeSpace($tenant);
        $this->makeDocument($tenant, $this->makeUser($tenant, 'user'), $space, ['title' => 'Zeta sans meta']);

        $user = $this->makeUser($tenant, 'user');
        $this->actingAsUser($user);
        $this->get(route('documents.index', ['cols' => [$def->id]]))->assertOk();

        foreach (['asc', 'desc'] as $dir) {
            $response = $this->get(route('documents.index', ['cols' => [$def->id], 'sort' => 'meta:'.$def->id, 'dir' => $dir]));
            $response->assertOk();
            $html = $response->getContent();
            $this->assertTrue(strpos($html, 'Zeta sans meta') > strpos($html, 'Alpha'), "{$dir}: sans valeur doit être dernier");
        }
    }

    public function test_metadata_sort_with_unknown_id_is_ignored(): void
    {
        $tenant = $this->makeTenant();
        $space = $this->makeSpace($tenant);
        $user = $this->makeUser($tenant, 'user');
        $this->actingAsUser($user);
        $this->makeDocument($tenant, $user, $space, ['title' => 'Doc']);

        // ID inexistant → ignoré (tri par défaut), pas d'erreur.
        $this->get(route('documents.index', ['sort' => 'meta:99999', 'dir' => 'asc']))->assertOk();
        // ID non numérique → ignoré.
        $this->get(route('documents.index', ['sort' => 'meta:abc', 'dir' => 'asc']))->assertOk();
    }

    public function test_space_sort_does_not_mix_metadata_values(): void
    {
        $tenant = $this->makeTenant();
        TenantContext::set($tenant->id);

        $spaceA = $this->makeSpace($tenant);
        $spaceB = $this->makeSpace($tenant);

        $def = MetadataDefinition::create([
            'tenant_id' => $tenant->id,
            'name' => 'Service',
            'key' => 'service',
            'type' => 'text',
        ]);

        // Deux documents dans le MÊME espace A, métadonnées différentes.
        $doc1 = $this->makeDocument($tenant, $this->makeUser($tenant, 'user'), $spaceA, ['title' => 'Doc Un']);
        $doc1->metadataValues()->create(['tenant_id' => $tenant->id, 'definition_id' => $def->id, 'value' => 'Finances']);
        $doc2 = $this->makeDocument($tenant, $this->makeUser($tenant, 'user'), $spaceA, ['title' => 'Doc Deux']);
        $doc2->metadataValues()->create(['tenant_id' => $tenant->id, 'definition_id' => $def->id, 'value' => 'RH']);
        // Un document dans l'espace B.
        $this->makeDocument($tenant, $this->makeUser($tenant, 'user'), $spaceB, ['title' => 'Doc Trois']);

        $user = $this->makeUser($tenant, 'user');
        $this->actingAsUser($user);

        $response = $this->get(route('documents.index', ['cols' => [$def->id], 'sort' => 'space_id', 'dir' => 'asc']));
        $response->assertOk();
        $html = $response->getContent();

        // Chaque document affiche SA valeur ('Finances' 1x, 'RH' 1x) — pas de répétition.
        $this->assertSame(1, substr_count($html, '>Finances<'));
        $this->assertSame(1, substr_count($html, '>RH<'));
    }

    public function test_meta_desc_sort_works_without_cols_in_url(): void
    {
        $tenant = $this->makeTenant();
        $def = $this->makeMeta($tenant, 'Montant', 'number', ['Petit' => '10', 'Grand' => '100']);

        $user = $this->makeUser($tenant, 'user');
        $this->actingAsUser($user);

        // Lien direct : sort=meta:{id}&dir=desc SANS cols.
        $response = $this->get(route('documents.index', ['sort' => 'meta:'.$def->id, 'dir' => 'desc']));
        $response->assertOk();
        $html = $response->getContent();
        $this->assertTrue(strpos($html, '>Grand<') < strpos($html, '>Petit<'), 'desc : 100 doit précéder 10');
    }

    public function test_meta_sort_toggles_direction_on_click(): void
    {
        $tenant = $this->makeTenant();
        $def = $this->makeMeta($tenant, 'Service', 'text', ['Alpha' => 'Finances', 'Beta' => 'RH']);

        $user = $this->makeUser($tenant, 'user');
        $this->actingAsUser($user);

        // 1er clic (dir=asc) → l'URL du lien doit passer à desc.
        $response = $this->get(route('documents.index', ['cols' => [$def->id], 'sort' => 'meta:'.$def->id, 'dir' => 'asc']));
        $html = $response->getContent();
        $this->assertStringContainsString('sort=meta%3A'.$def->id.'&amp;dir=desc', $html);
    }
}
