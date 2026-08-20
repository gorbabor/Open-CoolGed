<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;

class GedBackupCommand extends Command
{
    protected $signature = 'ged:backup {--prune-only : Supprimer uniquement les sauvegardes périmées}';

    protected $description = 'Sauvegarde la base de données et les fichiers documents (rétention 30 jours)';

    public function handle(BackupService $backup): int
    {
        if ($this->option('prune-only')) {
            $pruned = $backup->prune();
            $this->info("Sauvegardes périmées supprimées : {$pruned}");

            return self::SUCCESS;
        }

        $result = $backup->run();

        $this->info('Sauvegarde créée : '.$result['dir']);
        $this->line('  - dump SQL : '.($result['sql'] ? 'oui' : 'non (export JSON utilisé)'));
        $this->line('  - export JSON : '.($result['json'] ? 'oui' : 'non'));
        $this->line('  - sauvegardes périmées supprimées : '.$result['pruned']);

        return self::SUCCESS;
    }
}
