@foreach ($nodes as $folder)
    @php
        $children = $tree[$folder->id] ?? collect();
        $folderLevel = $level ?? 1;
    @endphp
    <li class="folder-node">
        <div class="folder-row">
            @if ($children->isNotEmpty())
            <button type="button" class="folder-caret" aria-expanded="false" title="{{ __('Afficher les sous-dossiers') }}">
                <i class="bi bi-chevron-right"></i>
            </button>
            @else
            <span class="caret-spacer"></span>
            @endif
            <button type="button" class="folder-toggle {{ $folderLevel === 1 ? 'fw-semibold' : 'folder-sub' }}"
                    data-url="{{ route('folders.documents', $folder) }}" aria-expanded="false"
                    title="{{ __('Afficher les documents du dossier') }}">
                <i class="bi {{ $folderLevel === 1 ? 'bi-folder-fill folder-icon-root' : 'bi-folder2 folder-icon-sub' }}"></i> {{ $folder->name }}
            </button>
            @if ($folder->documents_count > 0)
            <span class="folder-count" title="{{ $folder->documents_count }} {{ $folder->documents_count > 1 ? __('documents') : __('document') }}">{{ $folder->documents_count }} doc.</span>
            @endif
            @if ($canManage)
            <span class="folder-actions d-flex align-items-center gap-2 ms-auto">
                @if ($folder->depth() < $maxDepth)
                <details class="d-inline">
                    <summary class="d-inline text-success" style="cursor:pointer" title="{{ __('Ajouter un sous-dossier') }}">＋</summary>
                    <form method="POST" action="{{ route('folders.store', $space) }}" class="d-flex gap-1 mt-1">
                        @csrf
                        <input type="hidden" name="parent_id" value="{{ $folder->id }}">
                        <input type="text" name="name" class="form-control form-control-sm" placeholder="{{ __('Sous-dossier') }}" required maxlength="255">
                        <button class="btn btn-sm btn-outline-primary">{{ __('Ajouter') }}</button>
                    </form>
                </details>
                @endif
                <details class="d-inline">
                    <summary class="d-inline text-secondary" style="cursor:pointer" title="{{ __('Renommer') }}">✎</summary>
                    <form method="POST" action="{{ route('folders.rename', $folder) }}" class="d-flex gap-1 mt-1">
                        @csrf
                        <input type="text" name="name" class="form-control form-control-sm" value="{{ $folder->name }}" required maxlength="255">
                        <button class="btn btn-sm btn-outline-secondary">{{ __('Renommer') }}</button>
                    </form>
                </details>
                <form method="POST" action="{{ route('folders.delete', $folder) }}">@csrf @method('DELETE')
                    <button class="btn btn-sm btn-link text-danger p-0" title="{{ __('Supprimer') }}">✕</button>
                </form>
            </span>
            @endif
        </div>
        <div class="folder-documents d-none"></div>
        @if ($children->isNotEmpty())
            <ul class="list-unstyled folder-children d-none">
                @include('spaces._tree', ['nodes' => $children, 'tree' => $tree, 'space' => $space, 'maxDepth' => $maxDepth, 'canManage' => $canManage, 'level' => $folderLevel + 1])
            </ul>
        @endif
    </li>
@endforeach
