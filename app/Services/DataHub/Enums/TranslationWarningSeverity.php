<?php

declare(strict_types=1);

namespace App\Services\DataHub\Enums;

enum TranslationWarningSeverity: string
{
    case Informational = 'informational';
    case Warning = 'warning';
    case HighSeverity = 'high_severity';
    case Blocking = 'blocking';

    public function acknowledgmentRequired(): bool
    {
        return self::HighSeverity === $this;
    }

    public function approvalBlocked(): bool
    {
        return self::Blocking === $this;
    }

    public function sortOrder(): int
    {
        return match ($this) {
            self::Blocking => 0,
            self::HighSeverity => 1,
            self::Warning => 2,
            self::Informational => 3,
        };
    }
}
