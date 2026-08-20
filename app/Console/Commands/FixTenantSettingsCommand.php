<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;

class FixTenantSettingsCommand extends Command
{
    protected $signature = 'ged:fix-tenant-settings {--dry-run : Affiche les corrections sans les appliquer}';

    protected $description = 'Répare les paramètres tenant stockés en chaîne (ai_providers, allowed_mimes) en tableaux';

    public function handle(): int
    {
        $fixed = 0;

        foreach (Tenant::withoutGlobalScopes()->cursor() as $tenant) {
            $settings = $tenant->settings ?? [];
            $changedKeys = [];

            foreach (['ai_providers', 'allowed_mimes'] as $key) {
                if (isset($settings[$key]) && is_string($settings[$key])) {
                    $settings[$key] = array_values(array_filter(array_map('trim', explode(',', $settings[$key]))));
                    $changedKeys[] = $key;
                }
            }

            if ($changedKeys === []) {
                continue;
            }

            $this->line(sprintf('[%s] %s : %s', $tenant->id, $tenant->name, implode(', ', $changedKeys)));

            if (! $this->option('dry-run')) {
                $tenant->update(['settings' => $settings]);
            }
            $fixed++;
        }

        $this->info($this->option('dry-run')
            ? sprintf('%d tenant(s) à corriger (dry-run).', $fixed)
            : sprintf('%d tenant(s) corrigé(s).', $fixed));

        return self::SUCCESS;
    }
}
