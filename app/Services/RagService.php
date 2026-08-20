<?php

namespace App\Services;

use App\Models\Document;
use App\Models\RagChunk;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Retrieval-Augmented Generation, V2.
 * SEC-016 / RM-019: permission filtering happens BEFORE any segment is
 * selected or sent to the model. The index is partitioned per tenant.
 */
class RagService
{
    public function __construct(
        private PermissionService $permissions,
        private AuditService $audit,
    ) {}

    /** Deterministic pseudo-embedding (mock provider): 32 dims from sha256. */
    public function embed(string $text): array
    {
        $hash = hash('sha256', $text);
        $vector = [];
        for ($i = 0; $i < 32; $i++) {
            $vector[] = (hexdec(substr($hash, $i * 2, 2)) / 255) - 0.5;
        }

        return $vector;
    }

    public function indexDocument(Document $document): int
    {
        $version = $document->currentVersion;
        $text = trim((string) $version?->extracted_text);

        if ($text === '') {
            return 0;
        }

        $chunks = preg_split('/(?<=[.!?])\s+|\n{2,}/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        DB::transaction(function () use ($document, $version, $chunks) {
            RagChunk::where('document_id', $document->id)->delete();

            foreach (array_slice($chunks, 0, 200) as $i => $content) {
                $content = trim($content);
                if ($content === '') {
                    continue;
                }

                RagChunk::create([
                    'tenant_id' => $document->tenant_id,
                    'document_id' => $document->id,
                    'version_id' => $version->id,
                    'chunk_index' => $i,
                    'content' => mb_substr($content, 0, 2000),
                    'embedding' => $this->embed(mb_substr($content, 0, 500)),
                ]);
            }
        });

        $count = RagChunk::where('document_id', $document->id)->count();
        $this->audit->log('rag.indexed', 'document', $document->id, ['chunks' => $count]);

        return $count;
    }

    /**
     * Search respecting permissions: 1) candidate chunks of the tenant,
     * 2) filter by document accessibility, 3) rank by similarity.
     */
    public function search(User $user, string $query, int $limit = 5): array
    {
        $queryVector = $this->embed($query);

        $candidates = RagChunk::where('tenant_id', $user->tenant_id)->get();

        $accessibleIds = $this->permissions->accessibleDocumentIds($user);
        $accessibleSet = array_flip($accessibleIds);

        $scored = [];
        foreach ($candidates as $chunk) {
            if (! isset($accessibleSet[$chunk->document_id])) {
                continue; // RM-019: inaccessible segments are dropped BEFORE ranking
            }

            $score = $this->cosine($queryVector, $chunk->embedding ?? []);
            $scored[] = ['chunk' => $chunk, 'score' => $score];
        }

        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

        $selected = array_slice($scored, 0, $limit);
        $this->audit->log('rag.search', 'rag', null, ['query' => mb_substr($query, 0, 200), 'results' => count($selected)]);

        return $selected;
    }

    public function answer(User $user, string $query, int $limit = 5): array
    {
        $segments = $this->search($user, $query, $limit);

        $sources = array_map(fn ($s) => [
            'document_id' => $s['chunk']->document_id,
            'document_title' => $s['chunk']->document?->title,
            'score' => round($s['score'], 4),
        ], $segments);

        $context = implode("\n", array_map(fn ($s) => $s['chunk']->content, $segments));

        return [
            'answer' => 'Réponse générée à partir de '.count($segments).' segment(s) autorisé(s) : '.mb_substr($context, 0, 600),
            'sources' => $sources,
            'mode' => 'mock',
        ];
    }

    private function cosine(array $a, array $b): float
    {
        if ($a === [] || $b === [] || count($a) !== count($b)) {
            return 0.0;
        }

        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;
        foreach ($a as $i => $value) {
            $dot += $value * $b[$i];
            $normA += $value * $value;
            $normB += $b[$i] * $b[$i];
        }

        if ($normA == 0 || $normB == 0) {
            return 0.0;
        }

        return $dot / (sqrt($normA) * sqrt($normB));
    }
}
