<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class RagChunk extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'document_id', 'version_id', 'chunk_index', 'content', 'embedding'];

    protected $casts = ['embedding' => 'array'];

    public function document()
    {
        return $this->belongsTo(Document::class);
    }
}
