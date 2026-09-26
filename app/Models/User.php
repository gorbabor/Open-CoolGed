<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;

#[Fillable(['tenant_id', 'name', 'email', 'password', 'is_super_admin', 'status', 'mfa_enabled', 'mfa_secret', 'theme', 'theme_mode', 'locale', 'doc_view', 'doc_group', 'doc_columns', 'my_doc_columns', 'menu_hidden', 'menu_startup', 'last_login_at', 'job_id', 'department_id', 'direction_id', 'site_id', 'entity_id', 'country_id'])]
#[Hidden(['password', 'remember_token', 'mfa_secret'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
            'mfa_enabled' => 'boolean',
            'doc_columns' => 'array',
            'my_doc_columns' => 'array',
            'menu_hidden' => 'array',
            'last_login_at' => 'datetime',
        ];
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function groups()
    {
        return $this->belongsToMany(Group::class)->withPivot('tenant_id');
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'user_role')->withPivot('tenant_id');
    }

    /** Référentiel de dimension (poste/département/…) — helper V02. */
    public function referential(string $type): ?Referential
    {
        $col = match ($type) {
            'job' => $this->job_id,
            'department' => $this->department_id,
            'direction' => $this->direction_id,
            'site' => $this->site_id,
            'entity' => $this->entity_id,
            'country' => $this->country_id,
            default => null,
        };

        return $col ? Referential::find($col) : null;
    }

    /** IDs des référentiels de l'utilisateur (pour filtrage « applicable à mon poste »). */
    public function dimensionIds(): array
    {
        return array_values(array_filter([
            $this->job_id, $this->department_id, $this->direction_id,
            $this->site_id, $this->entity_id, $this->country_id,
        ]));
    }

    public function isSuperAdmin(): bool
    {
        return (bool) $this->is_super_admin;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }
}
