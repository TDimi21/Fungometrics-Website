<?php

declare(strict_types=1);

namespace App\Models\Concerns;

enum SidesPLayer: string
{
    case LEFT = 'L';
    case RIGHT = 'R';
    case SWITCH = 'S';
}
