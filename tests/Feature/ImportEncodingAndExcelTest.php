<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Services\XlsxService;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ImportEncodingAndExcelTest extends TestCase
{
    private const HEADER = 'id;code;titre;description;section;lot;famille;statut;proprietaire;domaine;processus;application;criticite;date_application;prochaine_revue;fichier';

    public function test_csv_latin1_accents_are_converted(): void
    {
        $tenant = $this->makeTenant();
        $admin = $this->makeUser($tenant, 'tenant_admin');
        $this->actingAsUser($admin);

        // Contenu en Windows-1252 : « Définir les règles » encodé latin1 (é = \xE9).
        $utf8 = "id;code;titre;description;section;lot;famille;statut;proprietaire;domaine;processus;application;criticite;date_application;prochaine_revue;fichier\n"
            ."900;KAE-ENC-900;Politique définie;Définir les règles documentaires;Section Enc;Lot Enc;Politique;brouillon;;;Gouvernance documentaire;;standard;;;\n";
        $latin1 = mb_convert_encoding($utf8, 'Windows-1252', 'UTF-8');

        $this->post(route('admin.import-csv'), [
            'csv' => UploadedFile::fake()->createWithContent('import-ansi.csv', $latin1, 'text/csv'),
        ])->assertRedirect();

        $doc = Document::withoutGlobalScopes()->where('document_code', 'KAE-ENC-900')->first();
        $this->assertNotNull($doc, 'document créé malgré l\'encodage ANSI');
        $this->assertSame('Politique définie', $doc->title);
        $this->assertSame('Définir les règles documentaires', $doc->description);
    }

    public function test_import_row_error_does_not_block_other_rows(): void
    {
        $tenant = $this->makeTenant();
        $admin = $this->makeUser($tenant, 'tenant_admin');
        $this->actingAsUser($admin);

        // Ligne 2 : statut invalide (rejetée) ; ligne 3 : valide (importée).
        $line = fn (array $values) => implode(';', array_pad($values, 16, ''))."\n";
        $csv = self::HEADER."\n"
            .$line(['901', 'KAE-TOL-901', 'Bad statut', '', 'Section T', 'Lot T', 'Politique', 'statut_invalide'])
            .$line(['902', 'KAE-TOL-902', 'Bon document', '', 'Section T', 'Lot T', 'Politique', 'brouillon']);

        $this->post(route('admin.import-csv'), [
            'csv' => UploadedFile::fake()->createWithContent('import.csv', $csv, 'text/csv'),
        ])->assertRedirect();

        $report = session('import_report');
        $this->assertNotEmpty($report['errors']);
        $this->assertStringContainsString('Ligne 2', $report['errors'][0]);
        $this->assertSame(1, Document::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count(), 'la ligne valide est importée malgré l\'erreur de la ligne 2');
        $this->assertDatabaseHas('documents', ['document_code' => 'KAE-TOL-902']);
    }

    public function test_xlsx_import_creates_documents_with_accents(): void
    {
        $tenant = $this->makeTenant();
        $admin = $this->makeUser($tenant, 'tenant_admin');
        $this->actingAsUser($admin);

        $header = ['id', 'code', 'titre', 'description', 'section', 'lot', 'famille', 'statut', 'proprietaire', 'domaine', 'processus', 'application', 'criticite', 'date_application', 'prochaine_revue', 'fichier'];
        $rows = [
            ['910', 'KAE-XLS-910', 'Procédure qualité', 'Définir les règles qualité', 'Section X', 'Lot X', 'Procédure', 'brouillon', '', '', '', '', 'standard', '', '', ''],
        ];

        $path = tempnam(sys_get_temp_dir(), 'import').'.xlsx';
        XlsxService::writeTemplate($path, $header, $rows);

        $this->post(route('admin.import-csv'), [
            'csv' => new UploadedFile($path, 'import.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
        ])->assertRedirect();

        $doc = Document::withoutGlobalScopes()->where('document_code', 'KAE-XLS-910')->first();
        $this->assertNotNull($doc, 'document créé depuis le xlsx');
        $this->assertSame('Procédure qualité', $doc->title);
        $this->assertSame('Définir les règles qualité', $doc->description);

        @unlink($path);
    }

    public function test_xls_legacy_format_is_rejected_with_clear_error(): void
    {
        $tenant = $this->makeTenant();
        $admin = $this->makeUser($tenant, 'tenant_admin');
        $this->actingAsUser($admin);

        $this->post(route('admin.import-csv'), [
            'csv' => UploadedFile::fake()->createWithContent('import.xls', 'fake-biff', 'application/vnd.ms-excel'),
        ])->assertRedirect();

        $report = session('import_report');
        $this->assertNotEmpty($report['errors']);
        $this->assertStringContainsString('xls', mb_strtolower($report['errors'][0]));
        $this->assertSame(0, Document::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());
    }

    public function test_xlsx_template_downloads(): void
    {
        $tenant = $this->makeTenant();
        $admin = $this->makeUser($tenant, 'tenant_admin');
        $this->actingAsUser($admin);

        $response = $this->get(route('admin.import-csv.template-xlsx'));
        $response->assertOk();
        $this->assertStringContainsString('spreadsheetml', $response->headers->get('Content-Type'));
    }
}
