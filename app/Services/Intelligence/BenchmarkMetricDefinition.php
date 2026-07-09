<?php

declare(strict_types=1);

namespace App\Services\Intelligence;

class BenchmarkMetricDefinition
{
    public function __construct(
        public readonly string $metricKey,
        public readonly string $displayName,
        public readonly string $category,
        public readonly ?string $unit,
        public readonly bool $higherIsBetter,
        public readonly float $importanceWeight,
        public readonly string $researchConfidence,
        public readonly string $populationConfidence,
        public readonly int $minimumPopulationSample,
        public readonly string $lastReviewed,
        public readonly string $sourceType,
        public readonly array $evidenceNotes,
        public readonly array $agePercentileAnchors,
    ) {
    }

    public function toArray(): array
    {
        return [
            'metric_key' => $this->metricKey,
            'display_name' => $this->displayName,
            'category' => $this->category,
            'unit' => $this->unit,
            'higher_is_better' => $this->higherIsBetter,
            'importance_weight' => $this->importanceWeight,
            'research_confidence' => $this->researchConfidence,
            'population_confidence' => $this->populationConfidence,
            'minimum_population_sample' => $this->minimumPopulationSample,
            'last_reviewed' => $this->lastReviewed,
            'source_type' => $this->sourceType,
            'evidence_notes' => $this->evidenceNotes,
            'age_percentile_anchors' => $this->agePercentileAnchors,
        ];
    }
}
