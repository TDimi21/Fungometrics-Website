<?php

declare(strict_types=1);

namespace App\Services\DataHub\Platforms;

use App\Services\DataHub\Enums\ImportPlatform;
use App\Services\DataHub\Enums\ImportSessionType;

final class RapsodoPlatform extends AbstractImportPlatform
{
    public function key(): ImportPlatform
    {
        return ImportPlatform::Rapsodo;
    }
    public function name(): string
    {
        return 'Rapsodo';
    }
    public function supportedFileTypes(): array
    {
        return ['xlsx'];
    }
    public function supportedSessionTypes(): array
    {
        return [ImportSessionType::Bullpen, ImportSessionType::PitchingPractice, ImportSessionType::Assessment];
    }
}
