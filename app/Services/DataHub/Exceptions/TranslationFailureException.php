<?php

declare(strict_types=1);

namespace App\Services\DataHub\Exceptions;

use RuntimeException;

final class TranslationFailureException extends RuntimeException
{
    /** @param array<string, mixed> $warning */
    public function __construct(private readonly array $warning)
    {
        parent::__construct((string) ($warning['message'] ?? 'Translation inspection failed.'));
    }

    public function errorCode(): string
    {
        return (string) ($this->warning['code'] ?? 'translation_failure');
    }

    /** @return array<string, mixed> */
    public function warning(): array
    {
        return $this->warning;
    }
}
