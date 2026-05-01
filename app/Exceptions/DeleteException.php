<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Support\Facades\Log;

class DeleteException extends Exception
{
    public function __construct()
    {
        Log::error('NOT DELETE DATA: '.$this->getMessage());
        parent::__construct('NOT DATA DELETED');
    }
}
