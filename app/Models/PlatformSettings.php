<?php

namespace App\Models;

use App\Services\TenantSettings;
use Illuminate\Database\Eloquent\Model;

/**
 * Paramètres plateforme (super admin) : valeurs par défaut appliquées aux
 * nouveaux tenants (D-ADM4). Ligne unique (id = 1).
 */
class PlatformSettings extends Model
{
    protected $fillable = ['settings'];

    protected $casts = ['settings' => 'array'];

    public static function instance(): self
    {
        return self::firstOrCreate(['id' => 1], ['settings' => []]);
    }

    public function allSettings(): array
    {
        return array_replace(TenantSettings::DEFAULTS, $this->settings ?? []);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $settings = $this->settings ?? [];

        if (array_key_exists($key, $settings)) {
            return $settings[$key];
        }

        return array_key_exists($key, TenantSettings::DEFAULTS) ? TenantSettings::DEFAULTS[$key] : $default;
    }

    public function set(array $values): void
    {
        $this->update(['settings' => array_replace($this->settings ?? [], $values)]);
    }

    /** Merge into a new tenant's settings (super admin defaults win, then base defaults). */
    public function applyToNewTenant(array &$tenantSettings): void
    {
        $tenantSettings = array_replace(TenantSettings::DEFAULTS, $this->settings ?? [], $tenantSettings);
    }
}
