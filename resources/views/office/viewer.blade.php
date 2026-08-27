@extends('layouts.standalone')

@section('title', 'Aperçu — '.$document->title)
@section('toolbar-title', 'Aperçu : '.$document->title.' (v'.$version->version.')')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="text-muted small">{{ $version->file_name }} · {{ $version->mime_type }}</div>
        <a href="{{ route('documents.show', $document) }}" target="_top" class="btn btn-sm btn-outline-secondary">Fiche document</a>
    </div>
    <div id="viewer" class="card p-3 bg-white">
        <div class="text-center text-muted py-5" id="loading">Chargement de l'aperçu…</div>
    </div>
</div>
@endsection

@section('head')
    @if ($viewerType === 'pdf')
        <script src="https://cdn.jsdelivr.net/npm/pdfjs-dist@3.11.174/build/pdf.min.js"></script>
    @elseif ($viewerType === 'docx')
        <script src="https://cdn.jsdelivr.net/npm/jszip@3.10.1/dist/jszip.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/docx-preview@0.3.2/dist/docx-preview.min.js"></script>
    @elseif ($viewerType === 'xlsx')
        <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/dompurify@3.1.5/dist/purify.min.js"></script>
    @elseif ($viewerType === 'pptx')
        <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/jszip@3.10.1/dist/jszip.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/jszip-utils@0.1.0/dist/jszip-utils.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/filesaver@1.3.0/FileSaver.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/pptxjs@1.2.1/dist/pptxjs.min.js"></script>
    @elseif ($viewerType === 'markdown')
        <script src="https://cdn.jsdelivr.net/npm/marked@9.1.6/marked.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/dompurify@3.1.5/dist/purify.min.js"></script>
    @endif
@endsection

@section('scripts')
<script>
const contentUrl = @json(route('documents.preview-content', [$document, $version]));
const viewerType = @json($viewerType);

async function load() {
    const res = await fetch(contentUrl);
    if (!res.ok) throw new Error('HTTP ' + res.status);
    const blob = await res.blob();
    if (blob.size === 0) {
        throw new Error('réponse vide (' + res.status + ', 0 octet) — un gestionnaire de téléchargement (ex. IDM) a probablement intercepté la requête. Désactivez-le pour ce site (bouton flottant IDM → « Disable IDM for this site ») puis rechargez.');
    }
    const box = document.getElementById('viewer');
    const loading = document.getElementById('loading');
    if (loading) loading.remove();

    if (viewerType === 'text') {
        const pre = document.createElement('pre');
        pre.className = 'mb-0';
        pre.style.whiteSpace = 'pre-wrap';
        pre.textContent = await blob.text();
        box.appendChild(pre);
    } else if (viewerType === 'markdown') {
        const md = await blob.text();
        const div = document.createElement('div');
        div.className = 'markdown-body';
        div.innerHTML = DOMPurify.sanitize(marked.parse(md));
        box.appendChild(div);
    } else if (viewerType === 'image') {
        const img = document.createElement('img');
        img.className = 'img-fluid';
        img.src = URL.createObjectURL(blob);
        box.appendChild(img);
    } else if (viewerType === 'pdf') {
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdn.jsdelivr.net/npm/pdfjs-dist@3.11.174/build/pdf.worker.min.js';
        const data = await blob.arrayBuffer();
        const pdf = await pdfjsLib.getDocument({ data }).promise;
        for (let i = 1; i <= pdf.numPages; i++) {
            const page = await pdf.getPage(i);
            const canvas = document.createElement('canvas');
            const viewport = page.getViewport({ scale: 1.5 });
            canvas.width = viewport.width;
            canvas.height = viewport.height;
            canvas.className = 'mb-3 border rounded';
            canvas.style.maxWidth = '100%';
            box.appendChild(canvas);
            await page.render({ canvasContext: canvas.getContext('2d'), viewport }).promise;
        }
    } else if (viewerType === 'docx') {
        await docx.renderAsync(blob, box, null, { inWrapper: true });
    } else if (viewerType === 'xlsx') {
        const data = await blob.arrayBuffer();
        const wb = XLSX.read(data, { type: 'array' });
        const tabs = document.createElement('div');
        tabs.className = 'nav nav-tabs mb-2';
        const panes = document.createElement('div');
        wb.SheetNames.forEach((name, i) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'nav-link' + (i === 0 ? ' active' : '');
            btn.textContent = name;
            btn.dataset.sheet = name;
            btn.onclick = () => {
                tabs.querySelectorAll('.nav-link').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                renderSheet(name);
            };
            tabs.appendChild(btn);
            panes.innerHTML = '';
        });
        const renderSheet = (name) => {
            const ws = wb.Sheets[name];
            const html = XLSX.utils.sheet_to_html(ws, { id: 'sheet' });
            panes.innerHTML = DOMPurify.sanitize(html);
            panes.querySelectorAll('table').forEach(t => { t.className = 'table table-sm table-bordered'; });
        };
        box.appendChild(tabs);
        box.appendChild(panes);
        renderSheet(wb.SheetNames[0]);
    } else if (viewerType === 'pptx') {
        try {
            await new Promise((resolve, reject) => {
                $('#viewer').pptxToHtml({
                    data: blob,
                    callback: () => resolve(),
                    fallback: () => reject(new Error('rendu impossible')),
                });
            });
        } catch (e) {
            box.innerHTML = '<div class="alert alert-warning mb-0">Aperçu PowerPoint indisponible pour ce fichier. <a href="' + contentUrl + '">Télécharger</a> pour consulter.</div>';
        }
    }
}

load().catch(e => {
    const loading = document.getElementById('loading');
    const msg = '<div class="alert alert-danger mb-0">Impossible de charger l\'aperçu : ' + e.message + '</div>';
    if (loading) {
        loading.outerHTML = msg;
    } else {
        const box = document.getElementById('viewer');
        if (box) box.innerHTML = msg;
        else document.body.insertAdjacentHTML('afterbegin', msg);
    }
});
</script>
@endsection
