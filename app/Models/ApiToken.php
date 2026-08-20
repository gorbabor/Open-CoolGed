<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * No tenant scope on purpose: tokens are located by their unique 256-bit hash
 * (SEC-011), and ApiAuth verifies tenant/user coherence server-side.
 */
class ApiToken extends Model
{
    protected $fillable = ['tenant_id', 'user_id', 'name', 'token_hash', 'last_used_at', 'expires_at'];

    protected $casts = ['last_used_at' => 'datetime', 'expires_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
