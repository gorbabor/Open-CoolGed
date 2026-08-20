<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class DocumentVersion extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'document_id', 'version', 'file_path', 'file_name', 'mime_type',
        'size', 'checksum', 'extracted_text', 'comment', 'created_by', 'lock_token',
    ];

    public function document()
    {
        return $this->belongsTo(Document::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
