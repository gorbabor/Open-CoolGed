<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class DocumentType extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'name', 'slug', 'metadata_schema', 'retention_days'];

    protected $casts = ['metadata_schema' => 'array'];

    public function workflows()
    {
        return $this->hasMany(Workflow::class);
    }
}
