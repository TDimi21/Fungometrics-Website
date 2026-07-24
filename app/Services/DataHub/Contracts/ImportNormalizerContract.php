<?php

declare(strict_types=1);

namespace App\Services\DataHub\Contracts;

use App\Services\DataHub\DTOs\NormalizedImportResult;

interface ImportNormalizerContract
{
    /**
     * @param iterable<int, array<string, mixed>> $records
     * @param array<string, string|null> $playerMappings
     */
    public function normalize(iterable $records, array $playerMappings = [], string $sessionType = 'cage'): NormalizedImportResult;
}
