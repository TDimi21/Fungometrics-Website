<?php

declare(strict_types=1);

namespace App\Models\Concerns;

enum SidesFieldPosition: string
{
    case LEFT = 'LF';
    case CENTER = 'CF';
    case RIGHT = 'RF';
    case NONE = 'N';
}
