<?php

namespace App\Services;

use App\Models\Document;
use App\Models\Folder;
use App\Models\RolePermission;
use App\Models\Share;
use App\Models\Space;
use App\Models\User;

class PermissionService
{
    /**
     * Scope chain for a resource: document -> folder -> space -> tenant.
     * Returns list of [scope_type, scope_id] most specific first.
     */
    public function scopeChain($resource): array
    {
        $chain = [];

        if ($resource instanceof Document) {
            $chain[] = ['document', $resource->id];
            if ($resource->folder_id) {
                $chain[] = ['folder', $resource->folder_id];
            }
            $chain[] = ['space', $resource->space_id];
        } elseif ($resource instanceof Folder) {
            $chain[] = ['folder', $resource->id];
            $chain[] = ['space', $resource->space_id];
            foreach ($resource->ancestors() as $parentId) {
                $chain[] = ['folder', $parentId];
            }
        } elseif ($resource instanceof Space) {
            $chain[] = ['space', $resource->id];
        }

        $chain[] = ['tenant', $resource->tenant_id ?? null];

        return $chain;
    }

    public function roleIds(User $user): array
    {
        return $user->roles()->pluck('roles.id')->all();
    }

    /**
     * Effective roles = direct roles (user_role) ∪ roles of the groups the user
     * belongs to (group_role). Flat groups only — no recursion (D-RBAC1).
     */
    public function effectiveRoleIds(User $user): array
    {
        $direct = $user->roles()->pluck('roles.id')->all();

        $groupRoleIds = $user->groups()
            ->with('roles')
            ->get()
            ->flatMap(fn ($group) => $group->roles->pluck('id'))
            ->all();

        return array_values(array_unique(array_merge($direct, $groupRoleIds)));
    }

    /**
     * Check a permission for a user, optionally against a resource scope chain.
     * RM-008: an explicit deny always prevails over any implicit or inherited allow.
     */
    public function can(User $user, string $permission, $resource = null): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->isSuspended() || ! $user->isActive()) {
            return false;
        }

        if ($user->tenant_id === null) {
            return false;
        }

        // RM-001/RM-002: a resource of another tenant is never accessible.
        if ($resource !== null && (int) $resource->tenant_id !== (int) $user->tenant_id) {
            return false;
        }

        $roleIds = $this->effectiveRoleIds($user);
        if ($roleIds === []) {
            return false;
        }

        $chain = $resource !== null ? $this->scopeChain($resource) : [['tenant', $user->tenant_id]];

        $denies = RolePermission::whereIn('role_id', $roleIds)
            ->where('denied', true)
            ->whereHas('permission', fn ($q) => $q->where('slug', $permission))
            ->get();

        foreach ($chain as [$scopeType, $scopeId]) {
            $deny = $denies->first(fn ($rp) => $this->scopeMatches($rp, $scopeType, $scopeId));
            if ($deny) {
                return false;
            }
        }

        $allows = RolePermission::whereIn('role_id', $roleIds)
            ->where('denied', false)
            ->whereHas('permission', fn ($q) => $q->where('slug', $permission))
            ->get();

        foreach ($chain as [$scopeType, $scopeId]) {
            $allow = $allows->first(fn ($rp) => $this->scopeMatches($rp, $scopeType, $scopeId));
            if ($allow) {
                return true;
            }
        }

        return false;
    }

    private function scopeMatches(RolePermission $rp, string $scopeType, $scopeId): bool
    {
        if ($rp->scope_type === 'global' || $rp->scope_type === 'tenant') {
            return true;
        }

        if ($rp->scope_type === $scopeType && $rp->scope_id == $scopeId) {
            return true;
        }

        return false;
    }

    /** Documents accessible to a user: permitted by RBAC or via an active share. */
    public function accessibleDocumentIds(User $user): array
    {
        $direct = Document::where('tenant_id', $user->tenant_id)
            ->get()
            ->filter(fn ($d) => $this->can($user, 'documents.view', $d))
            ->pluck('id')
            ->all();

        $shared = Share::where('tenant_id', $user->tenant_id)
            ->whereNull('revoked_at')
            ->where(fn ($q) => $q->where('expires_at', null)->orWhere('expires_at', '>', now()))
            ->where(function ($q) use ($user) {
                $q->where('shared_with_user_id', $user->id)
                    ->orWhereIn('shared_with_group_id', $user->groups()->pluck('groups.id'));
            })
            ->pluck('document_id')
            ->all();

        return array_values(array_unique(array_merge($direct, $shared)));
    }
}
