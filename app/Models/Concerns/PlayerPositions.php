<?php

declare(strict_types=1);

namespace App\Models\Concerns;

enum PlayerPositions: string
{
    case PITCHER = 'P';
    case CATCHER = 'C';
    case FIRST_BASE = '1B';
    case SECOND_BASE = '2B';
    case THIRD_BASE = '3B';
    case SHORT_STOP = 'SS';
    case RIGHT_FIELDER = 'RF';
    case CENTER_FIELDER = 'CF';
    case LEFT_FIELDER = 'LF';
}
