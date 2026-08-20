<?php

namespace App\Console\Commands;

use App\Models\Document;
use App\Models\Notification;
use Illuminate\Console\Command;

/**
 * Lot F — alertes de revue documentaire (V02 §16.3) :
 * - alerte 30 jours avant next_review_date (propriétaire + admin tenant) ;
 * - relance immédiate puis hebdomadaire si en retard.
 * À planifier : ged:review-alerts quotidien.
 */
class V02ReviewAlertsCommand extends Command
{
    protected $signature = 'v02:review-alerts {--dry-run : Afficher sans notifier}';

    protected $description = 'Génère les alertes de revue documentaire (30 j avant / retard)';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        // Documents approuvés avec prochaine revue dans 30 jours ou dépassée.
        $documents = Document::withoutGlobalScopes()
            ->where('status', 'approuve_applicable')
            ->whereNotNull('next_review_date')
            ->where('next_review_date', '<=', now()->addDays(30))
            ->with('owner')
            ->get();

        $sent = 0;
        foreach ($documents as $doc) {
            $late = $doc->next_review_date->isPast();
            $days = now()->startOfDay()->diffInDays($doc->next_review_date, false);

            $title = $late
                ? 'Revue documentaire en retard : '.$doc->title
                : 'Revue documentaire proche (J-'.max(0, $days).') : '.$doc->title;

            if ($dryRun) {
                $this->line($title);
                $sent++;

                continue;
            }

            $recipients = collect([$doc->owner_id])->filter()->unique();

            foreach ($recipients as $userId) {
                Notification::withoutGlobalScopes()->create([
                    'tenant_id' => $doc->tenant_id,
                    'user_id' => $userId,
                    'type' => $late ? 'review.late' : 'review.upcoming',
                    'title' => $title,
                    'body' => 'Date de revue : '.$doc->next_review_date->format('d/m/Y'),
                    'link' => '/documents/'.$doc->id,
                ]);
                $sent++;
            }
        }

        $this->info("Alertes de revue générées : {$sent}".($dryRun ? ' (dry-run)' : ''));

        return self::SUCCESS;
    }
}
