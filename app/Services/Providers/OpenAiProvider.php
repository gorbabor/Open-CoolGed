<?php

namespace App\Services\Providers;

use App\Models\DocumentVersion;
use App\Services\Contracts\AiProvider;
use Illuminate\Support\Facades\Http;

/**
 * Fournisseur OpenAI (chat completions). La clé API est fournie à la construction
 * (résolue par AiService : tenant > plateforme). Les jobs sont traduits en prompts FR.
 */
class OpenAiProvider implements AiProvider
{
    private const ENDPOINT = 'https://api.openai.com/v1/chat/completions';

    private const MAX_INPUT_CHARS = 40000;

    public function __construct(
        private string $apiKey,
        private string $model = 'gpt-4o-mini',
    ) {}

    public function name(): string
    {
        return 'openai';
    }

    public function run(string $jobType, DocumentVersion $version, array $params = []): array
    {
        $text = mb_substr(trim((string) $version->extracted_text), 0, self::MAX_INPUT_CHARS);

        [$system, $userPrompt] = $this->prompts($jobType, $text, $params);

        $response = Http::timeout(60)
            ->withToken($this->apiKey)
            ->post(self::ENDPOINT, [
                'model' => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'temperature' => 0.3,
                'response_format' => ['type' => 'json_object'],
            ]);

        if ($response->failed()) {
            throw new \RuntimeException('OpenAI : '.$response->status().' '.mb_substr($response->body(), 0, 200));
        }

        $content = $response->json('choices.0.message.content', '{}');
        $parsed = json_decode((string) $content, true);

        if (! is_array($parsed)) {
            throw new \RuntimeException('OpenAI : réponse JSON invalide.');
        }

        return [
            'content' => $this->shapeResult($jobType, $parsed, $version),
            'confidence' => (float) ($parsed['confidence'] ?? 0.85),
        ];
    }

    /** @return array{0: string, 1: string} [system, user] */
    private function prompts(string $jobType, string $text, array $params): array
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
