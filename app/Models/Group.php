<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Group extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'name', 'description'];

    public function users()
    {
        return $this->belongsToMany(User::class)->withPivot('tenant_id');
    }

    /** Roles attached to the group: every member inherits them (CA-RBAC1/2). */
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'group_role')->withPivot('tenant_id');
    }
}
