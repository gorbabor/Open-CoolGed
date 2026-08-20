<?php

namespace App\Http\Controllers;

use App\Models\DocumentType;
use App\Models\Group;
use App\Models\Role;
use App\Models\User;
use App\Models\Workflow;
use App\Services\AuditService;
use App\Services\WorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WorkflowController extends Controller
{
    public function __construct(
        private WorkflowService $workflows,
        private AuditService $audit,
    ) {}

    public function index()
    {
        return view('workflows.index', [
            'workflows' => Workflow::with(['steps', 'documentType'])->orderBy('name')->get(),
            'types' => DocumentType::orderBy('name')->get(),
            'users' => User::where('tenant_id', auth()->user()->tenant_id)->get(),
            'groups' => Group::orderBy('name')->get(),
            'roles' => Role::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'max:255'],
            'document_type_id' => ['nullable', 'exists:document_types,id'],
            'steps' => ['required', 'array', 'min:1'],
            'steps.*.name' => ['required', 'max:255'],
            'steps.*.assignee_type' => ['required', 'in:user,group,role,creator'],
            'steps.*.assignee_id' => ['nullable', 'integer'],
        ]);

        $workflow = Workflow::create([
            'tenant_id' => auth()->user()->tenant_id,
            'name' => $data['name'],
            'slug' => Str::slug($data['name']).'-'.uniqid(),
            'document_type_id' => $data['document_type_id'] ?? null,
            'is_active' => true,
        ]);

        foreach ($data['steps'] as $position => $step) {
            $workflow->steps()->create([
                'tenant_id' => $workflow->tenant_id,
                'position' => $position + 1,
                'name' => $step['name'],
                'assignee_type' => $step['assignee_type'],
                'assignee_id' => $step['assignee_id'] ?: null,
                'is_final' => $position === array_key_last($data['steps']),
            ]);
        }

        $this->audit->log('workflow.created', 'workflow', $workflow->id);

        return back()->with('success', 'Workflow créé.');
    }

    public function toggle(int $workflow)
    {
        $workflow = $this->workflow($workflow);
        $workflow->update(['is_active' => ! $workflow->is_active]);
        $this->audit->log('workflow.toggled', 'workflow', $workflow->id, ['active' => $workflow->is_active]);

        return back()->with('success', 'Workflow mis à jour.');
    }

    public function start(Request $request, int $document)
    {
        $document = $this->doc($document);
        $workflow = Workflow::findOrFail($request->input('workflow_id'));

        try {
            $this->workflows->start($document, $workflow, auth()->user());
        } catch (\RuntimeException $e) {
            return back()->withErrors(['workflow' => $e->getMessage()]);
        }

        return back()->with('success', 'Workflow démarré.');
    }
}
