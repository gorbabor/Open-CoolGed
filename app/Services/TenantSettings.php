<?php

namespace App\Services;

use App\Models\Tenant;

/**
 * Lecture/écriture des options paramétrables par tenant (JSON tenants.settings).
 * Valeurs par défaut centralisées ; le reste de l'application lit via ce service
 * (MIME autorisés, taille max, verrouillage auto, partage externe, …).
 */
class TenantSettings
{
    public const DEFAULTS = [
        // Sécurité
        'mfa_required_admin' => false,
        'mfa_required_validator' => false,
        'session_expiration_minutes' => 120,
        'password_min_length' => 8,
        'lockout_attempts' => 5,
        // Documents
        'comment_required' => false,
        'auto_lock_on_edit' => true,
        // Rétention
        'default_retention_days' => null,
        'trash_purge_days' => 30,
        'retention_alert_days' => 30,
        // Notifications
        'notif_tasks' => true,
        'notif_shares' => true,
        'notif_deadlines' => true,
        'email_notifications' => false,
        // Partage externe
        'external_sharing_enabled' => false,
        'external_share_max_days' => 30,
        'external_share_password_required' => false,
        // IA
        'ai_enabled' => true,
        'ai_providers' => ['mock'],
        // Workflows
        'default_workflow_type' => null,
    ];

    public function __construct(private Tenant $tenant) {}

    public static function for(Tenant $tenant): self
    {
        return new self($tenant);
    }

    public function all(): array
    {
        return $this->normalize(array_replace(self::DEFAULTS, $this->tenant->settings ?? []));
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $settings = $this->tenant->settings ?? [];

        if (array_key_exists($key, $settings)) {
            return $this->normalize([$key => $settings[$key]])[$key];
        }

        return array_key_exists($key, self::DEFAULTS) ? self::DEFAULTS[$key] : $default;
    }

    /**
     * Normalisation défensive : certaines valeurs ont pu être stockées en chaîne
     * (ex. « mock, openai ») par des versions antérieures du code — elles doivent
     * toujours être exposées comme tableaux (ai_providers, allowed_mimes).
     */
    private function normalize(array $settings): array
    {
        foreach (['ai_providers', 'allowed_mimes'] as $key) {
            if (isset($settings[$key]) && is_string($settings[$key])) {
                $settings[$key] = array_values(array_filter(array_map('trim', explode(',', $settings[$key]))));
            }
        }

        return $settings;
    }

    public function set(array $values): void
    {
        $settings = $this->tenant->settings ?? [];
        foreach ($values as $key => $value) {
            $settings[$key] = $value;
        }
        $this->tenant->update(['settings' => $settings]);
    }

    /** MIME autorisés à l'import (politique tenant, appliquée). */
    public function allowedMimes(): array
    {
        return $this->get('allowed_mimes', DocumentService::DEFAULT_ALLOWED_MIMES);
    }

    public function externalSharingEnabled(): bool
    {
        return (bool) $this->get('external_sharing_enabled', false);
    }
}
