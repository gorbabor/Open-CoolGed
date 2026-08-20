<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Request;

class AuditService
{
    public function log(string $action, ?string $resourceType = null, ?int $resourceId = null, array $details = [], ?int $actorUserId = null): AuditLog
    {
        $user = $actorUserId !== null ? User::withoutGlobalScopes()->find($actorUserId) : auth()->user();

        return AuditLog::create([
            'tenant_id' => $user?->tenant_id ?? TenantContext::get(),
            'user_id' => $user?->id,
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'details' => $details,
            'ip' => Request::ip(),
            'user_agent' => mb_substr((string) Request::userAgent(), 0, 255),
        ]);
    }
}
