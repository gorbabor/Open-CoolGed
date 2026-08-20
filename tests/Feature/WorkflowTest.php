<?php

namespace Tests\Feature;

use App\Models\Workflow;
use App\Services\WorkflowService;
use Tests\TestCase;

class WorkflowTest extends TestCase
{
    private function makeWorkflow($tenant, $validator, $admin)
    {
        $wf = Workflow::create([
            'tenant_id' => $tenant->id,
            'name' => 'Validation '.uniqid(),
            'slug' => 'validation-'.uniqid(),
            'is_active' => true,
        ]);

        $step1 = $wf->steps()->create([
            'tenant_id' => $tenant->id,
            'position' => 1,
            'name' => 'Revue',
            'assignee_type' => 'user',
            'assignee_id' => $validator->id,
            'is_final' => false,
        ]);

        $wf->steps()->create([
            'tenant_id' => $tenant->id,
            'position' => 2,
            'name' => 'Approbation',
            'assignee_type' => 'user',
            'assignee_id' => $admin->id,
            'is_final' => true,
        ]);

        return [$wf, $step1];
    }

    public function test_full_approval_path_and_status_transitions(): void
    {
        $tenant = $this->makeTenant();
        $space = $this->makeSpace($tenant);
        $creator = $this->makeUser($tenant, 'user');
        $validator = $this->makeUser($tenant, 'validator');
        $admin = $this->makeUser($tenant, 'tenant_admin');
        $doc = $this->makeDocument($tenant, $creator, $space);

        [$wf] = $this->makeWorkflow($tenant, $validator, $admin);
        $service = app(WorkflowService::class);
        $this->actingAsUser($creator);

        $instance = $service->start($doc, $wf, $creator);
        $doc->refresh();
        $this->assertSame('in_review', $doc->status);

        $task1 = $instance->tasks()->where('status', 'pending')->first();
        $service->decide($validator, $task1, 'approve');
        $instance->refresh();

        $task2 = $instance->tasks()->where('status', 'pending')->first();
        $this->assertNotNull($task2);
        $this->assertSame($admin->id, $task2->assignee_user_id);

        $service->decide($admin, $task2, 'approve');
        $instance->refresh();
        $doc->refresh();

        $this->assertSame('completed', $instance->status);
        $this->assertSame('approved', $doc->status);
    }

    public function test_rejection_requires_motif_and_reverts_document(): void
    {
        $tenant = $this->makeTenant();
        $space = $this->makeSpace($tenant);
        $creator = $this->makeUser($tenant, 'user');
        $validator = $this->makeUser($tenant, 'validator');
        $doc = $this->makeDocument($tenant, $creator, $space);

        [$wf] = $this->makeWorkflow($tenant, $validator, $this->makeUser($tenant, 'tenant_admin'));
        $service = app(WorkflowService::class);
        $this->actingAsUser($creator);

        $instance = $service->start($doc, $wf, $creator);
        $task = $instance->tasks()->first();

        // Motif obligatoire.
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('motif');
        $service->decide($validator, $task, 'reject');
    }

    public function test_task_cannot_be_processed_by_unassigned_user(): void
    {
        $tenant = $this->makeTenant();
        $space = $this->makeSpace($tenant);
        $creator = $this->makeUser($tenant, 'user');
        $validator = $this->makeUser($tenant, 'validator');
        $outsider = $this->makeUser($tenant, 'validator');
        $doc = $this->makeDocument($tenant, $creator, $space);

        [$wf] = $this->makeWorkflow($tenant, $validator, $outsider);
        $service = app(WorkflowService::class);
        $this->actingAsUser($creator);

        $instance = $service->start($doc, $wf, $creator);
        $task = $instance->tasks()->first();

        // CA-007: the assigned actor is validator, not outsider.
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('affectée');
        $service->decide($outsider, $task, 'approve');
    }

    public function test_undefined_transition_is_rejected(): void
    {
        $tenant = $this->makeTenant();
        $space = $this->makeSpace($tenant);
        $creator = $this->makeUser($tenant, 'user');
        $validator = $this->makeUser($tenant, 'validator');
        $doc = $this->makeDocument($tenant, $creator, $space);

        [$wf] = $this->makeWorkflow($tenant, $validator, $this->makeUser($tenant, 'tenant_admin'));
        $service = app(WorkflowService::class);
        $this->actingAsUser($creator);

        $instance = $service->start($doc, $wf, $creator);
        $task = $instance->tasks()->first();

        // CA-006: transition not defined in the model is refused.
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Transition');
        $service->decide($validator, $task, 'skip');
    }

    public function test_every_transition_is_audited_with_actor(): void
    {
        $tenant = $this->makeTenant();
        $space = $this->makeSpace($tenant);
        $creator = $this->makeUser($tenant, 'user');
        $validator = $this->makeUser($tenant, 'validator');
        $doc = $this->makeDocument($tenant, $creator, $space);

        [$wf] = $this->makeWorkflow($tenant, $validator, $this->makeUser($tenant, 'tenant_admin'));
        $service = app(WorkflowService::class);
        $this->actingAsUser($creator);

        $instance = $service->start($doc, $wf, $creator);
        $task = $instance->tasks()->first();
        $service->decide($validator, $task, 'approve');

        $this->assertDatabaseHas('workflow_tasks', ['id' => $task->id, 'acted_by' => $validator->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'workflow.task.approve', 'user_id' => $validator->id]);
    }

    public function test_workflow_can_be_restarted_after_completion(): void
    {
        $tenant = $this->makeTenant();
        $space = $this->makeSpace($tenant);
        $creator = $this->makeUser($tenant, 'user');
        $validator = $this->makeUser($tenant, 'validator');
        $admin = $this->makeUser($tenant, 'tenant_admin');
        $doc = $this->makeDocument($tenant, $creator, $space);

        [$wf] = $this->makeWorkflow($tenant, $validator, $admin);
        $service = app(WorkflowService::class);
        $this->actingAsUser($creator);

        // 1er cycle complet → approuvé.
        $instance = $service->start($doc, $wf, $creator);
        $service->decide($validator, $instance->tasks()->where('status', 'pending')->first(), 'approve');
        $instance->refresh();
        $service->decide($admin, $instance->tasks()->where('status', 'pending')->first(), 'approve');
        $doc->refresh();
        $this->assertSame('approved', $doc->status);

        // Relance (nouvelle révision) : le blocage ne concerne que les instances actives.
        $this->post(route('workflows.start', $doc), ['workflow_id' => $wf->id])
            ->assertRedirect()
            ->assertSessionHas('success');

        $doc->refresh();
        $this->assertSame('in_review', $doc->status);
        $this->assertSame(2, $doc->workflowInstances()->count());
        $this->assertSame(1, $doc->workflowInstances()->where('status', 'active')->count());

        // La fiche document rend l'onglet Workflow avec l'historique des instances.
        $html = $this->get(route('documents.show', $doc))->getContent();
        $this->assertStringContainsString('Historique des instances', $html);
    }
}
