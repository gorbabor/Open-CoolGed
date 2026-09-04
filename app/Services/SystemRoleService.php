<?php

namespace App\Services;

use App\Models\Permission;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\Tenant;

class SystemRoleService
{
    public const ROLE_DEFS = [
        'tenant_admin' => ['documents.view', 'documents.preview', 'documents.download', 'documents.create', 'documents.edit', 'documents.delete', 'documents.restore', 'documents.archive', 'documents.comment', 'documents.share', 'documents.metadata', 'admin.users', 'admin.groups', 'admin.roles', 'admin.types', 'admin.workflows', 'admin.audit', 'admin.settings', 'admin.quotas', 'admin.referentials', 'admin.spaces', 'workflow.manage', 'workflow.validate', 'workflow.approve', 'workflow.reject', 'ai.admin', 'ai.use'],
        'manager' => ['documents.view', 'documents.preview', 'documents.download', 'documents.create', 'documents.edit', 'documents.comment', 'documents.share', 'documents.metadata', 'documents.archive', 'workflow.validate', 'workflow.approve', 'workflow.reject', 'ai.use'],
        'user' => ['documents.view', 'documents.preview', 'documents.download', 'documents.create', 'documents.edit', 'documents.comment', 'documents.share'],
        'auditor' => ['documents.view', 'documents.preview', 'admin.audit'],
        'validator' => ['documents.view', 'documents.preview', 'documents.download', 'workflow.validate', 'workflow.approve', 'workflow.reject'],
    ];

    /** System roles are created per tenant (RM-029). */
    public function ensureFor(Tenant $tenant): array
    {
        $roles = [];

        foreach (self::ROLE_DEFS as $slug => $permSlugs) {
            $role = Role::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('slug', $slug)->first()
                ?? Role::create([
                    'tenant_id' => $tenant->id,
                    'name' => ucfirst(str_replace('_', ' ', $slug)),
                    'slug' => $slug,
                    'is_system' => true,
                ]);

            foreach ($permSlugs as $permSlug) {
                $permission = Permission::where('slug', $permSlug)->first();
                if ($permission) {
                    RolePermission::firstOrCreate(
                        ['role_id' => $role->id, 'permission_id' => $permission->id, 'scope_type' => 'tenant', 'scope_id' => null],
                        ['denied' => false]
                    );
                }
            }

            $roles[$slug] = $role;
        }

        return $roles;
    }

    public static function seedPermissions(): void
    {
        foreach (Permission::allSlugs() as $slug) {
            Permission::firstOrCreate(
                ['slug' => $slug],
                ['name' => ucwords(str_replace('.', ' ', $slug)), 'group' => explode('.', $slug)[0]]
            );
        }
    }
}
