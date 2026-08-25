<?php

namespace App\Providers;

use App\Services\AiService;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Singleton so registered providers (mock, failing, http…) are shared app-wide.
        $this->app->singleton(AiService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Pagination stylée Bootstrap 5 (alignée avec le thème) — le view Tailwind
        // par défaut s'affichait sans CSS Tailwind (flèches décalées vers le bas).
        Paginator::useBootstrapFive();
    }
}
