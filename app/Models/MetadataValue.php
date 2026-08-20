<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class MetadataValue extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'document_id', 'definition_id', 'value'];

    public function definition()
    {
        return $this->belongsTo(MetadataDefinition::class, 'definition_id');
    }
}
