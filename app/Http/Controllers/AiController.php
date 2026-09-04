<?php

namespace App\Http\Controllers;

use App\Models\AiJob;
use App\Services\AiService;
use App\Services\AuditService;
use App\Services\PermissionService;
use Illuminate\Http\Request;

class AiController extends Controller
{
    public function __construct(private AiService $ai, private AuditService $audit) {}

    public function dispatch(Request $request, int $document)
    {
        $document = $this->doc($document);
        $jobType = $request->input('job_type');

        if (! in_array($jobType, ['summary', 'qa', 'classification', 'extraction', 'correction', 'comparison', 'translation'], true)) {
            return back()->withErrors(['job_type' => 'Type de job IA inconnu.']);
        }

        try {
            $job = $this->ai->dispatch(
                auth()->user(),
                $document,
                $jobType,
                ['length' => $request->integer('length', 300), 'question' => $request->input('question')]
            );
        } catch (\RuntimeException $e) {
            return back()->withErrors(['ai' => $e->getMessage()]);
        }

        return back()->with('success', 'Job IA '.($job->status === 'succeeded' ? 'terminé' : 'lancé').' ('.$job->status.').');
    }

    public function runOcr(Request $request, int $document)
    {
        $document = $this->doc($document);

        try {
            $job = $this->ai->dispatch(auth()->user(), $document, 'ocr');
        } catch (\RuntimeException $e) {
            return back()->withErrors(['ai' => $e->getMessage()]);
        }

        if ($job->status === 'succeeded') {
            $version = $document->currentVersion;
            if (! empty($job->output['text'])) {
                $version->update(['extracted_text' => $job->output['text']]);
            }
        }

        return back()->with('success', 'OCR '.$job->status.'.');
    }

    public function validateResult(Request $request, int $result)
    {
        $result = $this->aiResult($result);
        $accepted = $request->boolean('accepted');

        $this->ai->validateResult(auth()->user(), $result, $accepted);

        return back()->with('success', $accepted ? 'Résultat validé.' : 'Résultat rejeté.');
    }

    public function applyResult(Request $request, int $result)
    {
        $result = $this->aiResult($result);
        $content = $request->input('content');

        if (! $content) {
            return back()->withErrors(['content' => 'Contenu requis.']);
        }

        try {
            $this->ai->applyResult(auth()->user(), $result, $content);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['content' => $e->getMessage()]);
        }

        return back()->with('success', 'Nouvelle version créée à partir de la proposition validée.');
    }

    public function index(Request $request)
    {
        if (! app(PermissionService::class)->can(auth()->user(), 'ai.admin')) {
            abort(403, 'Accès réservé à l\'administrateur IA.');
        }

        $jobs = AiJob::with(['document', 'result'])->orderByDesc('id')->paginate(30);

        return view('admin.ai', ['jobs' => $jobs]);
    }
}
