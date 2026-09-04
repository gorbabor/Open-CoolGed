<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\Tenant;
use App\Services\SystemRoleService;
use Illuminate\Console\Command;

class RolesSyncCommand extends Command
{
    protected $signature = 'roles:sync {--dry-run : Affiche les corrections sans les appliquer}';

    protected $description = 'Synchronise les rôles système sur tous les tenants (ajoute les permissions manquantes, crée les rôles absents)';

    public function handle(): int
    {
        $service = app(SystemRoleService::class);
        SystemRoleService::seedPermissions();
        $dryRun = $this->option('dry-run');
        $tenantsFixed = 0;
        $rolesCreated = 0;
        $permsAdded = 0;

        foreach (Tenant::withoutGlobalScopes()->cursor() as $tenant) {
            $tenantChanged = false;

            foreach (SystemRoleService::ROLE_DEFS as $slug => $permSlugs) {
                $role = Role::withoutGlobalScopes()
                    ->where('tenant_id', $tenant->id)
                    ->where('slug', $slug)
                    ->first();

                if (! $role) {
                    $this->line(sprintf('[%s] %s : rôle système « %s » absent', $tenant->id, $tenant->name, $slug));
                    $rolesCreated++;
                    $tenantChanged = true;

                    if (! $dryRun) {
                        $service->ensureFor($tenant);
                        $role = Role::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('slug', $slug)->first();
                    }
                }

                if (! $role) {
                    continue;
                }

                foreach ($permSlugs as $permSlug) {
                    $has = $role->permissions()->where('permissions.slug', $permSlug)->exists();
                    if (! $has) {
                        $this->line(sprintf('[%s] %s : %s + %s', $tenant->id, $tenant->name, $slug, $permSlug));
                        $permsAdded++;
                        $tenantChanged = true;

                        if (! $dryRun) {
                            $service->ensureFor($tenant);
                            break; // ensureFor re-synchronise tout le rôle — on repart sur le rôle suivant
                        }
                    }
                }
            }

            if ($tenantChanged) {
                $tenantsFixed++;
            }
        }

        $this->info(sprintf(
            '%s : %d tenant(s) concerné(s), %d rôle(s) créé(s), %d permission(s) ajoutée(s).',
            $dryRun ? 'Simulation (dry-run)' : 'Synchronisation',
            $tenantsFixed,
            $rolesCreated,
            $permsAdded
        ));

        return self::SUCCESS;
    }
}
