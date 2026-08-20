<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    protected $fillable = [
        'name', 'slug', 'status', 'plan', 'storage_quota_mb', 'user_quota',
        'max_file_size_mb', 'settings', 'branding',
    ];

    protected $casts = [
        'settings' => 'array',
        'branding' => 'array',
        'storage_quota_mb' => 'integer',
        'user_quota' => 'integer',
        'max_file_size_mb' => 'integer',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    public function aiEnabled(): bool
    {
        return ($this->settings['ai_enabled'] ?? true) === true;
    }

    public function allowedProviders(): array
    {
        return $this->settings['ai_providers'] ?? ['mock'];
    }
}
