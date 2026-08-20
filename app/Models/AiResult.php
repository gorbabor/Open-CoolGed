<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class AiResult extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'job_id', 'document_id', 'result_type', 'content', 'confidence',
        'validated_by', 'validated_at',
    ];

    protected $casts = ['content' => 'array', 'confidence' => 'float', 'validated_at' => 'datetime'];

    public function job()
    {
        return $this->belongsTo(AiJob::class);
    }

    public function document()
    {
        return $this->belongsTo(Document::class);
    }
}
