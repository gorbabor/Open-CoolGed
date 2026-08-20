<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Share extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'document_id', 'shared_with_user_id', 'shared_with_group_id',
        'permission', 'expires_at', 'revoked_at', 'created_by',
        'is_external', 'token', 'password_hash',
    ];

    protected $casts = ['expires_at' => 'datetime', 'revoked_at' => 'datetime', 'is_external' => 'boolean'];

    public function document()
    {
        return $this->belongsTo(Document::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'shared_with_user_id');
    }

    public function group()
    {
        return $this->belongsTo(Group::class, 'shared_with_group_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    /** RM-012: external access goes through a scoped token, never a public URL. */
    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }
}
