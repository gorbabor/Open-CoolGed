<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\BackupService;
use App\Services\TenantResetService;
use Illuminate\Console\Command;

class TenantResetCommand extends Command
{
    protected $signature = 'tenant:reset {tenant : ID ou slug du tenant}
        {--dry-run : Affiche ce qui sera purgé sans rien supprimer}
        {--force : Confirme le reset sans invite (CLI non interactive)}';

    protected $description = 'Purge le contenu métier d\'un tenant (documents, espaces, workflows…) en conservant tenant, utilisateurs, rôles et paramètres';

    public function handle(BackupService $backup, TenantResetService $reset): int
    {
        $tenant = Tenant::withoutGlobalScopes()
            ->where('id', (int) $this->argument('tenant'))
            ->orWhere('slug', $this->argument('tenant'))
            ->first();

        if (! $tenant) {
            $this->error('Tenant introuvable.');

            return self::FAILURE;
        }

        $this->line("Tenant : {$tenant->name} (id {$tenant->id})");
        $this->line('Contenu purgé : documents, versions, fichiers, espaces, dossiers, types, métadonnées, tags, référentiels, workflows, notifications, partages, audits, jobs IA.');
        $this->line('Conservé : tenant, utilisateurs, rôles, groupes, paramètres, branding.');

        if ($this->option('dry-run')) {
            $this->info('Dry-run : aucun changement effectué.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Confirmer le reset complet de ce tenant ? (irréversible sans sauvegarde)', false)) {
            $this->warn('Reset annulé.');

            return self::SUCCESS;
        }

        $this->line('Sauvegarde préalable…');
        try {
            $result = $backup->run();
            $this->line('Sauvegarde créée : '.$result['dir']);
        } catch (\Throwable $e) {
            $this->error('La sauvegarde préalable a échoué — reset annulé : '.$e->getMessage());

            return self::FAILURE;
        }

        $counts = $reset->reset($tenant);
        $this->info('Reset terminé : '.$counts['documents'].' document(s) supprimé(s), '.$counts['files_deleted'].' fichier(s) effacé(s).');

        return self::SUCCESS;
    }
}
