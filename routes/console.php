<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Sauvegardes (CDG §41) : quotidienne à 02:00 + purge des sauvegardes
 * de plus de 30 jours (intégrée à ged:backup).
 * En cPanel : ajouter la tâche cron  * * * * * php /chemin/artisan schedule:run
 */
Schedule::command('ged:backup')->dailyAt('02:00');

/*
 * Rétention documentaire (CDG §24) : purge périodique des documents
 * dont l'échéance de rétention est dépassée (avant suppression définitive).
 */
Schedule::command('ged:retention')->dailyAt('03:00');

/*
 * Périmètre V02 : alertes de revue documentaire (30 j avant / retard) — quotidien.
 */
Schedule::command('v02:review-alerts')->dailyAt('06:00');
