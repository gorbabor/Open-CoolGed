<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;

class MenuService
{
    public const ITEMS = [
        'dashboard' => ['label' => 'Tableau de bord', 'icon' => 'bi-speedometer2', 'route' => 'dashboard', 'params' => []],
        'documents' => ['label' => 'Documents', 'icon' => 'bi-files', 'route' => 'documents.index', 'params' => []],
        'personal' => ['label' => 'Mes documents personnels', 'icon' => 'bi-person-lock', 'route' => 'documents.index', 'params' => ['personal' => 1]],
        'v02' => ['label' => 'Mes documents', 'icon' => 'bi-briefcase', 'route' => 'v02.my-documents', 'params' => []],
        'spaces' => ['label' => 'Espaces', 'icon' => 'bi-collection', 'route' => 'spaces.index', 'params' => []],
        'search' => ['label' => 'Recherche', 'icon' => 'bi-search', 'route' => 'search', 'params' => []],
        'workflows' => ['label' => 'Workflows', 'icon' => 'bi-diagram-3', 'route' => 'workflows.index', 'params' => []],
        'tasks' => ['label' => 'Mes tâches', 'icon' => 'bi-check2-square', 'route' => 'tasks.index', 'params' => []],
        'notifications' => ['label' => 'Notifications', 'icon' => 'bi-bell', 'route' => 'notifications.index', 'params' => []],
    ];

    public const ADMIN_KEY = 'administration';

    public const ADMIN_DEFAULT_LABEL = 'Administration';

    /**
     * Entrées de la sidebar ordonnées et personnalisées.
     * Avec $user : applique la visibilité effective (tenant ∩ utilisateur) ;
     * sans $user (écran d'administration) : toutes les entrées.
     */
    public function items(?Tenant $tenant, ?User $user = null): array
    {
        $labels = (array) ($tenant?->settings['menu']['labels'] ?? []);
        $order = (array) ($tenant?->settings['menu']['order'] ?? []);

        $ordered = [];
        foreach ($order as $position => $key) {
            if (! is_string($key)) {
                $key = $position; // compatibilité si l'ordre est stocké sous forme {clé: position}
            }
            if (isset(self::ITEMS[$key]) && ! isset($ordered[$key])) {
                $ordered[$key] = self::ITEMS[$key];
            }
        }
        foreach (self::ITEMS as $key => $definition) {
            if (! isset($ordered[$key])) {
                $ordered[$key] = $definition;
            }
        }

        if ($user !== null) {
            $hidden = $this->hiddenKeys($tenant, $user);
            $ordered = array_filter($ordered, fn ($key) => ! in_array($key, $hidden, true), ARRAY_FILTER_USE_KEY);

            // Garde défensive : jamais de sidebar vide (l'admin masque un menu que l'utilisateur
            // avait déjà masqué — repli sur la visibilité du tenant, puis sur les défauts).
            if ($ordered === []) {
                $tenantHidden = $this->hiddenKeys($tenant, null);
                $ordered = array_filter(
                    self::ITEMS,
                    fn ($key) => ! in_array($key, $tenantHidden, true),
                    ARRAY_FILTER_USE_KEY
                );
            }
            if ($ordered === []) {
                $ordered = self::ITEMS;
            }
        }

        $result = [];
        foreach ($ordered as $key => $definition) {
            $custom = trim((string) ($labels[$key] ?? ''));
            $definition['custom'] = $custom !== '';
            if ($custom !== '') {
                $definition['label'] = $custom;
            }
            $result[$key] = $definition;
        }

        return $result;
    }

    /** Clés masquées : masquage du tenant ∪ masquage de l'utilisateur (si fourni). */
    public function hiddenKeys(?Tenant $tenant, ?User $user = null): array
    {
        $tenantHidden = array_values(array_intersect((array) ($tenant?->settings['menu']['hidden'] ?? []), self::validKeys()));

        if ($user === null) {
            return $tenantHidden;
        }

        $userHidden = array_values(array_intersect((array) ($user->menu_hidden ?? []), self::validKeys()));

        return array_values(array_unique(array_merge($tenantHidden, $userHidden)));
    }

    /** Clés visibles par défaut pour le tenant (hors masquage organisation). */
    public function tenantVisibleKeys(?Tenant $tenant): array
    {
        return array_values(array_diff(self::validKeys(), $this->hiddenKeys($tenant, null)));
    }

    /** Route d'atterrissage après connexion : choix utilisateur > défaut tenant > tableau de bord. */
    public function startupRoute(?User $user): string
    {
        if ($user === null || $user->isSuperAdmin()) {
            return route('dashboard');
        }

        $hidden = $this->hiddenKeys($user->tenant, $user);
        $candidates = [
            (string) ($user->menu_startup ?? ''),
            (string) ($user->tenant?->settings['menu']['startup'] ?? ''),
        ];

        foreach ($candidates as $key) {
            if ($key !== '' && isset(self::ITEMS[$key]) && ! in_array($key, $hidden, true)) {
                return route(self::ITEMS[$key]['route'], self::ITEMS[$key]['params']);
            }
        }

        return route('dashboard');
    }

    public function adminLabel(?Tenant $tenant): string
    {
        $custom = trim((string) ($tenant?->settings['menu']['labels'][self::ADMIN_KEY] ?? ''));

        return $custom !== '' ? $custom : self::ADMIN_DEFAULT_LABEL;
    }

    public static function validKeys(): array
    {
        return array_keys(self::ITEMS);
    }
}
