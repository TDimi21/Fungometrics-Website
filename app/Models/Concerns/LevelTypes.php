<?php

declare(strict_types=1);

namespace App\Models\Concerns;

enum LevelTypes: string
{
    case MID_SCHOOL = 'MID';
    case HIGH_SCHOOL = 'HIGH';
    case JUCO = 'JUCO';
    case D1 = 'D1';
    case D2 = 'D2';
    case D3 = 'D3';
    case TRAVEL = 'TRAVEL';
    case NAIA = 'NAIA';
    case PLAYER = 'PLAYER';
}
