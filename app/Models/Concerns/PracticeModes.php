<?php

declare(strict_types=1);

namespace App\Models\Concerns;

enum PracticeModes: string
{
    case HIT_OR_PITCH = 'HP';
    case WEIGHT_BALL = 'WB';
    case LONG_TOSS = 'LT';
    case EXIT_VELOCITY = 'EV';
}
