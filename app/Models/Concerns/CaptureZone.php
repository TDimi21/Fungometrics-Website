<?php

declare(strict_types=1);

namespace App\Models\Concerns;

enum CaptureZone: string
{
    case BALL = 'B';
    case STRIKE = 'S';
}
