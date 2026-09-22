<?php

namespace App\Console\Commands;

use App\Services\InstallerService;
use Illuminate\Console\Command;
use Throwable;

class AppInstallCommand extends Command
{
    protected $signature = 'app:install
        {--force : Ne pas demander de confirmation}
        {--db-driver=mysql : mysql ou sqlite}
        {--db-host= : Hôte MySQL}
        {--db-port=3306 : Port MySQL}
        {--db-database= : Nom de la base}
        {--db-username= : Utilisateur MySQL}
        {--db-password= : Mot de passe MySQL}
        {--app-url= : URL publique de l\'application}
        {--super-name= : Nom du super administrateur}
        {--super-email= : Email du super administrateur}
        {--super-password= : Mot de passe du super administrateur}
        {--org-name= : Nom de l\'organisation}
        {--admin-name= : Nom de l\'administrateur tenant}
        {--admin-email= : Email de l\'administrateur tenant}
        {--admin-password= : Mot de passe de l\'administrateur tenant}
        {--demo : Créer les comptes de démonstration (utilisateur, validateur)}';

    protected $description = 'Installation en ligne de commande : .env, migrations, comptes initiaux, verrou';

    public function handle(InstallerService $installer): int
    {
        if ($installer->isInstalled()) {
            $this->error('Application déjà installée (verrou présent).');

            return self::FAILURE;
        }

        if (! $installer->requirementsPassed()) {
            foreach ($installer->requirements() as $check) {
                $this->line(sprintf('[%s] %s', $check['ok'] ? 'OK' : 'KO', $check['label']));
            }
            $this->error('Des prérequis ne sont pas satisfaits.');

            return self::FAILURE;
        }

        $db = [
            'driver' => $this->option('db-driver'),
            'host' => $this->option('db-host') ?: '127.0.0.1',
            'port' => $this->option('db-port'),
            'database' => $this->option('db-database') ?: $this->ask('Base de données'),
            'username' => $this->option('db-username') ?: $this->ask('Utilisateur MySQL', 'root'),
            'password' => $this->option('db-password') ?: (string) $this->secret('Mot de passe MySQL (laisser vide si aucun)'),
            'app_url' => $this->option('app-url') ?: $this->ask("URL de l'application", 'http://localhost'),
        ];

        $admin = [
            'name' => $this->option('super-name') ?: $this->ask('Nom du super administrateur'),
            'email' => $this->option('super-email') ?: $this->ask('Email du super administrateur'),
            'password' => $this->option('super-password') ?: (string) $this->secret('Mot de passe du super administrateur'),
        ];

        $organization = [
            'name' => $this->option('org-name') ?: $this->ask("Nom de l'organisation"),
            'admin_name' => $this->option('admin-name') ?: $this->ask("Nom de l'administrateur tenant"),
            'admin_email' => $this->option('admin-email') ?: $this->ask("Email de l'administrateur tenant"),
            'admin_password' => $this->option('admin-password') ?: (string) $this->secret("Mot de passe de l'administrateur tenant"),
        ];

        $test = $installer->testConnection($db);
        if (! $test['ok']) {
            $this->error($test['message']);

            return self::FAILURE;
        }
        $this->info($test['message']);

        if (! $this->option('force') && ! $this->confirm('Lancer l\'installation (migrations + comptes initiaux + verrou) ?')) {
            $this->line('Installation annulée.');

            return self::SUCCESS;
        }

        try {
            $result = $installer->install($db, $admin, $organization, (bool) $this->option('demo'));
        } catch (Throwable $e) {
            $this->error('Installation interrompue : '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Installation terminée.');
        $this->line('Organisation : '.$result['tenant']->name.' (slug : '.$result['tenant']->slug.')');
        foreach ($result['accounts'] as $role => $email) {
            $this->line(sprintf('- %s : %s', $role, $email));
        }

        return self::SUCCESS;
    }
}
