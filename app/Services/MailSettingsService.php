<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\Crypt;

/**
 * Messagerie SMTP configurable par tenant (Paramètres → Messagerie).
 * Le mot de passe est stocké chiffré dans tenants.settings ; ce service
 * configure dynamiquement le mailer « smtp » de Laravel pour le tenant courant.
 */
class MailSettingsService
{
    public function enabled(Tenant $tenant): bool
    {
        $settings = $tenant->settings ?? [];

        return (bool) ($settings['mail_enabled'] ?? false)
            && ! empty($settings['smtp_host']);
    }

    /** Configure le mailer global avec les paramètres du tenant. Retourne le host utilisé. */
    public function configure(Tenant $tenant): ?string
    {
        $settings = $tenant->settings ?? [];

        if (! $this->enabled($tenant)) {
            return null;
        }

        $password = $settings['smtp_password'] ?? null;
        if ($password !== null && $password !== '') {
            try {
                $password = Crypt::decryptString($password);
            } catch (\Throwable) {
                // Valeur non chiffrée (ancienne saisie) : utilisée telle quelle.
            }
        }

        $fromName = $settings['smtp_from_name'] ?? null;
        if ($fromName === null || trim((string) $fromName) === '') {
            $fromName = $tenant->branding['brand_name'] ?? null ?: 'Open-CoolGed';
        }

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => $settings['smtp_host'],
            'mail.mailers.smtp.port' => (int) ($settings['smtp_port'] ?? 587),
            'mail.mailers.smtp.username' => $settings['smtp_username'] ?? null,
            'mail.mailers.smtp.password' => $password,
            'mail.mailers.smtp.encryption' => (int) ($settings['smtp_port'] ?? 587) === 465 ? 'ssl' : 'tls',
            'mail.from.address' => $settings['smtp_from_address'] ?? ($settings['smtp_username'] ?? 'no-reply@example.com'),
            'mail.from.name' => $fromName,
        ]);

        return $settings['smtp_host'];
    }

    public function fromName(Tenant $tenant): string
    {
        $settings = $tenant->settings ?? [];
        $name = $settings['smtp_from_name'] ?? null;

        if ($name === null || trim((string) $name) === '') {
            return $tenant->branding['brand_name'] ?? null ?: 'Open-CoolGed';
        }

        return $name;
    }
}
