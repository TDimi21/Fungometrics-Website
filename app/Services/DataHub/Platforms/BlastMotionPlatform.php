<?php

declare(strict_types=1);

namespace App\Services\DataHub\Platforms;

use App\Services\DataHub\Enums\ImportPlatform;
use App\Services\DataHub\Enums\ImportSessionType;

final class BlastMotionPlatform extends AbstractImportPlatform
{
    public function key(): ImportPlatform
    {
        return ImportPlatform::BlastMotion;
    }
    public function name(): string
    {
        return 'Blast Motion';
    }
    public function supportedSessionTypes(): array
    {
        return [ImportSessionType::Cage, ImportSessionType::BattingPractice, ImportSessionType::Assessment];
    }
}
