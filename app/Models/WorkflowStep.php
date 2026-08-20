<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class WorkflowStep extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'workflow_id', 'position', 'name', 'assignee_type', 'assignee_id', 'is_final'];

    protected $casts = ['is_final' => 'boolean'];

    public function workflow()
    {
        return $this->belongsTo(Workflow::class);
    }
}
