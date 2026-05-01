<?php

declare(strict_types=1);

namespace App\Models\Concerns;

enum TypeSMS: string
{
    case CREATED = 'create_profile';
    case TRAINING = 'send_result';

}
