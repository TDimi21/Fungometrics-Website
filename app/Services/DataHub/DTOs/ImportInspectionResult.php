<?php

declare(strict_types=1);

namespace App\Services\DataHub\DTOs;

final readonly class ImportInspectionResult
{
    /** @param array<string, mixed> $data */
    public function __construct(
        public array $data = [],
    ) {
    }
}
