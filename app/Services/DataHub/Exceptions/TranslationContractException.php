<?php

declare(strict_types=1);

namespace App\Services\DataHub\Exceptions;

use DomainException;

final class TranslationContractException extends DomainException
{
    /** @param array<string, mixed> $context */
    public function __construct(
        private readonly string $errorCode,
        string $message,
        private readonly array $context = [],
    ) {
        parent::__construct($message);
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    /** @return array<string, mixed> */
    public function context(): array
    {
        return $this->context;
    }
}
