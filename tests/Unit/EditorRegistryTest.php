<?php

namespace Tests\Unit;

use App\Models\DocumentVersion;
use App\Services\DocumentService;
use App\Services\EditorRegistry;
use Tests\TestCase;

class EditorRegistryTest extends TestCase
{
    private function version(string $mime): DocumentVersion
    {
        $v = new DocumentVersion;
        $v->mime_type = $mime;

        return $v;
    }

    public function test_text_formats_map_to_embedded_editor(): void
    {
        $registry = app(EditorRegistry::class);

        foreach (['text/plain', 'text/markdown', 'text/csv', 'application/json', 'text/html'] as $mime) {
            $this->assertSame('embedded', $registry->resolveEditor($this->version($mime)), $mime);
        }
    }

    public function test_pdf_maps_to_pdf_editor(): void
    {
        $registry = app(EditorRegistry::class);

        $this->assertSame('pdf', $registry->resolveEditor($this->version('application/pdf')));
    }

    public function test_office_formats_fall_back_without_service(): void
    {
        $registry = app(EditorRegistry::class);

        foreach ([
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        ] as $mime) {
            $this->assertSame('fallback', $registry->resolveEditor($this->version($mime)), $mime);
        }
    }

    public function test_viewers_are_resolved_for_office_and_text(): void
    {
        $registry = app(EditorRegistry::class);

        $this->assertSame('pdf', $registry->resolveViewer($this->version('application/pdf')));
        $this->assertSame('docx', $registry->resolveViewer($this->version('application/vnd.openxmlformats-officedocument.wordprocessingml.document')));
        $this->assertSame('xlsx', $registry->resolveViewer($this->version('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')));
        $this->assertSame('pptx', $registry->resolveViewer($this->version('application/vnd.openxmlformats-officedocument.presentationml.presentation')));
        $this->assertSame('markdown', $registry->resolveViewer($this->version('text/markdown')));
        $this->assertSame('text', $registry->resolveViewer($this->version('text/plain')));
        $this->assertNull($registry->resolveViewer($this->version('application/octet-stream')));
    }

    public function test_markdown_is_accepted_by_upload_policy(): void
    {
        $this->assertContains('text/markdown', app(DocumentService::class)->allowedMimes());
    }
}
