<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'name', 'slug', 'description', 'is_system'];

    protected $casts = ['is_system' => 'boolean'];

    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'role_permission')
            ->withPivot('scope_type', 'scope_id', 'denied');
    }

    public function groups()
    {
        return $this->belongsToMany(Group::class, 'group_role')->withPivot('tenant_id');
    }
}
