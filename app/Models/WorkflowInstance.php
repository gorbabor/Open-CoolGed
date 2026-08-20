<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class WorkflowInstance extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'workflow_id', 'document_id', 'status', 'current_step_id', 'created_by'];

    public function workflow()
    {
        return $this->belongsTo(Workflow::class);
    }

    public function document()
    {
        return $this->belongsTo(Document::class);
    }

    public function currentStep()
    {
        return $this->belongsTo(WorkflowStep::class, 'current_step_id');
    }

    public function tasks()
    {
        return $this->hasMany(WorkflowTask::class, 'instance_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
