<?php

declare(strict_types=1);

namespace App\Services\DataHub\Support;

use InvalidArgumentException;
use JsonException;

final class CanonicalPayload
{
    public static function normalize(mixed $value): mixed
    {
        if (is_float($value)) {
            if ( ! is_finite($value)) {
                throw new InvalidArgumentException('Canonical payloads do not support non-finite numeric values.');
            }

            return 0.0 === $value ? 0.0 : $value;
        }

        if ( ! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map([self::class, 'normalize'], $value);
        }

        $normalized = [];
        foreach ($value as $key => $item) {
            $normalized[(string) $key] = self::normalize($item);
        }
        ksort($normalized, SORT_STRING);

        return $normalized;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @throws JsonException
     */
    public static function serialize(array $payload): string
    {
        return json_encode(
            self::normalize($payload),
            JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );
    }

    /** @param array<string, mixed> $payload */
    public static function sha256(array $payload): string
    {
        return hash('sha256', self::serialize($payload));
    }
}
