<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;

class GedRestoreCommand extends Command
{
    protected $signature = 'ged:restore {--dir= : Répertoire de sauvegarde (défaut : plus récente)}';

    protected $description = 'Restaure la base de données et les fichiers depuis une sauvegarde';

    public function handle(BackupService $backup): int
    {
        $dir = $this->option('dir');

        if ($dir !== null && ! is_dir($dir)) {
            $this->error('Répertoire de sauvegarde introuvable : '.$dir);

            return self::FAILURE;
        }

        try {
            $result = $backup->restore($dir);
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Restauration réussie depuis : '.$result['dir']);

        return self::SUCCESS;
    }
}
