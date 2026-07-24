<?php

declare(strict_types=1);

namespace App\Services\DataHub\DTOs;

final readonly class ImportInspectionResult
{
    public function __construct(
        public ?int $rowCount = null,
        public ?int $playerCount = null,
        public string $status = 'not_analyzed',
    ) {
    }
}
