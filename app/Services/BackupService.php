<?php

namespace App\Services;

use Illuminate\Database\MySqlConnection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/**
 * Backup / restore (CDG §41, CA-020).
 * - Backup : dump SQL (mysqldump si disponible, sinon export JSON portable)
 *   + copie des fichiers stockés hors webroot + rétention 30 jours.
 * - Restore : réimport du dump + restauration des fichiers.
 */
class BackupService
{
    private const RETENTION_DAYS = 30;

    /** Tables in dependency-safe order for the portable JSON export. */
    private const TABLES = [
        'tenants', 'users', 'groups', 'group_user', 'roles', 'permissions',
        'role_permission', 'user_role', 'spaces', 'folders', 'document_types',
        'documents', 'document_versions', 'tags', 'document_tag',
        'metadata_definitions', 'metadata_values', 'workflows', 'workflow_steps',
        'workflow_instances', 'workflow_tasks', 'comments', 'notifications',
        'audit_logs', 'shares', 'ai_jobs', 'ai_results', 'office_sessions',
        'api_tokens', 'rag_chunks', 'migrations',
    ];

    public function backupDir(): string
    {
        return storage_path('backups');
    }

    public function run(): array
    {
        $stamp = now()->format('Ymd_His');
        $dir = $this->backupDir().DIRECTORY_SEPARATOR.$stamp;
        File::makeDirectory($dir, 0755, true);

        $sql = $this->dumpSql();
        $json = $this->dumpJson();

        if ($sql !== null) {
            File::put($dir.'/database.sql', $sql);
        }
        if ($json !== null) {
            File::put($dir.'/database.json', json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        $filesDir = $dir.'/files';
        File::makeDirectory($filesDir, 0755, true);
        $source = storage_path('app/private');
        if (is_dir($source)) {
            File::copyDirectory($source, $filesDir);
        }

        File::put($dir.'/manifest.json', json_encode([
            'created_at' => now()->toIso8601String(),
            'app' => 'kaeged',
            'db' => config('database.default'),
            'files' => File::exists($source) ? count(File::allFiles($source)) : 0,
        ], JSON_PRETTY_PRINT));

        $pruned = $this->prune();

        return ['dir' => $dir, 'sql' => $sql !== null, 'json' => $json !== null, 'pruned' => $pruned];
    }

    /** List backups newest first. */
    public function list(): array
    {
        if (! is_dir($this->backupDir())) {
            return [];
        }

        $dirs = array_filter(glob($this->backupDir().'/*'), 'is_dir');
        usort($dirs, fn ($a, $b) => strcmp($b, $a));

        return $dirs;
    }

    /** Retention: delete backups older than 30 days. */
    public function prune(): int
    {
        $removed = 0;
        foreach ($this->list() as $dir) {
            $stamp = basename($dir);
            $date = \DateTime::createFromFormat('Ymd_His', $stamp);
            if ($date && $date < now()->subDays(self::RETENTION_DAYS)->toDateTime()) {
                File::deleteDirectory($dir);
                $removed++;
            }
        }

        return $removed;
    }

    public function restore(?string $dir = null): array
    {
        $dir = $dir ?? ($this->list()[0] ?? null);

        if (! $dir || ! is_dir($dir)) {
            throw new \RuntimeException('Aucune sauvegarde disponible.');
        }

        $sql = $dir.'/database.sql';
        if (is_file($sql)) {
            $this->restoreSql($sql);
        } elseif (is_file($dir.'/database.json')) {
            $this->restoreJson($dir.'/database.json');
        } else {
            throw new \RuntimeException('Sauvegarde sans contenu de base de données.');
        }

        $filesDir = $dir.'/files';
        if (is_dir($filesDir)) {
            $target = storage_path('app/private');
            File::deleteDirectory($target);
            File::copyDirectory($filesDir, $target);
        }

        return ['dir' => $dir];
    }

    private function dumpSql(): ?string
    {
        $mysqldump = config('ged.mysqldump_path', 'mysqldump');
        $connection = DB::connection();

        if (! $connection instanceof MySqlConnection) {
            return null;
        }

        $config = $connection->getConfig();
        $cmd = sprintf(
            '"%s" -h %s -P %s -u %s --single-transaction --routines %s %s',
            $mysqldump,
            $config['host'],
            $config['port'],
            $config['username'],
            ($config['password'] ?? '') !== '' ? '-p'.escapeshellarg($config['password']) : '',
            $config['database']
        );

        $result = Process::run($cmd);

        return $result->successful() ? $result->output() : null;
    }

    private function dumpJson(): array
    {
        $data = [];

        foreach (self::TABLES as $table) {
            if (! \Schema::hasTable($table)) {
                continue;
            }

            $data[$table] = DB::table($table)->get()->map(fn ($row) => (array) $row)->all();
        }

        return $data;
    }

    private function restoreSql(string $sqlFile): void
    {
        $mysql = config('ged.mysql_path', 'mysql');
        $connection = DB::connection();

        if (! $connection instanceof MySqlConnection) {
            throw new \RuntimeException('Le dump SQL requiert MySQL. Utilisez l\'export JSON.');
        }

        $config = $connection->getConfig();
        $cmd = sprintf(
            '"%s" -h %s -P %s -u %s %s %s < "%s"',
            $mysql,
            $config['host'],
            $config['port'],
            $config['username'],
            ($config['password'] ?? '') !== '' ? '-p'.escapeshellarg($config['password']) : '',
            $config['database'],
            $sqlFile
        );

        $result = Process::run($cmd);

        if (! $result->successful()) {
            throw new \RuntimeException('Restauration SQL échouée : '.$result->errorOutput());
        }
    }

    private function restoreJson(string $jsonFile): void
    {
        $data = json_decode(File::get($jsonFile), true);

        if (! is_array($data)) {
            throw new \RuntimeException('Export JSON invalide.');
        }

        DB::statement(DB::connection() instanceof MySqlConnection
            ? 'SET FOREIGN_KEY_CHECKS=0'
            : 'PRAGMA foreign_keys = OFF');

        try {
            foreach (self::TABLES as $table) {
                if (! isset($data[$table])) {
                    continue;
                }

                DB::table($table)->delete();
            }

            foreach (self::TABLES as $table) {
                foreach ($data[$table] ?? [] as $row) {
                    DB::table($table)->insert($row);
                }
            }
        } finally {
            DB::statement(DB::connection() instanceof MySqlConnection
                ? 'SET FOREIGN_KEY_CHECKS=1'
                : 'PRAGMA foreign_keys = ON');
        }
    }
}
