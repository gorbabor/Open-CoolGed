<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class MetadataDefinition extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'name', 'key', 'type', 'required', 'options'];

    protected $casts = ['required' => 'boolean', 'options' => 'array'];
}
