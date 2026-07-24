<?php

declare(strict_types=1);

namespace App\Services\DataHub\Platforms;

use App\Services\DataHub\Enums\ImportPlatform;
use App\Services\DataHub\Enums\ImportSessionType;

final class GenericCsvPlatform extends AbstractImportPlatform
{
    public function key(): ImportPlatform
    {
        return ImportPlatform::GenericCsv;
    }
    public function name(): string
    {
        return 'Generic CSV';
    }
    public function supportedSessionTypes(): array
    {
        return ImportSessionType::cases();
    }
}
