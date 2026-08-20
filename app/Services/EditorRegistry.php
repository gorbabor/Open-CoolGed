<?php

namespace App\Services;

use App\Models\DocumentVersion;

/**
 * Mapping MIME/extension → éditeur et viewer (étape 1).
 * Un format accepté à l'upload a toujours un comportement défini :
 *  - 'embedded' : édition en ligne (texte/markdown/csv/json/html)
 *  - 'pdf'      : visualisation + annotations (pdf.js + pdf-lib)
 *  - 'fallback' : téléchargement → modification locale → réimport (CA-019)
 * Le viewer est indépendant de l'éditeur (consultation seule).
 */
class EditorRegistry
{
    /** @return array<string, string> MIME → éditeur */
    public function editors(): array
    {
        return config('ged.editors', []);
    }

    /** @return array<string, string> MIME → viewer JS */
    public function viewers(): array
    {
        return config('ged.viewers', []);
    }

    public function resolveEditor(DocumentVersion $version): string
    {
        return $this->editors()[$version->mime_type] ?? 'fallback';
    }

    public function resolveViewer(DocumentVersion $version): ?string
    {
        return $this->viewers()[$version->mime_type] ?? null;
    }

    public function isViewable(DocumentVersion $version): bool
    {
        return $this->resolveViewer($version) !== null;
    }

    public function isEditableInBrowser(DocumentVersion $version): bool
    {
        return in_array($this->resolveEditor($version), ['embedded', 'pdf'], true);
    }
}
