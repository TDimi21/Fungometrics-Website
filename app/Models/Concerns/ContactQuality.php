<?php

declare(strict_types=1);

namespace App\Models\Concerns;

enum ContactQuality: string
{
    case WEAK = 'W';
    case AVERAGE = 'A';
    case HARD = 'H';
    case MISS_FOUL = 'MF';
    case NONE = 'N';
}
