<?php

declare(strict_types=1);

namespace App\Services\DataHub\Contracts;

use App\Services\DataHub\DTOs\ImportFileMetadata;
use App\Services\DataHub\DTOs\ImportInspectionResult;

interface ImportParserContract
{
    public function inspect(ImportFileMetadata $file): ImportInspectionResult;

    /** @return iterable<int, array<string, mixed>> */
    public function parse(ImportFileMetadata $file): iterable;
}
