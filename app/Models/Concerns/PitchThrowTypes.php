<?php

declare(strict_types=1);

namespace App\Models\Concerns;

enum PitchThrowTypes: string
{
    case FAST_BALL = 'FB';
    case CHANGE_UP = 'CH';
    case SLIDER = 'SL';
    case CURVE_BALL = 'CB';
    case OTHER = 'OTHER';
}
