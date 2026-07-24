<?php

declare(strict_types=1);

namespace App\Services\DataHub\Enums;

enum ImportSessionType: string
{
    case Cage = 'cage';
    case LiveAb = 'live_ab';
    case Bullpen = 'bullpen';
    case Strength = 'strength';
    case Mobility = 'mobility';
    case Assessment = 'assessment';
    case BattingPractice = 'batting_practice';
    case PitchingPractice = 'pitching_practice';

    public function label(): string
    {
        return match ($this) {
            self::Cage => 'Cage',
            self::LiveAb => 'Live AB',
            self::Bullpen => 'Bullpen',
            self::Strength => 'Strength',
            self::Mobility => 'Mobility',
            self::Assessment => 'Assessment',
            self::BattingPractice => 'Batting Practice',
            self::PitchingPractice => 'Pitching Practice',
        };
    }
}
