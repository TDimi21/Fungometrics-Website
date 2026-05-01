<?php

declare(strict_types=1);

namespace App\Models\Concerns;

enum UserTypes: string
{
    case PLAYER = 'player';
    case COACH = 'coach';
}
