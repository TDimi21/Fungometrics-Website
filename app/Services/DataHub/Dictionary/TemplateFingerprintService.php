<?php

declare(strict_types=1);

namespace App\Services\DataHub\Dictionary;

final class TemplateFingerprintService
{
    public static function normalize(string $value): string
    {
        return mb_strtolower((string)preg_replace('/[^a-z0-9]/i', '', $value));
    } public function fingerprint(array $headers): string
    {
        return hash('sha256', implode('|', array_map([self::class,'normalize'], $headers)));
    }
}
