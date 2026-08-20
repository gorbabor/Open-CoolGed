<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\DocumentType;
use App\Models\DocumentVersion;
use App\Services\BackupService;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class BackupRestoreTest extends TestCase
{
    public function test_backup_creates_archive_with_database_and_files(): void
    {
        $tenant = $this->makeTenant();
        $space = $this->makeSpace($tenant);
        $user = $this->makeUser($tenant, 'user');
        $doc = $this->makeDocument($tenant, $user, $space);

        $backup = app(BackupService::class);

        // Backup on a fresh SQLite database: portable JSON export is used.
        $result = $backup->run();

        $this->assertFileExists($result['dir'].'/database.json');
        $this->assertFileExists($result['dir'].'/manifest.json');
        $this->assertDirectoryExists($result['dir'].'/files');

        $manifest = json_decode(File::get($result['dir'].'/manifest.json'), true);
        $this->assertSame('kaeged', $manifest['app']);

        $json = json_decode(File::get($result['dir'].'/database.json'), true);
        $this->assertNotEmpty($json['tenants']);
        $this->assertNotEmpty($json['documents']);
        $this->assertSame($doc->id, $json['documents'][0]['id']);
    }

    public function test_restore_recreates_data_after_loss(): void
    {
        $tenant = $this->makeTenant();
        $space = $this->makeSpace($tenant);
        $user = $this->makeUser($tenant, 'user');
        $doc = $this->makeDocument($tenant, $user, $space, ['content' => 'Contenu critique de sauvegarde.']);

        $backup = app(BackupService::class);
        $result = $backup->run();

        // Simulate data loss: the document and its file disappear.
        $filePath = DocumentVersion::withoutGlobalScopes()
            ->where('document_id', $doc->id)
            ->orderByDesc('id')
            ->value('file_path');
        File::delete(storage_path('app/private/'.$filePath));
        Document::withoutGlobalScopes()->where('id', $doc->id)->forceDelete();

        $this->assertDatabaseMissing('documents', ['id' => $doc->id]);

        // CA-020: restore from the latest backup succeeds.
        $backup->restore($result['dir']);

        $restored = Document::withoutGlobalScopes()->find($doc->id);
        $this->assertNotNull($restored);

        $restoredVersion = DocumentVersion::withoutGlobalScopes()
            ->where('document_id', $restored->id)
            ->orderByDesc('id')
            ->first();
        $this->assertSame('Contenu critique de sauvegarde.', $restoredVersion->extracted_text);
        $this->assertFileExists(storage_path('app/private/'.$restoredVersion->file_path));
    }

    public function test_prune_removes_backups_older_than_30_days(): void
    {
        $backup = app(BackupService::class);
        $dir = $backup->backupDir();

        // Deterministic state: storage/backups persists between runs.
        File::deleteDirectory($dir);
        File::makeDirectory($dir.'/20260101_010000', 0755, true);
        File::makeDirectory($dir.'/'.now()->format('Ymd_His'), 0755, true);

        $pruned = $backup->prune();

        $this->assertSame(1, $pruned);
        $this->assertDirectoryDoesNotExist($dir.'/20260101_010000');
        $this->assertDirectoryExists($dir.'/'.now()->format('Ymd_His'));
    }

    public function test_retention_command_archives_expired_documents(): void
    {
        $tenant = $this->makeTenant();
        $space = $this->makeSpace($tenant);
        $user = $this->makeUser($tenant, 'user');

        $type = DocumentType::create([
            'tenant_id' => $tenant->id,
            'name' => 'Facture',
            'slug' => 'facture-'.uniqid(),
            'retention_days' => 365,
        ]);

        $old = $this->makeDocument($tenant, $user, $space, ['document_type_id' => $type->id]);
        Document::withoutGlobalScopes()->where('id', $old->id)->update(['created_at' => now()->subYears(2)]);
        $recent = $this->makeDocument($tenant, $user, $space, ['document_type_id' => $type->id]);

        $this->artisan('ged:retention')->assertSuccessful();

        $old->refresh();
        $recent->refresh();

        $this->assertSame('expired', $old->status);
        $this->assertNotSame('expired', $recent->status);
    }
}
