<?php

namespace App\Services;

use App\Models\DocumentVersion;
use Smalot\PdfParser\Parser;

/**
 * Plain-text extraction for full-text search and RAG.
 * Returns '' when the format is not supported locally (PDF/Office binary).
 */
class TextExtractor
{
    public function extract(DocumentVersion $version): string
    {
        $path = app(StorageService::class)->path($version->file_path);
        if (! is_file($path)) {
            return '';
        }

        $mime = $version->mime_type;

        if (in_array($mime, ['text/plain', 'text/csv', 'application/json', 'text/html', 'text/markdown'], true)) {
            $content = file_get_contents($path);

            return $content === false ? '' : mb_substr($content, 0, 200000);
        }

        if ($mime === 'application/pdf' && class_exists('\Smalot\PdfParser\Parser')) {
            try {
                $parser = new Parser;

                return mb_substr($parser->parseFile($path)->getText(), 0, 200000);
            } catch (\Throwable) {
                return '';
            }
        }

        return '';
    }
}
