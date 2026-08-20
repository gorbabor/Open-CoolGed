<?php

namespace App\Services\Providers;

use App\Models\DocumentVersion;
use App\Services\Contracts\AiProvider;

/**
 * Deterministic local provider used for development, tests and demo.
 * Never modifies the source document (RM-017): it only produces proposals.
 */
class MockAiProvider implements AiProvider
{
    public function name(): string
    {
        return 'mock';
    }

    public function run(string $jobType, DocumentVersion $version, array $params = []): array
    {
        $text = trim((string) $version->extracted_text);

        if ($jobType === 'ocr') {
            return [
                'content' => ['text' => $text, 'language' => 'fr'],
                'confidence' => $text === '' ? 0.1 : 0.95,
            ];
        }

        if ($text === '') {
            throw new \RuntimeException('Aucun contenu textuel disponible pour ce document.');
        }

        return match ($jobType) {
            'summary' => [
                'content' => ['summary' => mb_substr($text, 0, (int) ($params['length'] ?? 300))],
                'confidence' => 0.9,
            ],
            'qa' => [
                'content' => [
                    'answer' => mb_substr($text, 0, 400),
                    'source' => 'extrait du document (sections '.mb_substr($text, 0, 80).'…)',
                ],
                'confidence' => 0.8,
            ],
            'classification' => [
                'content' => ['type' => 'document', 'category' => 'général', 'tags' => ['document', 'classé']],
                'confidence' => 0.75,
            ],
            'extraction' => [
                'content' => [
                    'reference' => $version->document?->reference,
                    'title' => $version->document?->title,
                    'date' => $version->created_at?->toDateString(),
                ],
                'confidence' => 0.7,
            ],
            'correction' => [
                'content' => ['suggestion' => $text],
                'confidence' => 0.6,
            ],
            'comparison' => [
                'content' => ['diff' => 'Version analysée : '.$version->version.'. Aucune divergence détectée (fournisseur mock).'],
                'confidence' => 0.5,
            ],
            'translation' => [
                'content' => ['translation' => '[traduction simulée] '.mb_substr($text, 0, 200)],
                'confidence' => 0.4,
            ],
            default => throw new \RuntimeException("Type de job IA inconnu : {$jobType}"),
        };
    }
}
