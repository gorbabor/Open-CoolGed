<?php

namespace Tests\Feature;

use App\Services\InstallerService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InstallerTest extends TestCase
{
    private string $lock;

    private string $env;

    private string $sqlite;

    private string $originalDefault;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalDefault = config('database.default');
        $this->lock = storage_path('framework/testing/installed.lock');
        $this->env = storage_path('framework/testing/installer.env');
        $this->sqlite = storage_path('framework/testing/installer.sqlite');

        @unlink($this->lock);
        @unlink($this->env);
        @unlink($this->sqlite);

        config([
            'ged.installed_lock' => $this->lock,
            'ged.env_path' => $this->env,
            // Les tests simulent une instance non installée : la détection « base déjà migrée »
            // (active par défaut en production) est désactivée — elle est testée séparément.
            'ged.installer_detect_existing' => false,
        ]);
    }

    protected function tearDown(): void
    {
        config(['database.default' => $this->originalDefault]);
        DB::purge(InstallerService::CONNECTION);

        @unlink($this->lock);
        @unlink($this->env);
        @unlink($this->sqlite);

        parent::tearDown();
    }

    public function test_install_page_is_available_when_not_installed(): void
    {
        $html = $this->get(route('install.show'))->assertOk()->getContent();
        $this->assertStringContainsString("Installation d'Open-CoolGed", $html);
        $this->assertStringContainsString('Tester la connexion', $html);
        $this->assertStringContainsString('Prérequis', $html);
    }

    public function test_install_page_is_unavailable_once_installed(): void
    {
        file_put_contents($this->lock, 'installed');

        $this->get(route('install.show'))->assertStatus(404);
    }

    public function test_already_migrated_database_blocks_installer_without_lock(): void
    {
        // Détection activée (défaut production) : la base de test est migrée → considérée installée.
        config(['ged.installer_detect_existing' => true]);

        $this->assertTrue(app(InstallerService::class)->isInstalled());
        $this->get(route('install.show'))->assertStatus(404);
        $this->get(route('install.done'))->assertOk();
    }

    public function test_invalid_database_connection_is_reported_without_writing(): void
    {
        $response = $this->from(route('install.show'))->post(route('install.test'), [
            'db_driver' => 'sqlite',
            'db_database' => base_path('database'), // un dossier → connexion impossible
        ]);

        $response->assertRedirect(route('install.show'));
        $response->assertSessionHas('install_error');
        $this->assertFileDoesNotExist($this->env);
        $this->assertFileDoesNotExist($this->lock);
    }

    public function test_full_installation_creates_schema_accounts_and_lock(): void
    {
        $response = $this->post(route('install.run'), [
            'db_driver' => 'sqlite',
            'db_database' => $this->sqlite,
            'app_url' => 'https://ged.test',
            'super_name' => 'Super Root',
            'super_email' => 'root@ged.test',
            'super_password' => 'secret-root-1',
            'org_name' => 'ACME Corp',
            'admin_name' => 'Admin ACME',
            'admin_email' => 'admin@acme.test',
            'admin_password' => 'secret-admin-1',
            'demo_accounts' => '1',
        ]);

        $response->assertRedirect(route('install.done'));

        // Verrou + fichier .env écrits.
        $this->assertFileExists($this->lock);
        $this->assertFileExists($this->env);
        $envContent = file_get_contents($this->env);
        $this->assertStringContainsString('APP_KEY=base64:', $envContent);
        $this->assertStringContainsString('DB_DATABASE='.$this->sqlite, $envContent);
        $this->assertStringContainsString('APP_URL=https://ged.test', $envContent);

        // Schéma migré et comptes créés sur la connexion d'installation.
        $connection = DB::connection(InstallerService::CONNECTION);
        $this->assertTrue($connection->getSchemaBuilder()->hasTable('users'));

        $superAdmin = $connection->table('users')->where('email', 'root@ged.test')->first();
        $this->assertNotNull($superAdmin);
        $this->assertSame(1, (int) $superAdmin->is_super_admin);

        $tenant = $connection->table('tenants')->where('name', 'ACME Corp')->first();
        $this->assertNotNull($tenant);

        $admin = $connection->table('users')->where('email', 'admin@acme.test')->first();
        $this->assertNotNull($admin);
        $this->assertSame((int) $tenant->id, (int) $admin->tenant_id);

        $adminRole = $connection->table('roles')->where('tenant_id', $tenant->id)->where('slug', 'tenant_admin')->first();
        $this->assertNotNull($adminRole);
        $this->assertTrue(
            $connection->table('user_role')->where('user_id', $admin->id)->where('role_id', $adminRole->id)->exists()
        );

        // Comptes de démonstration créés (case cochée).
        $this->assertNotNull($connection->table('users')->where('email', 'user@demo.local')->first());
        $this->assertNotNull($connection->table('users')->where('email', 'validator@demo.local')->first());

        // Assistant inaccessible ensuite ; page de fin accessible.
        $this->get(route('install.show'))->assertStatus(404);
        $this->get(route('install.done'))->assertOk()->assertSee('Installation terminée');
    }
}
