<?php

declare(strict_types=1);

namespace App\Services\DataHub\Services;

use App\Services\DataHub\Contracts\ImportPlatformContract;
use App\Services\DataHub\Enums\ImportPlatform;
use App\Services\DataHub\Platforms\BlastMotionPlatform;
use App\Services\DataHub\Platforms\GenericCsvPlatform;
use App\Services\DataHub\Platforms\HitTraxPlatform;
use App\Services\DataHub\Platforms\RapsodoPlatform;
use App\Services\DataHub\Platforms\TrackManPlatform;
use InvalidArgumentException;

final class PlatformRegistry
{
    /** @var array<string, ImportPlatformContract> */
    private array $platforms;

    /** @param array<int, ImportPlatformContract>|null $platforms */
    public function __construct(?array $platforms = null)
    {
        $platforms ??= [
            new TrackManPlatform(),
            new HitTraxPlatform(),
            new RapsodoPlatform(),
            new BlastMotionPlatform(),
            new GenericCsvPlatform(),
        ];

        $this->platforms = [];
        foreach ($platforms as $platform) {
            $this->platforms[$platform->key()->value] = $platform;
        }
    }

    /** @return array<int, ImportPlatformContract> */
    public function all(): array
    {
        return array_values($this->platforms);
    }

    public function get(ImportPlatform|string $platform): ImportPlatformContract
    {
        $key = $platform instanceof ImportPlatform ? $platform->value : $platform;
        return $this->platforms[$key] ?? throw new InvalidArgumentException("Unknown Data Hub platform: {$key}");
    }
}
