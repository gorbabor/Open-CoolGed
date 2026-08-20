<?php

namespace App\Console\Commands;

use App\Models\Document;
use App\Models\DocumentType;
use Illuminate\Console\Command;

/**
 * Rétention (CDG §24 / RM-030) : les documents dont la durée de conservation
 * (retention_days du type documentaire ou expiration_at) est dépassée passent
 * à l'état "expired" puis sont archivés. La suppression définitive reste une
 * décision humaine, auditée.
 */
class GedRetentionCommand extends Command
{
    protected $signature = 'ged:retention {--dry-run : Afficher sans modifier}';

    protected $description = 'Applique les règles de rétention documentaire (échéances)';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        // CLI: no authenticated user, so no tenant context — process all tenants.
        $types = DocumentType::withoutGlobalScopes()->get(['id', 'retention_days']);
        $expired = collect();

        foreach ($types as $type) {
            if ($type->retention_days === null) {
                continue;
            }

            $threshold = now()->subDays($type->retention_days);
            $ids = Document::withoutGlobalScopes()
                ->where('document_type_id', $type->id)
                ->where('status', '!=', 'expired')
                ->where('status', '!=', 'archived')
                ->where('created_at', '<', $threshold)
                ->pluck('id');

            $expired = $expired->merge($ids);
        }

        $explicit = Document::withoutGlobalScopes()
            ->whereNotNull('expiration_at')
            ->where('expiration_at', '<', now())
            ->whereNotIn('status', ['expired', 'archived'])
            ->pluck('id');

        $expired = $expired->merge($explicit)->unique()->values();

        $this->info('Documents arrivés à échéance : '.$expired->count().($dryRun ? ' (dry-run)' : ''));

        if ($dryRun) {
            return self::SUCCESS;
        }

        Document::withoutGlobalScopes()->whereIn('id', $expired)->update([
            'status' => 'expired',
            'archived_at' => now(),
        ]);

        return self::SUCCESS;
    }
}
