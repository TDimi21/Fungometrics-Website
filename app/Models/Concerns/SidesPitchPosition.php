<?php

declare(strict_types=1);

namespace App\Models\Concerns;

enum SidesPitchPosition: string
{
    case TOP_LEFT = 'TL';
    case TOP_CENTER = 'TC';
    case TOP_RIGHT = 'TR';
    case MIDDLE_LEFT = 'ML';
    case MIDDLE_CENTER = 'MC';
    case MIDDLE_RIGHT = 'MR';
    case BOTTOM_LEFT = 'BL';
    case BOTTOM_MIDDLE = 'BC';
    case BOTTOM_RIGHT = 'BR';

    case ZONE_BALL = 'B';
    case ZONE_STRIKE='S';
}
