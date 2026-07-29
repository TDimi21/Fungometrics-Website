<?php

declare(strict_types=1);

namespace App\Services\DataHub\Support;

final class CertificationVersions
{
    public const FIXTURE_GENERATOR = '1.2.0';
    public const PLATFORM_DICTIONARY = '1.0.0';
    public const BASEBALL_DICTIONARY = '1.0.0';
    public const TRANSLATION_ENGINE = '1.1.0';
    public const TRANSLATION_REVIEW_SCHEMA = '1.1.0';
    public const TRANSLATION_SNAPSHOT_SCHEMA = '1.1.0';
    public const WARNING_RULES = '1.1.0';

    /** @return array<string, string> */
    public static function all(): array
    {
        return [
            'fixture_generator' => self::FIXTURE_GENERATOR,
            'platform_dictionary' => self::PLATFORM_DICTIONARY,
            'baseball_dictionary' => self::BASEBALL_DICTIONARY,
            'translation_engine' => self::TRANSLATION_ENGINE,
            'translation_review_schema' => self::TRANSLATION_REVIEW_SCHEMA,
            'translation_snapshot_schema' => self::TRANSLATION_SNAPSHOT_SCHEMA,
            'warning_rules' => self::WARNING_RULES,
        ];
    }
}
