<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Referential extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'type', 'name', 'code'];

    public const TYPES = ['domain', 'process', 'job', 'department', 'direction', 'site', 'entity', 'country'];

    public function documents()
    {
        return $this->belongsToMany(Document::class, 'document_referential')
            ->withPivot('tenant_id', 'type');
    }
}
