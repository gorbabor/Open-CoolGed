<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class WorkflowTask extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'instance_id', 'step_id', 'status', 'assignee_user_id',
        'delegated_to_id', 'due_at', 'decision_comment', 'acted_by', 'acted_at',
    ];

    protected $casts = ['due_at' => 'datetime', 'acted_at' => 'datetime'];

    public function instance()
    {
        return $this->belongsTo(WorkflowInstance::class, 'instance_id');
    }

    public function step()
    {
        return $this->belongsTo(WorkflowStep::class, 'step_id');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assignee_user_id');
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'acted_by');
    }

    public function effectiveAssigneeId(): ?int
    {
        return $this->delegated_to_id ?? $this->assignee_user_id;
    }
}
