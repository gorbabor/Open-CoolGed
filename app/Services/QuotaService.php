<?php

namespace App\Services;

use App\Models\Tenant;

class QuotaService
{
    public function __construct(private StorageService $storage) {}

    public function storageLeftBytes(Tenant $tenant): int
    {
        $used = $this->storage->tenantStorageBytes($tenant);
        $limit = $tenant->storage_quota_mb * 1048576;

        return max(0, $limit - $used);
    }

    /** RM-020: quota checked before creating any resource that consumes it. */
    public function canStore(Tenant $tenant, int $sizeBytes): bool
    {
        return $this->storageLeftBytes($tenant) >= $sizeBytes;
    }

    public function usersLeft(Tenant $tenant): int
    {
        return max(0, $tenant->user_quota - $this->storage->userCount($tenant));
    }

    public function canAddUser(Tenant $tenant): bool
    {
        return $this->usersLeft($tenant) > 0;
    }
}
