<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    protected $fillable = ['name', 'slug', 'group'];

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_permission')
            ->withPivot('scope_type', 'scope_id', 'denied');
    }

    public static function allSlugs(): array
    {
        return [
            'documents.view', 'documents.preview', 'documents.download', 'documents.create',
            'documents.edit', 'documents.delete', 'documents.restore', 'documents.archive',
            'documents.comment', 'documents.share', 'documents.metadata',
            'workflow.validate', 'workflow.approve', 'workflow.reject', 'workflow.manage',
            'ai.use', 'ai.admin',
            'admin.users', 'admin.groups', 'admin.roles', 'admin.types',
            'admin.workflows', 'admin.audit', 'admin.settings', 'admin.quotas',
            'admin.referentials',
        ];
    }
}
