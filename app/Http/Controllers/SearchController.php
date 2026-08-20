<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentType;
use App\Models\DocumentVersion;
use App\Models\Tag;
use App\Services\PermissionService;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __invoke(Request $request, PermissionService $permissions)
    {
        $user = auth()->user();
        $accessibleIds = $permissions->accessibleDocumentIds($user);

        $query = Document::with(['currentVersion', 'type', 'space'])
            ->whereIn('id', $accessibleIds ?: [0]);

        $q = $request->input('q') ?? '';

        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('title', 'like', "%{$q}%")
                    ->orWhere('reference', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%");
            });
        }

        if ($request->filled('type_id')) {
            $query->where('document_type_id', $request->input('type_id'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }
        if ($request->filled('tag')) {
            $query->whereHas('tags', fn ($t) => $t->where('tags.name', $request->input('tag')));
        }
        if ($request->filled('author')) {
            $query->where('created_by', $request->input('author'));
        }

        $ids = $query->pluck('documents.id');

        // Full-text search over extracted content (V1): matches versions text.
        if ($request->boolean('fulltext') && $q !== '') {
            $textIds = DocumentVersion::where('tenant_id', $user->tenant_id)
                ->where('extracted_text', 'like', "%{$q}%")
                ->pluck('document_id');
            $ids = $ids->merge($textIds)->unique();
            $query = Document::whereIn('id', $ids);
        }

        $documents = $query->orderByDesc('updated_at')->paginate(config('ged.pagination'))->withQueryString();

        return view('search', [
            'documents' => $documents,
            'q' => $q,
            'filters' => $request->all(),
            'types' => DocumentType::orderBy('name')->get(),
            'tags' => Tag::orderBy('name')->get(),
        ]);
    }
}
