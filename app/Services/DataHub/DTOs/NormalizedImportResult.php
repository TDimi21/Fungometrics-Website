<?php

declare(strict_types=1);

namespace App\Services\DataHub\DTOs;

final readonly class NormalizedImportResult
{
    /** @param array<int, array<string, mixed>> $records */
    public function __construct(public array $records)
    {
    }
}
