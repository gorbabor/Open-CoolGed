@extends('layouts.standalone')

@section('title', 'PDF — '.$version->file_name)
@section('toolbar-title', 'Annotation PDF : '.$version->file_name)

@section('head')
    <style>
        #pdfCanvasWrap { position: relative; background: #fff; }
        #pdfCanvasWrap canvas { display: block; box-shadow: 0 2px 8px rgba(0,0,0,.15); }
        .hl-rect { position: absolute; background: rgba(255, 230, 0, .4); border: 1px solid rgba(255,180,0,.8); pointer-events: none; }
        .mode-btn.active { outline: 3px solid #0d6efd; }
    </style>
@endsection

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex align-items-center gap-2 mb-3">
        <button id="modeText" class="btn btn-sm btn-outline-primary mode-btn" title="Texte libre (clic pour placer)">T</button>
        <button id="modeHighlight" class="btn btn-sm btn-outline-warning mode-btn" title="Surligner (clic-glisser)">🖍 Surligner</button>
        <button id="modeNote" class="btn btn-sm btn-outline-info mode-btn" title="Note (clic pour placer)">🗒 Note</button>
        <button id="undoBtn" class="btn btn-sm btn-outline-secondary">Annuler</button>
        <span class="text-muted small ms-2" id="pageInfo"></span>
        <button id="saveBtn" class="btn btn-success btn-sm ms-auto">
            <i class="bi bi-check-lg"></i> Enregistrer (nouvelle version)
        </button>
    </div>
    <div id="pdfCanvasWrap" class="mx-auto"></div>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/pdfjs-dist@3.11.174/build/pdf.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/pdf-lib@1.17.1/dist/pdf-lib.min.js"></script>
<script>
const contentUrl = @json(route('documents.preview-content', [$version->document, $version]));
const saveUrl = @json(route('office.pdf.save', $token));
const csrf = document.querySelector('meta[name="csrf-token"]').content;

let pdf = null;
let pdfDoc = null;          // pdf-lib document
let pdfBytes = null;        // original bytes
let pages = [];             // canvas par page
let mode = 'text';
let annotations = [];       // { page, type, x, y, w, h, text }
let undoStack = [];
let drag = null;

function scale() {
    const wrap = document.getElementById('pdfCanvasWrap');
    const rect = wrap.getBoundingClientRect();
    return rect.width / 794; // A4 ~794pt @96dpi → rendu responsive
}

async function renderAll() {
    const wrap = document.getElementById('pdfCanvasWrap');
    wrap.innerHTML = '';
    pages = [];
    const s = scale();
    for (let i = 1; i <= pdf.numPages; i++) {
        const page = await pdf.getPage(i);
        const canvas = document.createElement('canvas');
        const viewport = page.getViewport({ scale: 1.4 });
        canvas.width = viewport.width;
        canvas.height = viewport.height;
        canvas.style.width = '100%';
        canvas.dataset.page = i;
        canvas.style.touchAction = 'none';
        wrap.appendChild(canvas);
        await page.render({ canvasContext: canvas.getContext('2d'), viewport }).promise;
        pages.push({ canvas, viewport, page });
        canvas.addEventListener('mousedown', e => onDown(e, i));
        canvas.addEventListener('mousemove', e => onMove(e, i));
        canvas.addEventListener('mouseup', e => onUp(e, i));
    }
}

function pos(e, viewport) {
    const rect = e.target.getBoundingClientRect();
    return {
        x: ((e.clientX - rect.left) / rect.width) * viewport.width,
        y: ((e.clientY - rect.top) / rect.height) * viewport.height,
    };
}

function onDown(e, pageNo) {
    const { page, viewport } = pages[pageNo - 1];
    const p = pos(e, viewport);
    if (mode === 'highlight') {
        drag = { page: pageNo, x: p.x, y: p.y, w: 0, h: 0, el: null };
    } else {
        const text = prompt(mode === 'note' ? 'Contenu de la note :' : 'Texte à ajouter :');
        if (text) {
            annotations.push({ page: pageNo, type: mode, x: p.x, y: p.y, w: 0, h: 0, text });
            drawAnnotations();
        }
    }
}

function onMove(e, pageNo) {
    if (!drag || drag.page !== pageNo) return;
    const { viewport } = pages[pageNo - 1];
    const p = pos(e, viewport);
    drag.w = p.x - drag.x;
    drag.h = p.y - drag.y;
    if (!drag.el) {
        drag.el = document.createElement('div');
        drag.el.className = 'hl-rect';
        pages[pageNo - 1].canvas.parentElement.appendChild(drag.el);
    }
    Object.assign(drag.el.style, {
        left: Math.min(drag.x, drag.x + drag.w) + 'px',
        top: Math.min(drag.y, drag.y + drag.h) + 'px',
        width: Math.abs(drag.w) + 'px',
        height: Math.abs(drag.h) + 'px',
    });
}

function onUp(e, pageNo) {
    if (!drag) return;
    if (drag.w > 5 && drag.h > 5) {
        annotations.push({ page: drag.page, type: 'highlight', x: Math.min(drag.x, drag.x + drag.w), y: Math.min(drag.y, drag.y + drag.h), w: Math.abs(drag.w), h: Math.abs(drag.h), text: '' });
    }
    drag = null;
    drawAnnotations();
}

function drawAnnotations() {
    pages.forEach(({ canvas, viewport }, idx) => {
        const pageNo = idx + 1;
        canvas.parentElement.querySelectorAll('.ann').forEach(el => el.remove());
        annotations.filter(a => a.page === pageNo).forEach(a => {
            const el = document.createElement('div');
            el.className = 'ann';
            const left = (a.x / viewport.width) * 100;
            const top = (a.y / viewport.height) * 100;
            const width = ((a.w || 0) / viewport.width) * 100;
            const height = ((a.h || 18) / viewport.height) * 100;
            if (a.type === 'highlight') {
                el.style.cssText = 'position:absolute;left:' + left + '%;top:' + top + '%;width:' + width + '%;height:' + height + '%;background:rgba(255,230,0,.4);border:1px solid rgba(255,180,0,.8);pointer-events:none;';
            } else {
                el.style.cssText = 'position:absolute;left:' + left + '%;top:' + top + '%;background:' + (a.type === 'note' ? '#e7f3ff' : '#fffbe6') + ';border:1px solid #999;padding:2px 6px;font-size:11px;max-width:60%;pointer-events:none;';
                el.textContent = (a.type === 'note' ? '🗒 ' : '') + a.text;
            }
            canvas.parentElement.appendChild(el);
        });
    });
    undoStack = annotations.map(a => ({ ...a }));
}

function undo() {
    const last = undoStack.pop();
    if (!last) return;
    annotations = annotations.filter(a => a !== last);
    drawAnnotations();
}

async function save() {
    const btn = document.getElementById('saveBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Enregistrement…';
    try {
        const { PDFDocument, rgb } = PDFLib;
        pdfDoc = await PDFDocument.load(pdfBytes);
        for (const a of annotations) {
            const page = pdfDoc.getPage(a.page - 1);
            const { height } = page.getSize();
            if (a.type === 'highlight') {
                page.drawRectangle({
                    x: a.x, y: height - a.y - a.h, width: a.w, height: a.h,
                    color: rgb(1, 0.9, 0.3), opacity: 0.45,
                });
            } else {
                page.drawText(a.text, {
                    x: a.x, y: height - a.y - 12, size: 10,
                    color: rgb(0.1, 0.1, 0.1),
                });
            }
        }
        const saved = await pdfDoc.save();
        const blob = new Blob([saved], { type: 'application/pdf' });
        const fd = new FormData();
        fd.append('file', blob, 'annotated.pdf');
        const res = await fetch(saveUrl, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf },
            body: fd,
        });
        if (res.redirected) { window.location.href = res.url; return; }
        throw new Error('Erreur serveur (' + res.status + ')');
    } catch (e) {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-lg"></i> Enregistrer (nouvelle version)';
        alert('Enregistrement impossible : ' + e.message);
    }
}

