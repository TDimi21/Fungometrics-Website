<?php

declare(strict_types=1);

namespace App\Services\BallFlight;

/**
 * One normalization boundary for external and future FMTRX flight datasets.
 * This class reads supplied files only; it does not mutate application data.
 */
final class ResearchDatabase
{
    public function __construct(
        private readonly TrackManImporter $trackMan,
        private readonly StatcastImporter $statcast,
    ) {
    }

    /** @return list<array<string,mixed>> */
    public function import(string $source, string $path): array
    {
        return match ($this->detect($path, $source)) {
            'trackman' => $this->trackMan->import($path),
            'statcast' => $this->statcast->import($path),
            default => throw new \InvalidArgumentException("Unsupported research source '{$source}'."),
        };
    }

    /** @return array<string,mixed> */
    public function inspect(string $path, string $source = 'auto', array $context = []): array
    {
        return match ($this->detect($path, $source)) {
            'trackman' => $this->trackMan->inspect($path, $context),
            'statcast' => $this->statcast->inspect($path, $context),
        };
    }

    public function detect(string $path, string $requested = 'auto'): string
    {
        $requested = mb_strtolower($requested);
        if (in_array($requested, ['trackman', 'statcast'], true)) return $requested;
        if ($requested !== 'auto') throw new \InvalidArgumentException("Unsupported research source '{$requested}'.");
        if (!is_readable($path)) throw new \InvalidArgumentException("CSV is not readable: {$path}");
        $handle = fopen($path, 'rb');
        if ($handle === false) throw new \RuntimeException("Unable to open CSV: {$path}");
        try {
            $headers = fgetcsv($handle) ?: [];
        } finally {
            fclose($handle);
        }
        if ($this->trackMan->detects($headers)) return 'trackman';
        if ($this->statcast->detects($headers)) return 'statcast';
        throw new \InvalidArgumentException('Unable to detect research CSV source from headers.');
    }
}
