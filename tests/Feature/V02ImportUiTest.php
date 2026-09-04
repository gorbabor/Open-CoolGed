<?php

namespace Tests\Feature;

use App\Models\Document;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class V02ImportUiTest extends TestCase
{
    public function test_import_csv_page_requires_admin_and_renders(): void
    {
        $tenant = $this->makeTenant();
        $admin = $this->makeUser($tenant, 'tenant_admin');
        $this->actingAsUser($admin);

        $this->get(route('admin.import-csv'))
            ->assertOk()
            ->assertSee('Import CSV')
            ->assertSee('Télécharger le modèle Excel')
            ->assertSee('Template CSV');
    }

    public function test_import_csv_template_downloads_csv(): void
    {
        $tenant = $this->makeTenant();
        $admin = $this->makeUser($tenant, 'tenant_admin');
        $this->actingAsUser($admin);

        $response = $this->get(route('admin.import-csv.template'));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $content = $response->streamedContent();
        $this->assertStringContainsString('id;code;titre;description;section;lot;famille;statut;proprietaire;domaine;processus;application;criticite;date_application;prochaine_revue;fichier', $content);
        $this->assertStringContainsString('fichier', $content);
    }

    public function test_import_csv_creates_documents_without_file(): void
    {
        $tenant = $this->makeTenant();
        $admin = $this->makeUser($tenant, 'tenant_admin');
        $this->actingAsUser($admin);

        $csv = "id;code;titre;description;section;lot;famille;statut;proprietaire;domaine;criticite;date_application;prochaine_revue;fichier\n"
            ."101;KAE-TEST-101;Doc test 101;Desc 101;Section Test;Lot Test;Politique;brouillon;;;standard;;;\n"
            ."102;KAE-TEST-102;Doc test 102;Desc 102;Section Test;Lot Test;Manuel;brouillon;;;standard;;;\n";

        $this->post(route('admin.import-csv'), [
            'csv' => UploadedFile::fake()->createWithContent('import.csv', $csv, 'text/csv'),
        ])->assertRedirect()->assertSessionHas('import_report');

        $this->assertDatabaseHas('documents', ['reference' => '101', 'document_code' => 'KAE-TEST-101']);
        $this->assertDatabaseHas('documents', ['reference' => '102', 'document_code' => 'KAE-TEST-102']);
        $this->assertDatabaseHas('spaces', ['name' => 'Section Test']);
        $this->assertDatabaseHas('document_types', ['name' => 'Politique']);
    }

    public function test_import_csv_attaches_file_when_path_exists(): void
    {
        $tenant = $this->makeTenant();
        $admin = $this->makeUser($tenant, 'tenant_admin');
        $this->actingAsUser($admin);

        // Fichier PDF temporaire sur le serveur.
        $filePath = tempnam(sys_get_temp_dir(), 'import').'.pdf';
        file_put_contents($filePath, '%PDF-1.4 test content');

        $csv = "id;code;titre;description;section;lot;famille;statut;proprietaire;domaine;criticite;date_application;prochaine_revue;fichier\n"
            ."201;KAE-TEST-201;Doc avec fichier;Desc;Section F;Lot F;Politique;brouillon;;;standard;;;{$filePath}\n";

        $this->post(route('admin.import-csv'), [
            'csv' => UploadedFile::fake()->createWithContent('import.csv', $csv, 'text/csv'),
        ])->assertRedirect();

        $doc = Document::withoutGlobalScopes()->where('document_code', 'KAE-TEST-201')->first();
        $this->assertNotNull($doc);
        $this->assertNotNull($doc->currentVersion, 'le fichier doit être attaché (version 1.0)');
        $this->assertSame('application/pdf', $doc->currentVersion->mime_type);

        @unlink($filePath);
    }

    public function test_import_csv_reports_missing_file_but_creates_document(): void
    {
        $tenant = $this->makeTenant();
        $admin = $this->makeUser($tenant, 'tenant_admin');
        $this->actingAsUser($admin);

        $csv = "id;code;titre;description;section;lot;famille;statut;proprietaire;domaine;criticite;date_application;prochaine_revue;fichier\n"
            ."301;KAE-TEST-301;Doc sans fichier;Desc;Section G;Lot G;Politique;brouillon;;;standard;;;/chemin/inexistant.pdf\n";

        $this->post(route('admin.import-csv'), [
            'csv' => UploadedFile::fake()->createWithContent('import.csv', $csv, 'text/csv'),
        ])->assertRedirect();

        $this->assertDatabaseHas('documents', ['document_code' => 'KAE-TEST-301']);
        $report = session('import_report');
        $this->assertNotEmpty($report);
        $this->assertArrayHasKey('file_errors', $report);
    }

    public function test_import_csv_rejects_duplicates(): void
    {
        $tenant = $this->makeTenant();
        $admin = $this->makeUser($tenant, 'tenant_admin');
        $this->actingAsUser($admin);

        $csv = "id;code;titre;description;section;lot;famille;statut;proprietaire;domaine;criticite;date_application;prochaine_revue;fichier\n"
            ."401;KAE-TEST-401;Doc A;Desc;Section H;Lot H;Politique;brouillon;;;standard;;;\n"
            ."401;KAE-TEST-401;Doc A bis;Desc;Section H;Lot H;Politique;brouillon;;;standard;;;\n";

        $this->post(route('admin.import-csv'), [
            'csv' => UploadedFile::fake()->createWithContent('import.csv', $csv, 'text/csv'),
        ])->assertRedirect();

        $report = session('import_report');
        $this->assertNotEmpty($report['errors']);
        $this->assertSame(1, Document::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());
    }

    public function test_import_csv_rejects_invalid_status(): void
    {
        $tenant = $this->makeTenant();
        $admin = $this->makeUser($tenant, 'tenant_admin');
        $this->actingAsUser($admin);

        $csv = "id;code;titre;description;section;lot;famille;statut;proprietaire;domaine;criticite;date_application;prochaine_revue;fichier\n"
            ."501;KAE-TEST-501;Doc statut;Desc;Section I;Lot I;Politique;statut_invalide;;;standard;;;\n";

        $this->post(route('admin.import-csv'), [
            'csv' => UploadedFile::fake()->createWithContent('import.csv', $csv, 'text/csv'),
        ])->assertRedirect();

        $report = session('import_report');
        $this->assertNotEmpty($report['errors']);
        $this->assertSame(0, Document::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());
    }
}
