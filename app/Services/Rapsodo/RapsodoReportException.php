<?php

declare(strict_types=1);

namespace App\Services\Rapsodo;

use RuntimeException;

final class RapsodoReportException extends RuntimeException
{
    public function __construct(
        public readonly string $reportCode,
        string $message,
        public readonly int $httpStatus = 422,
    ) {
        parent::__construct($message);
    }
}
