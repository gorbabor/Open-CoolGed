<?php

namespace App\Http\Controllers;

use App\Models\DocumentVersion;
use App\Services\AuditService;
use App\Services\DocumentService;
use App\Services\EditorRegistry;
use App\Services\OfficeService;
use App\Services\PermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class OfficeController extends Controller
{
    public function __construct(
        private OfficeService $office,
        private DocumentService $documents,
        private EditorRegistry $registry,
        private PermissionService $permissions,
        private AuditService $audit,
    ) {}

    /** Start an online edit session (check-out + scoped token), routed to the right editor. */
    public function start(int $document)
    {
        $document = $this->doc($document);

        try {
            $session = $this->office->startEdit(auth()->user(), $document);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['office' => $e->getMessage()]);
        }

        $editor = $this->registry->resolveEditor($session->version);

        return match ($editor) {
            'embedded' => redirect()->route('office.embedded', ['token' => $session->raw_token]),
            'pdf' => redirect()->route('office.pdf', ['token' => $session->raw_token]),
            default => redirect()->route('office.download', ['token' => $session->raw_token])
                ->with('success', 'Mode repli : téléchargez, modifiez, puis réimportez (nouvelle version).'),
        };
    }

    /** Token-scoped download (never a permanent public URL — RM-012). */
    public function download(string $token)
    {
        $session = $this->office->downloadViaToken($token);
        $version = $session->version;

        return Storage::disk(config('ged.storage_disk'))
            ->download($version->file_path, $version->file_name);
    }

    /** Return the edited file: token + identity checked, new version created. */
    public function returnFile(Request $request, string $token)
    {
        $request->validate(['file' => ['required', 'file']]);

        try {
            $version = $this->office->returnFile(auth()->user(), $token, $request->file('file'));
        } catch (\RuntimeException $e) {
            return back()->withErrors(['office' => $e->getMessage()]);
        }

        return redirect()->route('documents.show', $version->document_id)->with('success', 'Nouvelle version créée ('.$version->version.').');
    }

    /** Fallback re-import after a plain download (CA-019). */
    public function reimport(Request $request, int $document)
    {
        $document = $this->doc($document);
        $request->validate(['file' => ['required', 'file']]);

        try {
            $this->documents->addVersion(auth()->user(), $document, $request->file('file'), 'Réimport après édition locale');
            DocumentVersion::withoutGlobalScopes()->where('document_id', $document->id)->update(['lock_token' => null]);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }

        return back()->with('success', 'Fichier réimporté : nouvelle version créée.');
    }

    /** Browser viewer page (full screen, standalone) for viewable formats. */
    public function viewer(int $document)
    {
        $document = $this->doc($document);

        if (! $this->permissions->can(auth()->user(), 'documents.preview', $document)
            && ! $this->permissions->can(auth()->user(), 'documents.edit', $document)) {
            abort(403);
        }

        $version = $document->currentVersion;
        if (! $version || ! $this->registry->isViewable($version)) {
            abort(404, 'Aucun aperçu disponible pour ce format.');
        }

        $this->audit->log('document.previewed', 'document_version', $version->id);

        return view('office.viewer', [
            'document' => $document,
            'version' => $version,
            'viewerType' => $this->registry->resolveViewer($version),
        ]);
    }

    /** Embedded text/markdown editor (session-scoped). */
    public function embedded(string $token)
    {
        $session = $this->office->downloadViaToken($token);
        $version = $session->version;

        return view('office.embedded', ['session' => $session, 'version' => $version, 'token' => $token]);
    }

    /** Save content from the embedded editor → new version, lock released. */
    public function embeddedSave(Request $request, string $token)
    {
        $content = $request->input('content');

        if ($content === null) {
            return back()->withErrors(['content' => 'Contenu manquant.']);
        }

        try {
            $version = $this->office->saveEmbedded(auth()->user(), $token, $content);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['office' => $e->getMessage()]);
        }

        return redirect()->route('documents.show', $version->document_id)
            ->with('success', 'Nouvelle version créée ('.$version->version.').');
    }

    /** PDF annotation editor (session-scoped). */
    public function pdf(string $token)
    {
        $session = $this->office->downloadViaToken($token);
        $version = $session->version;

        return view('office.pdf', ['session' => $session, 'version' => $version, 'token' => $token]);
    }

    /** Save the annotated PDF (uploaded by the client) → new version. */
    public function pdfSave(Request $request, string $token)
    {
        $request->validate(['file' => ['required', 'file']]);

        try {
            $version = $this->office->returnFile(auth()->user(), $token, $request->file('file'));
        } catch (\RuntimeException $e) {
            return back()->withErrors(['office' => $e->getMessage()]);
        }

        return redirect()->route('documents.show', $version->document_id)
            ->with('success', 'Nouvelle version créée ('.$version->version.').');
    }

    /** Stream file content for viewers (permission checked, no public URL). */
    public function previewContent(int $document, int $version)
    {
        $document = $this->doc($document);
        $version = $this->version($version);

        if (! $this->permissions->can(auth()->user(), 'documents.preview', $document)
            && ! $this->permissions->can(auth()->user(), 'documents.edit', $document)) {
            abort(403);
        }

        $path = Storage::disk(config('ged.storage_disk'))->path($version->file_path);

        /*
         * Anti download-manager padding: this endpoint only feeds in-browser
         * viewers/editors (they parse bytes themselves). PDFs are prefixed with
         * 64 spaces — the PDF spec allows up to 1024 bytes before "%PDF", which
         * pdf.js and pdf-lib handle — so download managers (e.g. IDM) that detect
         * "%PDF" at offset 0 do not intercept the stream (RM-012: no public URL).
         * The real MIME is preserved on documents.download.
         */
        if ($version->mime_type === 'application/pdf') {
            return response()->stream(function () use ($path) {
                echo str_repeat(' ', 64);
                readfile($path);
            }, 200, [
                'Content-Type' => 'application/octet-stream',
                'Content-Disposition' => 'inline; filename="'.rawurlencode($version->file_name).'"',
            ]);
        }

        return response()->file($path, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="'.rawurlencode($version->file_name).'"',
        ]);
    }
}
