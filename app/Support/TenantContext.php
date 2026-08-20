<?php

namespace App\Support;

final class TenantContext
{
    private static ?int $tenantId = null;

    private static bool $enabled = true;

    public static function set(?int $tenantId): void
    {
        self::$tenantId = $tenantId;
    }

    public static function get(): ?int
    {
        return self::$tenantId;
    }

    public static function disable(): void
    {
        self::$enabled = false;
    }

    public static function enable(): void
    {
        self::$enabled = true;
    }

    public static function isEnabled(): bool
    {
        return self::$enabled;
    }
}
