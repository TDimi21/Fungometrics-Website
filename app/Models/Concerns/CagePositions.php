<?php

declare(strict_types=1);

namespace App\Models\Concerns;

enum CagePositions: string
{
    case TOP = 'T';
    case FRONT = 'F';
    case LEFT = 'L';
    case RIGHT = 'R';
    case BOTTOM = 'B';
}
