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
    case LongToss = 'long_toss';
    case WeightedBalls = 'weighted_balls';
    case ExitVelocity = 'exit_velocity';
    case SpeedAgility = 'speed_agility';
    case Recovery = 'recovery';

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
            self::LongToss => 'Long Toss',
            self::WeightedBalls => 'Weighted Balls',
            self::ExitVelocity => 'Exit Velocity',
            self::SpeedAgility => 'Speed & Agility',
            self::Recovery => 'Recovery',
        };
    }
}
