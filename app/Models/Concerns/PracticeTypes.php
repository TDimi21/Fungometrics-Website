<?php

declare(strict_types=1);

namespace App\Models\Concerns;

enum PracticeTypes: string
{
    case CAGE = 'C';
    case BULLPEN = 'P';
    case BATTING = 'B';
    case LIVE_AB = 'L';
    case TRAINING= 'T';
}
