<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class OfficeSession extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'document_id', 'version_id', 'user_id', 'token', 'expires_at', 'returned_at',
    ];

    protected $casts = ['expires_at' => 'datetime', 'returned_at' => 'datetime'];

    public function document()
    {
        return $this->belongsTo(Document::class);
    }

    public function version()
    {
        return $this->belongsTo(DocumentVersion::class, 'version_id');
    }

    public function isValid(): bool
    {
        return $this->returned_at === null && $this->expires_at->isFuture();
    }
}
