<?php

namespace App\Http\Controllers;

use App\Models\WorkflowTask;
use App\Services\WorkflowService;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function __construct(private WorkflowService $workflows) {}

    public function index()
    {
        $user = auth()->user();

        $tasks = WorkflowTask::where(fn ($q) => $q->where('assignee_user_id', $user->id)->orWhere('delegated_to_id', $user->id))
            ->with(['instance.document', 'step', 'instance.workflow'])
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->orderBy('due_at')
            ->get();

        return view('tasks', ['tasks' => $tasks]);
    }

    public function decide(Request $request, int $task)
    {
        $task = $this->task($task);

        try {
            $this->workflows->decide(
                auth()->user(),
                $task,
                $request->input('decision') ?: 'approve',
                $request->input('comment')
            );
        } catch (\RuntimeException $e) {
            return back()->withErrors(['task' => $e->getMessage()]);
        }

        return back()->with('success', 'Décision enregistrée.');
    }
}
