<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class ReadAcknowledgement extends Model
{
    use BelongsToTenant;

    public const CREATED_AT = 'acknowledged_at';

    public const UPDATED_AT = null;

    protected $fillable = ['tenant_id', 'document_version_id', 'document_id', 'user_id', 'acknowledged_at'];

    protected $casts = ['acknowledged_at' => 'datetime'];

    public function document()
    {
        return $this->belongsTo(Document::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
