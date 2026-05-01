<?php

declare(strict_types=1);

namespace App\Models\Concerns;

enum BattingTrajectory: string
{
    case FLY_BALL = 'FB';
    case POP_FLY = 'PF';
    case GROUND_BALL = 'GB';
    case LINE_DRIVE = 'LD';
    case SWING_MISS = 'SM';
    case TAKE = 'TK';
    case FOUL = 'F';
    case HIT_BY_PITCH = 'HBP';
}