document.addEventListener('DOMContentLoaded', async () => {
    document.getElementById('modeText').classList.add('active');
    document.getElementById('modeText').onclick = () => setMode('text');
    document.getElementById('modeHighlight').onclick = () => setMode('highlight');
    document.getElementById('modeNote').onclick = () => setMode('note');
    document.getElementById('undoBtn').onclick = undo;
    document.getElementById('saveBtn').onclick = save;

    function setMode(m) {
        mode = m;
        document.querySelectorAll('.mode-btn').forEach(b => b.classList.remove('active'));
        document.getElementById(mode === 'text' ? 'modeText' : mode === 'highlight' ? 'modeHighlight' : 'modeNote').classList.add('active');
    }

    try {
        const res = await fetch(contentUrl);
        if (!res.ok) throw new Error('HTTP ' + res.status);
        let bytes = new Uint8Array(await res.arrayBuffer());
        if (bytes.length === 0) {
            throw new Error('réponse vide — un gestionnaire de téléchargement (ex. IDM) a probablement intercepté la requête. Désactivez-le pour ce site (bouton flottant IDM → « Disable IDM for this site ») puis rechargez.');
        }
        // Strip the anti-download-manager padding (64 spaces) if present,
        // so pdf-lib always receives a clean PDF.
        if (bytes.length > 64 && bytes.slice(0, 64).every(b => b === 32)) {
            bytes = bytes.slice(64);
        }
        pdfBytes = bytes;
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdn.jsdelivr.net/npm/pdfjs-dist@3.11.174/build/pdf.worker.min.js';
        pdf = await pdfjsLib.getDocument({ data: pdfBytes }).promise;
        document.getElementById('pageInfo').textContent = pdf.numPages + ' page(s)';
        await renderAll();
    } catch (e) {
        document.getElementById('pdfCanvasWrap').innerHTML =
            '<div class="alert alert-danger">Impossible de charger le PDF : ' + e.message + '</div>';
    }
});
</script>
@endsection
