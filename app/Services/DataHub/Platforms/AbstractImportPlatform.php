<?php

declare(strict_types=1);

namespace App\Services\DataHub\Platforms;

use App\Services\DataHub\Contracts\ImportPlatformContract;

abstract class AbstractImportPlatform implements ImportPlatformContract
{
    /** @return array<int, string> */
    public function supportedFileTypes(): array
    {
        return ['csv', 'xlsx'];
    }
}
