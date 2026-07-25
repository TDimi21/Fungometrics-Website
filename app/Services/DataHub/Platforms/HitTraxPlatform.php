<?php

declare(strict_types=1);

namespace App\Services\DataHub\Platforms;

use App\Services\DataHub\Enums\ImportPlatform;
use App\Services\DataHub\Enums\ImportSessionType;

final class HitTraxPlatform extends AbstractImportPlatform
{
    public function key(): ImportPlatform
    {
        return ImportPlatform::HitTrax;
    }
    public function name(): string
    {
        return 'HitTrax';
    }
    public function supportedSessionTypes(): array
    {
        return [ImportSessionType::Cage, ImportSessionType::BattingPractice];
    }
}
