<?php

namespace App\Services\Contracts;

use App\Models\DocumentVersion;

interface AiProvider
{
    public function name(): string;

    /** @return array{content: mixed, confidence: float} */
    public function run(string $jobType, DocumentVersion $version, array $params = []): array;
}
