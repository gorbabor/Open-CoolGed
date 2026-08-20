<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

class StorageService
{
    public function disk(): string
    {
        return config('ged.storage_disk', 'local');
    }

    /** Store an uploaded file outside webroot. Returns the relative path. */
    public function store(string $relativeDir, $uploadedFile): string
    {
        return $uploadedFile->store($relativeDir, $this->disk());
    }

    public function path(string $filePath): string
    {
        return Storage::disk($this->disk())->path($filePath);
    }

    public function exists(string $filePath): bool
    {
        return Storage::disk($this->disk())->exists($filePath);
    }

    public function delete(string $filePath): bool
    {
        return Storage::disk($this->disk())->delete($filePath);
    }

    /** Signed short-lived download URL when the configured disk supports it. */
    public function temporaryUrl(string $filePath, int $minutes = 5): ?string
    {
        try {
            return Storage::disk($this->disk())->temporaryUrl($filePath, now()->addMinutes($minutes));
        } catch (\Throwable) {
            return null;
        }
    }

    /** Storage used by a tenant, in bytes. */
    public function tenantStorageBytes(Tenant $tenant): int
    {
        return (int) \DB::table('document_versions')->where('tenant_id', $tenant->id)->sum('size');
    }

    public function tenantStorageMb(Tenant $tenant): float
    {
        return round($this->tenantStorageBytes($tenant) / 1048576, 2);
    }

    public function userCount(Tenant $tenant): int
    {
        return User::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count();
    }
}
