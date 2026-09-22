<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class InstallerService
{
    public const CONNECTION = 'installer';

    /**
     * Une instance est « installée » si le verrou est posé — ou, à défaut, si la base
     * est déjà migrée (protection des instances existantes : l'assistant ne doit jamais
     * pouvoir réécrire un .env ni rejouer une installation en production).
     */
    public function isInstalled(): bool
    {
        if (is_file(config('ged.installed_lock'))) {
            return true;
        }

        if (! config('ged.installer_detect_existing', true)) {
            return false;
        }

        try {
            return Schema::hasTable('migrations') && Schema::hasTable('users');
        } catch (Throwable) {
            return false;
        }
    }

    public function lock(): void
    {
        $path = config('ged.installed_lock');
        @mkdir(dirname($path), 0775, true);
        file_put_contents($path, 'installed at '.now()->toDateTimeString().PHP_EOL);
    }

    /** Rapport de prérequis : [ ['label' => ..., 'ok' => bool, 'hint' => ?string], ... ]. */
    public function requirements(): array
    {
        $envPath = config('ged.env_path');

        $checks = [
            ['label' => 'PHP ≥ 8.4.1', 'ok' => PHP_VERSION_ID >= 80401, 'hint' => 'Version détectée : '.PHP_VERSION],
            ['label' => 'Extension mbstring', 'ok' => extension_loaded('mbstring'), 'hint' => null],
            ['label' => 'Extension openssl', 'ok' => extension_loaded('openssl'), 'hint' => null],
            ['label' => 'Extension pdo_mysql', 'ok' => extension_loaded('pdo_mysql'), 'hint' => null],
            ['label' => 'Extension curl', 'ok' => extension_loaded('curl'), 'hint' => null],
            ['label' => 'Extension gd', 'ok' => extension_loaded('gd'), 'hint' => null],
            ['label' => 'Extension zip', 'ok' => extension_loaded('zip'), 'hint' => null],
            ['label' => 'Extension intl', 'ok' => extension_loaded('intl'), 'hint' => null],
            [
                'label' => 'Écriture du fichier .env',
                'ok' => is_writable(file_exists($envPath) ? $envPath : dirname($envPath)),
                'hint' => $envPath,
            ],
            ['label' => 'Écriture de storage/', 'ok' => is_writable(storage_path()), 'hint' => null],
            ['label' => 'Écriture de bootstrap/cache', 'ok' => is_writable(base_path('bootstrap/cache')), 'hint' => null],
        ];

        return $checks;
    }

    public function requirementsPassed(): bool
    {
        foreach ($this->requirements() as $check) {
            if (! $check['ok']) {
                return false;
            }
        }

        return true;
    }

    /** Teste une connexion base de données sans rien écrire. */
    public function testConnection(array $db): array
    {
        try {
            $this->configureConnection($db);
            DB::connection(self::CONNECTION)->getPdo();

            return ['ok' => true, 'message' => 'Connexion réussie.'];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => 'Échec de la connexion : '.$e->getMessage()];
        } finally {
            DB::purge(self::CONNECTION);
        }
    }

    /**
     * Parcours complet : .env, migrations, comptes initiaux, verrou.
     *
     * @return array{tenant: Tenant, accounts: array<int, string>}
     */
    public function install(array $db, array $admin, array $organization, bool $withDemoAccounts): array
    {
        $this->configureConnection($db);

        $this->writeEnv([
            'APP_NAME' => 'Open-CoolGed',
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
            'APP_URL' => $db['app_url'],
            'APP_KEY' => 'base64:'.base64_encode(random_bytes(32)),
            'DB_CONNECTION' => $db['driver'],
            'DB_HOST' => $db['host'] ?? '',
            'DB_PORT' => (string) ($db['port'] ?? ''),
            'DB_DATABASE' => $db['database'],
            'DB_USERNAME' => $db['username'] ?? '',
            'DB_PASSWORD' => $db['password'] ?? '',
        ]);

        Artisan::call('migrate', ['--force' => true]);

        SystemRoleService::seedPermissions();

        $superAdmin = User::create([
            'tenant_id' => null,
            'name' => $admin['name'],
            'email' => $admin['email'],
            'password' => $admin['password'],
            'is_super_admin' => true,
            'status' => 'active',
        ]);

        $tenant = Tenant::create([
            'name' => $organization['name'],
            'slug' => Str::slug($organization['name']) ?: 'tenant-'.Str::lower(Str::random(6)),
            'status' => 'active',
            'plan' => 'standard',
            'storage_quota_mb' => 5120,
            'user_quota' => 50,
            'max_file_size_mb' => 50,
            'settings' => [],
        ]);

        $roles = app(SystemRoleService::class)->ensureFor($tenant);

        $tenantAdmin = User::create([
            'tenant_id' => $tenant->id,
            'name' => $organization['admin_name'],
            'email' => $organization['admin_email'],
            'password' => $organization['admin_password'],
            'status' => 'active',
        ]);
        $tenantAdmin->roles()->attach($roles['tenant_admin']->id, ['tenant_id' => $tenant->id]);
        app(PersonalSpaceService::class)->ensure($tenantAdmin);

        $accounts = ['super_admin' => $superAdmin->email, 'admin' => $tenantAdmin->email];

        if ($withDemoAccounts) {
            foreach ([['Utilisateur Démo', 'user@demo.local', 'user'], ['Validateur Démo', 'validator@demo.local', 'validator']] as [$name, $email, $roleSlug]) {
                $user = User::create([
                    'tenant_id' => $tenant->id,
                    'name' => $name,
                    'email' => $email,
                    'password' => 'password123',
                    'status' => 'active',
                ]);
                $user->roles()->attach($roles[$roleSlug]->id, ['tenant_id' => $tenant->id]);
                app(PersonalSpaceService::class)->ensure($user);
                $accounts[$roleSlug] = $email;
            }
        }

        $this->lock();

        return ['tenant' => $tenant, 'accounts' => $accounts];
    }

    /** Écrit (ou met à jour) des clés dans le fichier .env. */
    public function writeEnv(array $values): void
    {
        $path = config('ged.env_path');
        $content = is_file($path) ? file_get_contents($path) : '';
        if (trim($content) === '') {
            $example = base_path('.env.example');
            $content = is_file($example) ? file_get_contents($example) : '';
        }

        foreach ($values as $key => $value) {
            $line = $key.'='.(preg_match('/\s/', (string) $value) ? '"'.$value.'"' : $value);
            if (preg_match('/^'.preg_quote($key, '/').'=.*$/m', $content)) {
                $content = preg_replace('/^'.preg_quote($key, '/').'=.*$/m', $line, $content, 1);
            } else {
                $content = rtrim($content, "\r\n").PHP_EOL.$line.PHP_EOL;
            }
        }

        file_put_contents($path, $content);
    }

    /** Configure une connexion Laravel dédiée à l'installation (mysql ou sqlite). */
    public function configureConnection(array $db): void
    {
        if (($db['driver'] ?? 'mysql') === 'sqlite') {
            config(['database.connections.'.self::CONNECTION => [
                'driver' => 'sqlite',
                'database' => $db['database'],
                'prefix' => '',
                'foreign_key_constraints' => true,
            ]]);
        } else {
            config(['database.connections.'.self::CONNECTION => [
                'driver' => 'mysql',
                'host' => $db['host'] ?? '127.0.0.1',
                'port' => $db['port'] ?? '3306',
                'database' => $db['database'],
                'username' => $db['username'] ?? '',
                'password' => $db['password'] ?? '',
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'strict' => true,
            ]]);
        }

        DB::purge(self::CONNECTION);
        config(['database.default' => self::CONNECTION]);
    }
}
