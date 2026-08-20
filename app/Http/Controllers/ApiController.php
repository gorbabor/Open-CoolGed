<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\WorkflowTask;
use App\Services\AiService;
use App\Services\AuditService;
use App\Services\PermissionService;
use App\Services\RagService;
use Illuminate\Http\Request;

class ApiController extends Controller
{
    public function __construct(
        private PermissionService $permissions,
        private AuditService $audit,
        private RagService $rag,
        private AiService $ai,
    ) {}

    private function user()
    {
        return request()->user();
    }

    public function documents(Request $request)
    {
        $accessible = $this->permissions->accessibleDocumentIds($this->user());

        $query = Document::with(['currentVersion', 'type', 'space'])
            ->whereIn('id', $accessible ?: [0]);

        if ($request->filled('q')) {
            $q = $request->input('q');
            $query->where(fn ($sub) => $sub->where('title', 'like', "%{$q}%")->orWhere('reference', 'like', "%{$q}%"));
        }

        return response()->json($query->orderByDesc('id')->paginate(config('ged.pagination')));
    }

    public function showDocument(int $document)
    {
        $document = $this->doc($document);

        if (! $this->permissions->can($this->user(), 'documents.view', $document)) {
            abort(403);
        }

        return response()->json($document->load(['versions', 'type', 'space', 'tags', 'metadataValues.definition']));
    }

    public function search(Request $request)
    {
        $q = $request->input('q') ?? '';
        if ($q === '') {
            return response()->json(['error' => 'Paramètre q requis.'], 422);
        }

        $ids = $this->permissions->accessibleDocumentIds($this->user());

        $matches = Document::whereIn('id', $ids ?: [0])
            ->where(fn ($sub) => $sub->where('title', 'like', "%{$q}%")->orWhere('reference', 'like', "%{$q}%"))
            ->limit(50)
            ->get(['id', 'title', 'reference', 'status']);

        $this->audit->log('api.search', 'document', null, ['q' => $q]);

        return response()->json($matches);
    }

    public function rag(Request $request)
    {
        $q = $request->input('q') ?? '';
        if ($q === '') {
            return response()->json(['error' => 'Paramètre q requis.'], 422);
        }

        $result = $this->rag->answer($this->user(), $q);

        return response()->json($result);
    }

    public function aiJobs(Request $request, int $document)
    {
        $document = $this->doc($document);

        if (! $this->permissions->can($this->user(), 'ai.use', $document)) {
            abort(403);
        }

        $job = $this->ai->dispatch($this->user(), $document, $request->input('job_type', 'summary'));

        return response()->json($job->load('result'));
    }

    public function tasks()
    {
        $tasks = WorkflowTask::where(fn ($q) => $q->where('assignee_user_id', $this->user()->id)->orWhere('delegated_to_id', $this->user()->id))
            ->with(['instance.document', 'step'])
            ->where('status', 'pending')
            ->get();

        return response()->json($tasks);
    }

    public function me()
    {
        return response()->json([
            'id' => $this->user()->id,
            'name' => $this->user()->name,
            'email' => $this->user()->email,
            'tenant_id' => $this->user()->tenant_id,
            'is_super_admin' => $this->user()->is_super_admin,
        ]);
    }
}
