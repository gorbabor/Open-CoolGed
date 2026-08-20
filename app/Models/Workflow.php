<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Workflow extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'name', 'slug', 'document_type_id', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function steps()
    {
        return $this->hasMany(WorkflowStep::class)->orderBy('position');
    }

    public function documentType()
    {
        return $this->belongsTo(DocumentType::class);
    }
}
