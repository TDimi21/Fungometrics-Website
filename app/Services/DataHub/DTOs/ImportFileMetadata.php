<?php

declare(strict_types=1);

namespace App\Services\DataHub\DTOs;

final readonly class ImportFileMetadata
{
    public function __construct(
        public string $name,
        public int $sizeBytes,
        public string $extension,
        public ?string $mimeType = null,
    ) {
    }
}
