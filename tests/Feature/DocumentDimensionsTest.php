<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Referential;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class DocumentDimensionsTest extends TestCase
{
    private function makeRefs(Tenant $tenant): array
    {
        $domain = Referential::create(['tenant_id' => $tenant->id, 'type' => 'domain', 'name' => 'Qualité']);
        $process = Referential::create(['tenant_id' => $tenant->id, 'type' => 'process', 'name' => 'Gouvernance documentaire']);
        $site = Referential::create(['tenant_id' => $tenant->id, 'type' => 'site', 'name' => 'Siège']);
        $country = Referential::create(['tenant_id' => $tenant->id, 'type' => 'country', 'name' => 'Côte d\'Ivoire']);

        return compact('domain', 'process', 'site', 'country');
    }

    public function test_manual_create_links_domain_process_and_application(): void
    {
        $tenant = $this->makeTenant();
        $space = $this->makeSpace($tenant);
        $user = $this->makeUser($tenant, 'user');
        $this->actingAsUser($user);
        $refs = $this->makeRefs($tenant);

        $this->post(route('documents.store'), [
            'title' => 'Doc dimensions',
            'space_id' => $space->id,
            'domain_id' => $refs['domain']->id,
            'process_id' => $refs['process']->id,
            'application' => ['site' => $refs['site']->id, 'country' => $refs['country']->id],
            'file' => UploadedFile::fake()->createWithContent('doc.txt', 'contenu', 'text/plain'),
        ])->assertRedirect();

        $doc = Document::withoutGlobalScopes()->where('title', 'Doc dimensions')->first();
        $this->assertNotNull($doc);
        $this->assertSame($refs['domain']->id, $doc->domain_id);
        $this->assertSame($refs['process']->id, $doc->process_id);

        $pivots = $doc->referentials()->get()->keyBy('type');
        $this->assertTrue($pivots->has('site'));
        $this->assertTrue($pivots->has('country'));
        $this->assertSame($refs['site']->id, $pivots['site']->id);
    }

    public function test_manual_create_ignores_foreign_referential(): void
    {
        $tenantA = $this->makeTenant();
        $tenantB = $this->makeTenant();
        $space = $this->makeSpace($tenantA);
        $user = $this->makeUser($tenantA, 'user');
        $this->actingAsUser($user);

        $foreignSite = Referential::create(['tenant_id' => $tenantB->id, 'type' => 'site', 'name' => 'Site B']);

        $this->post(route('documents.store'), [
            'title' => 'Doc étranger',
            'space_id' => $space->id,
            'application' => ['site' => $foreignSite->id],
            'file' => UploadedFile::fake()->createWithContent('doc.txt', 'contenu', 'text/plain'),
        ])->assertRedirect();

        $doc = Document::withoutGlobalScopes()->where('title', 'Doc étranger')->first();
        $this->assertNotNull($doc);
        $this->assertSame(0, $doc->referentials()->count(), 'un référentiel d\'un autre tenant ne doit pas être lié');
    }

    public function test_import_csv_links_domain_process_and_application(): void
    {
        $tenant = $this->makeTenant();
        $admin = $this->makeUser($tenant, 'tenant_admin');
        $this->actingAsUser($admin);

        $csv = "id;code;titre;description;section;lot;famille;statut;proprietaire;domaine;processus;application;criticite;date_application;prochaine_revue;fichier\n"
            ."701;KAE-DIM-701;Doc dimensionné;Desc;Section D;Lot D;Politique;brouillon;;Qualité;Gouvernance documentaire;\"site:Siège;country:Côte d'Ivoire\";standard;;;\n";

        $this->post(route('admin.import-csv'), [
            'csv' => UploadedFile::fake()->createWithContent('import.csv', $csv, 'text/csv'),
        ])->assertRedirect();

        $report = session('import_report');
        $this->assertSame([], $report['errors'] ?? ['REPORT ABSENT'], 'erreurs d\'import: '.json_encode($report));

        $doc = Document::withoutGlobalScopes()->where('document_code', 'KAE-DIM-701')->first();
        $this->assertNotNull($doc);
        $this->assertNotNull($doc->domain_id, 'domaine doit être lié au document');
        $this->assertSame('Qualité', Referential::find($doc->domain_id)->name);
        $this->assertNotNull($doc->process_id, 'processus doit être lié au document');
        $this->assertSame('Gouvernance documentaire', Referential::find($doc->process_id)->name);

        $pivots = $doc->referentials()->get()->keyBy('type');
        $this->assertTrue($pivots->has('site'));
        $this->assertTrue($pivots->has('country'));
    }

    public function test_import_csv_template_includes_new_columns_and_stays_backward_compatible(): void
    {
        $tenant = $this->makeTenant();
        $admin = $this->makeUser($tenant, 'tenant_admin');
        $this->actingAsUser($admin);

        $content = $this->get(route('admin.import-csv.template'))->streamedContent();
        $this->assertStringContainsString('processus', $content);
        $this->assertStringContainsString('application', $content);

        // Ancien CSV à 14 colonnes : toujours accepté (en-tête du fichier fait foi).
        $legacy = "id;code;titre;description;section;lot;famille;statut;proprietaire;domaine;criticite;date_application;prochaine_revue;fichier\n"
            ."702;KAE-DIM-702;Doc ancien;Desc;Section E;Lot E;Manuel;brouillon;;;standard;;;\n";
        $this->post(route('admin.import-csv'), [
            'csv' => UploadedFile::fake()->createWithContent('import.csv', $legacy, 'text/csv'),
        ])->assertRedirect();
        $this->assertDatabaseHas('documents', ['document_code' => 'KAE-DIM-702']);
    }

    public function test_update_metadata_updates_dimension_links(): void
    {
        $tenant = $this->makeTenant();
        $space = $this->makeSpace($tenant);
        $user = $this->makeUser($tenant, 'tenant_admin');
        $this->actingAsUser($user);
        $refs = $this->makeRefs($tenant);

        $doc = $this->makeDocument($tenant, $user, $space, ['title' => 'Doc à relier']);

        $this->post(route('documents.metadata', $doc), [
            'title' => 'Doc à relier',
            'space_id' => $space->id,
            'domain_id' => $refs['domain']->id,
            'process_id' => $refs['process']->id,
            'application' => ['site' => $refs['site']->id],
        ])->assertRedirect();

        $doc->refresh();
        $this->assertSame($refs['domain']->id, $doc->domain_id);
        $this->assertSame($refs['process']->id, $doc->process_id);
        $this->assertTrue($doc->referentials()->wherePivot('type', 'site')->exists());

        // Retirer un lien application → le pivot est vidé.
        $this->post(route('documents.metadata', $doc), [
            'title' => 'Doc à relier',
            'space_id' => $space->id,
            'domain_id' => $refs['domain']->id,
            'process_id' => $refs['process']->id,
            'application' => [],
        ])->assertRedirect();

        $doc->refresh();
        $this->assertSame(0, $doc->referentials()->count());
    }

    public function test_admin_assigns_dimensions_to_users(): void
    {
        $tenant = $this->makeTenant();
        $admin = $this->makeUser($tenant, 'tenant_admin');
        $this->actingAsUser($admin);

        $role = Role::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('slug', 'user')->first();
        $job = Referential::create(['tenant_id' => $tenant->id, 'type' => 'job', 'name' => 'Responsable Qualité']);
        $site = Referential::create(['tenant_id' => $tenant->id, 'type' => 'site', 'name' => 'Siège']);

        $this->post(route('admin.users.store'), [
            'name' => 'User Dimensionné',
            'email' => 'dim@test.local',
            'role_id' => $role->id,
            'password' => 'password123',
            'job_id' => $job->id,
            'site_id' => $site->id,
        ])->assertRedirect();

        $u = User::where('email', 'dim@test.local')->first();
        $this->assertNotNull($u);
        $this->assertSame($job->id, $u->job_id);
        $this->assertSame($site->id, $u->site_id);
        $this->assertContains($job->id, $u->dimensionIds());
        $this->assertContains($site->id, $u->dimensionIds());
    }

    public function test_cards_domain_counts_linked_documents(): void
    {
        $tenant = $this->makeTenant();
        $space = $this->makeSpace($tenant);
        $user = $this->makeUser($tenant, 'user');
        $this->actingAsUser($user);

        $qms = Referential::create(['tenant_id' => $tenant->id, 'type' => 'domain', 'name' => 'QMS']);
        $gov = Referential::create(['tenant_id' => $tenant->id, 'type' => 'domain', 'name' => 'GOV']);

        $d1 = $this->makeDocument($tenant, $user, $space, ['title' => 'Doc QMS 1', 'domain_id' => $qms->id]);
        $this->makeDocument($tenant, $user, $space, ['title' => 'Doc QMS 2', 'domain_id' => $qms->id]);
        $this->makeDocument($tenant, $user, $space, ['title' => 'Doc GOV', 'domain_id' => $gov->id]);

        $response = $this->get(route('documents.index', ['view' => 'cards', 'group' => 'domain']));
        $response->assertOk();
        $html = $response->getContent();

        $this->assertStringContainsString('QMS', $html);
        $this->assertStringContainsString('GOV', $html);
        $this->assertStringContainsString('>2 documents<', $html);
        $this->assertStringContainsString('value='.$qms->id, $html);

        // Clic sur la carte → liste filtrée sur le domaine.
        $list = $this->get(route('documents.index', ['view' => 'list', 'group' => 'domain', 'value' => $qms->id]));
        $list->assertOk();
        $listHtml = $list->getContent();
        $this->assertStringContainsString('Doc QMS 1', $listHtml);
        $this->assertStringNotContainsString('Doc GOV', $listHtml);
    }
}
