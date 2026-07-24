<?php

declare(strict_types=1);

namespace App\Services\DataHub\Contracts;

use App\Services\DataHub\Enums\ImportPlatform;
use App\Services\DataHub\Enums\ImportSessionType;

interface ImportPlatformContract
{
    public function key(): ImportPlatform;

    public function name(): string;

    /** @return array<int, string> */
    public function supportedFileTypes(): array;

    /** @return array<int, ImportSessionType> */
    public function supportedSessionTypes(): array;
}
