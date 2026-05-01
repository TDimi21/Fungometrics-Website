<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Support\Facades\Log;

class NotFound extends Exception
{
    public function __construct()
    {
        Log::error('Not Data Found: '.$this->getMessage());
        parent::__construct('Not Data Found');
    }
}
