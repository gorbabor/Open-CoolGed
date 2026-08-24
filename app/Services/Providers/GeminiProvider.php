<?php

namespace App\Services\Providers;

use App\Models\DocumentVersion;
use App\Services\Contracts\AiProvider;
use Illuminate\Support\Facades\Http;

/**
 * Fournisseur Google Gemini (Generative Language API — format natif generateContent).
 * La clé API est passée en paramètre de requête (key=...).
 */
class GeminiProvider implements AiProvider
{
    private const BASE_URL = 'https://generativelanguage.googleapis.com/v1beta';

    private const MAX_INPUT_CHARS = 40000;

    public function __construct(
        private string $apiKey,
        private string $model = 'gemini-2.0-flash',
    ) {}

    public function name(): string
    {
        return 'gemini';
    }

    public function run(string $jobType, DocumentVersion $version, array $params = []): array
    {
        $text = mb_substr(trim((string) $version->extracted_text), 0, self::MAX_INPUT_CHARS);

        [$system, $userPrompt] = $this->prompts($jobType, $text, $params, $version);

        $response = Http::timeout(60)
            ->withQueryParameters(['key' => $this->apiKey])
            ->post(self::BASE_URL.'/models/'.$this->model.':generateContent', [
                'systemInstruction' => ['parts' => [['text' => $system]]],
                'contents' => [['role' => 'user', 'parts' => [['text' => $userPrompt]]]],
                'generationConfig' => ['temperature' => 0.3],
            ]);

        if ($response->failed()) {
            throw new \RuntimeException('Gemini : '.$response->status().' '.mb_substr($response->body(), 0, 200));
        }

        $text = $response->json('candidates.0.content.parts.0.text', '{}');
        $parsed = json_decode((string) $text, true);

        if (! is_array($parsed)) {
            throw new \RuntimeException('Gemini : réponse JSON invalide.');
        }

        return [
            'content' => $this->shapeResult($jobType, $parsed, $version),
            'confidence' => (float) ($parsed['confidence'] ?? 0.85),
        ];
    }

    /** @return array{0: string, 1: string} [system, user] */
    private function prompts(string $jobType, string $text, array $params, DocumentVersion $version): array
    {
        $base = 'Tu es un assistant documentaire en français. Réponds UNIQUEMENT en JSON valide. ';

        return match ($jobType) {
            'summary' => [$base.'Génère un résumé.', 'Résume ce document en '.($params['length'] ?? 300)." caractères maximum :\n\n{$text}"],
            'qa' => [$base.'Réponds à la question à partir du document.', 'Question : '.($params['question'] ?? '')."\n\nDocument :\n\n{$text}"],
            'classification' => [$base.'Classe le document (type, catégorie, tags).', "Classe ce document :\n\n{$text}"],
            'extraction' => [$base.'Extrais les champs structurés.', "Extrais référence, titre, date du document :\n\n{$text}"],
            'correction' => [$base.'Propose une correction orthographique et grammaticale.', "Corrige ce texte :\n\n{$text}"],
            'comparison' => [$base.'Compare les documents.', 'Compare la version '.($version->version ?? '?').' avec la précédente si disponible.'],
            'translation' => [$base.'Traduis le texte en '.($params['target_lang'] ?? 'anglais').'.', "Traduis :\n\n{$text}"],
            'ocr' => [$base.'Extrais le texte brut.', "Extrais le texte de ce document :\n\n{$text}"],
            default => throw new \RuntimeException("Type de job IA inconnu : {$jobType}"),
        };
    }

    private function shapeResult(string $jobType, array $parsed, DocumentVersion $version): mixed
    {
        return match ($jobType) {
            'summary' => ['summary' => $parsed['summary'] ?? ''],
            'qa' => ['answer' => $parsed['answer'] ?? '', 'source' => $parsed['source'] ?? 'document'],
            'classification' => ['type' => $parsed['type'] ?? 'document', 'category' => $parsed['category'] ?? 'général', 'tags' => $parsed['tags'] ?? []],
            'extraction' => ['reference' => $parsed['reference'] ?? null, 'title' => $parsed['title'] ?? null, 'date' => $parsed['date'] ?? null],
            'correction' => ['suggestion' => $parsed['suggestion'] ?? ''],
            'comparison' => ['diff' => $parsed['diff'] ?? ''],
            'translation' => ['translation' => $parsed['translation'] ?? ''],
            'ocr' => ['text' => $parsed['text'] ?? '', 'language' => $parsed['language'] ?? 'fr'],
            default => $parsed,
        };
    }
}
