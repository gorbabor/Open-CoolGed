<?php

use App\Models\PlatformSettings;

if (! function_exists('app_display_name')) {
    /**
     * Nom affiché de l'application, résolu par couches :
     * 1. Nom de marque du tenant connecté (branding.brand_name) — surcharge par entreprise.
     * 2. Nom de la plateforme (PlatformSettings.app_name, défaut Open-CoolGed).
     */
    function app_display_name(): string
    {
        $user = auth()->user();

        if ($user?->tenant) {
            $brandName = $user->tenant->branding['brand_name'] ?? null;
            if ($brandName !== null && trim($brandName) !== '') {
                return $brandName;
            }
        }

        $platform = PlatformSettings::instance()->get('app_name', null);
        if ($platform !== null && trim($platform) !== '') {
            return $platform;
        }

        return 'Open-CoolGed';
    }
}
