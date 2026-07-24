<?php

declare(strict_types=1);

namespace App\Services\DataHub\Platforms;

use App\Services\DataHub\Enums\ImportPlatform;
use App\Services\DataHub\Enums\ImportSessionType;

final class TrackManPlatform extends AbstractImportPlatform
{
    public function key(): ImportPlatform
    {
        return ImportPlatform::TrackMan;
    }
    public function name(): string
    {
        return 'TrackMan';
    }
    public function supportedSessionTypes(): array
    {
        return [ImportSessionType::Cage, ImportSessionType::LiveAb, ImportSessionType::BattingPractice, ImportSessionType::PitchingPractice];
    }
}
