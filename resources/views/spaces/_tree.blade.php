@foreach ($nodes as $folder)
    <li class="py-1">
        <div class="d-flex justify-content-between align-items-start">
            <span>
                <i class="bi bi-folder"></i> {{ $folder->name }}
                <span class="text-muted">({{ $folder->documents_count }} doc.)</span>
            </span>
            @if ($canManage)
            <span class="d-flex align-items-center gap-2">
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
        @php $children = $tree[$folder->id] ?? collect(); @endphp
        @if ($children->isNotEmpty())
            <ul class="list-unstyled ms-3 ps-2 border-start">
                @include('spaces._tree', ['nodes' => $children, 'tree' => $tree, 'space' => $space, 'maxDepth' => $maxDepth, 'canManage' => $canManage])
            </ul>
        @endif
    </li>
@endforeach
