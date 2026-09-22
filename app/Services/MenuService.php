<?php

namespace App\Services;

use App\Models\Tenant;

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

    /** Entrées de la sidebar ordonnées et personnalisées pour un tenant (défauts si non configuré). */
    public function items(?Tenant $tenant): array
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
