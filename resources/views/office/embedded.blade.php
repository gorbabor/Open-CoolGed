@extends('layouts.standalone')

@section('title', 'Édition — '.$version->file_name)
@section('toolbar-title', 'Édition : '.$version->file_name)

@section('head')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.css">
    <style>
        .CodeMirror { height: 100%; font-size: 14px; }
        #previewPane { height: 100%; overflow: auto; padding: 1rem; }
        .markdown-body img { max-width: 100%; }
    </style>
@endsection

@section('content')
<div class="editor-wrap d-flex">
    <div class="flex-fill border-end bg-white">
        <textarea id="editor" class="w-100 h-100"></textarea>
    </div>
    <div class="flex-fill d-none d-md-block" id="previewWrap">
        <div id="previewPane" class="bg-white"></div>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/marked@9.1.6/marked.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/dompurify@3.1.5/dist/purify.min.js"></script>
<script>
const contentUrl = @json(route('documents.preview-content', [$version->document, $version]));
const saveUrl = @json(route('office.embedded.save', $token));
const isMarkdown = @json($version->mime_type === 'text/markdown');
const mime = @json($version->mime_type);

let editor = null;

async function init() {
    const res = await fetch(contentUrl);
    if (!res.ok) throw new Error('HTTP ' + res.status);
    const content = await res.text();

    editor = CodeMirror.fromTextArea(document.getElementById('editor'), {
        lineNumbers: true,
        mode: mime === 'text/html' ? 'xml' : mime === 'application/json' ? { name: 'javascript', json: true } : 'text/plain',
        lineWrapping: true,
    });
    editor.setValue(content);

    if (isMarkdown) {
        const render = () => {
            document.getElementById('previewPane').innerHTML =
                DOMPurify.sanitize(marked.parse(editor.getValue()));
        };
        editor.on('change', render);
        render();
    }
}

async function save() {
    const btn = document.getElementById('saveBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Enregistrement…';
    try {
        const res = await fetch(saveUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            },
            body: JSON.stringify({ content: editor.getValue() }),
        });
        if (res.redirected) { window.location.href = res.url; return; }
        const data = await res.json().catch(() => ({}));
        throw new Error(data.message || 'Erreur serveur');
    } catch (e) {
        btn.disabled = false;
        btn.innerHTML = 'Enregistrer';
        alert('Enregistrement impossible : ' + e.message);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    // CSRF token via cookie + POST form fallback: use a hidden form post for reliability.
    const btn = document.createElement('button');
    btn.id = 'saveBtn';
    btn.className = 'btn btn-success btn-sm ms-auto';
    btn.innerHTML = '<i class="bi bi-check-lg"></i> Enregistrer (nouvelle version)';
    btn.onclick = save;
    document.querySelector('.toolbar').appendChild(btn);

    init().catch(e => {
        document.getElementById('editor').outerHTML =
            '<div class="alert alert-danger m-3">Impossible de charger le contenu : ' + e.message + '</div>';
    });
});
</script>
@endsection
