<?php

namespace App\Services;

use App\Models\Document;
use App\Models\Group;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowInstance;
use App\Models\WorkflowStep;
use App\Models\WorkflowTask;
use Illuminate\Support\Facades\DB;

class WorkflowService
{
    public function __construct(
        private PermissionService $permissions,
        private AuditService $audit,
        private NotificationService $notifications,
    ) {}

    /** Resolve assignee user ids for a step (user / group / role / creator). */
    public function resolveAssigneeIds(WorkflowStep $step, Document $document): array
    {
        return match ($step->assignee_type) {
            'user' => $step->assignee_id ? [$step->assignee_id] : [],
            'group' => Group::withoutGlobalScopes()
                ->find($step->assignee_id)?->users()->pluck('users.id')->all() ?? [],
            'role' => User::withoutGlobalScopes()
                ->where('tenant_id', $document->tenant_id)
                ->whereHas('roles', fn ($q) => $q->where('roles.id', $step->assignee_id))
                ->pluck('id')
                ->all(),
            'creator' => [$document->created_by],
            default => [],
        };
    }

    public function start(Document $document, Workflow $workflow, User $byUser): WorkflowInstance
    {
        if (! $workflow->is_active) {
            throw new \RuntimeException('Ce workflow est désactivé.');
        }

        $active = WorkflowInstance::where('document_id', $document->id)
            ->where('status', 'active')
            ->exists();

        if ($active) {
            throw new \RuntimeException('Un workflow est déjà actif sur ce document.');
        }

        return DB::transaction(function () use ($document, $workflow, $byUser) {
            $firstStep = $workflow->steps()->orderBy('position')->first();
            if (! $firstStep) {
                throw new \RuntimeException('Le workflow ne possède aucune étape.');
            }

            $instance = WorkflowInstance::create([
                'tenant_id' => $document->tenant_id,
                'workflow_id' => $workflow->id,
                'document_id' => $document->id,
                'status' => 'active',
                'current_step_id' => $firstStep->id,
                'created_by' => $byUser->id,
            ]);

            $document->update(['status' => 'in_review']);
            $this->createTasks($instance, $firstStep);
            $this->audit->log('workflow.started', 'workflow_instance', $instance->id, ['workflow' => $workflow->name]);

            return $instance;
        });
    }

    private function createTasks(WorkflowInstance $instance, WorkflowStep $step): void
    {
        $document = $instance->document;
        $assigneeIds = $this->resolveAssigneeIds($step, $document);

        foreach ($assigneeIds as $userId) {
            $task = WorkflowTask::create([
                'tenant_id' => $instance->tenant_id,
                'instance_id' => $instance->id,
                'step_id' => $step->id,
                'status' => 'pending',
                'assignee_user_id' => $userId,
                'due_at' => now()->addDays(7),
            ]);

            $this->notifications->send(
                User::withoutGlobalScopes()->find($userId),
                'workflow.task',
                'Tâche de validation : '.$step->name,
                'Document « '.$document->title.' » à traiter.',
                '/tasks'
            );
        }
    }

    /**
     * CA-007: only the assigned actor (or its delegate) may act.
     * CA-006: only defined transitions are accepted.
     */
    public function decide(User $user, WorkflowTask $task, string $decision, ?string $comment = null): WorkflowInstance
    {
        $instance = $task->instance;

        if ($instance->status !== 'active') {
            throw new \RuntimeException('Le workflow n\'est plus actif.');
        }

        if ($task->status !== 'pending') {
            throw new \RuntimeException('Cette tâche a déjà été traitée.');
        }

        if ($task->effectiveAssigneeId() !== $user->id && ! $user->isSuperAdmin()) {
            throw new \RuntimeException('Cette tâche ne vous est pas affectée.', 403);
        }

        if (! in_array($decision, ['approve', 'reject'], true)) {
            throw new \RuntimeException('Transition non définie pour ce workflow.');
        }

        $document = $instance->document;

        if ($decision === 'approve' && ! $this->permissions->can($user, 'workflow.approve', $document)) {
            throw new \RuntimeException('Permission de validation refusée.', 403);
        }

        if ($decision === 'reject' && ! $this->permissions->can($user, 'workflow.reject', $document)) {
            throw new \RuntimeException('Permission de rejet refusée.', 403);
        }

        if ($decision === 'reject' && trim((string) $comment) === '') {
            throw new \RuntimeException('Le motif de rejet est obligatoire.');
        }

        return DB::transaction(function () use ($user, $task, $decision, $comment, $instance, $document) {
            $task->update([
                'status' => $decision === 'approve' ? 'completed' : 'rejected',
                'decision_comment' => $comment,
                'acted_by' => $user->id,
                'acted_at' => now(),
            ]);

            $this->audit->log('workflow.task.'.$decision, 'workflow_task', $task->id, [
                'instance' => $instance->id,
                'document' => $document->id,
                'comment' => $comment,
            ], $user->id);

            if ($decision === 'reject') {
                $instance->update(['status' => 'rejected']);
                $document->update(['status' => 'draft']);
                $this->notifications->send(
                    $document->creator()->first(),
                    'workflow.rejected',
                    'Document rejeté',
                    '« '.$document->title.' » a été rejeté : '.$comment,
                    '/documents/'.$document->id
                );

                return $instance->fresh();
            }

            $pending = WorkflowTask::where('instance_id', $instance->id)
                ->where('status', 'pending')
                ->count();

            if ($pending > 0) {
                return $instance->fresh();
            }

            $next = WorkflowStep::where('workflow_id', $instance->workflow_id)
                ->where('position', '>', $task->step->position)
                ->orderBy('position')
                ->first();

            if (! $next) {
                $instance->update(['status' => 'completed']);
                $document->update(['status' => 'approved']);
                $this->audit->log('workflow.completed', 'workflow_instance', $instance->id);
                $this->notifications->send(
                    $document->creator()->first(),
                    'workflow.completed',
                    'Workflow terminé',
                    '« '.$document->title.' » a été approuvé.',
                    '/documents/'.$document->id
                );

                return $instance->fresh();
            }

            $instance->update(['current_step_id' => $next->id]);
            $this->createTasks($instance, $next);

            return $instance->fresh();
        });
    }

    public function delegate(User $user, WorkflowTask $task, int $toUserId): WorkflowTask
    {
        if ($task->effectiveAssigneeId() !== $user->id && ! $user->isSuperAdmin()) {
            throw new \RuntimeException('Cette tâche ne vous est pas affectée.', 403);
        }

        $task->update(['delegated_to_id' => $toUserId]);
        $this->audit->log('workflow.task.delegated', 'workflow_task', $task->id, ['to' => $toUserId]);

        return $task->fresh();
    }
}
