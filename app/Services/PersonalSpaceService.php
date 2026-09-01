<?php

namespace App\Services;

use App\Models\Space;
use App\Models\User;

class PersonalSpaceService
{
    public function ensure(User $user): Space
    {
        return Space::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $user->tenant_id, 'personal_user_id' => $user->id],
            [
                'tenant_id' => $user->tenant_id,
                'personal_user_id' => $user->id,
                'is_personal' => true,
                'name' => 'Personnel — '.$user->name,
                'description' => 'Espace personnel privé de '.$user->name,
                'color' => '#6f42c1',
            ]
        );
    }

    public function adminAccessEnabled(User $user): bool
    {
        return ($user->tenant->settings['personal_spaces_admin_access'] ?? true) === true;
    }

    public function canAccess(User $user, Space $space): bool
    {
        if (! $space->is_personal) {
            return true;
        }

        return $space->personal_user_id === $user->id
            || ($this->adminAccessEnabled($user) && $this->permissions()->can($user, 'admin.users'));
    }

    private function permissions(): PermissionService
    {
        return app(PermissionService::class);
    }
}
