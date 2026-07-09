<?php

declare(strict_types=1);

namespace App\Services\Intelligence;

class PlayerIntelligenceService
{
    public function __construct(
        private readonly IntelligenceDataAssembler $assembler,
        private readonly IntelligenceSignalEngine $signalEngine,
        private readonly IntelligenceRecommendationEngine $recommendationEngine,
        private readonly IntelligenceSnapshotFormatter $formatter,
        private readonly TrendEngine $trendEngine,
        private readonly ProjectionEngine $projectionEngine,
        private readonly LimiterEngine $limiterEngine,
        private readonly PlayerDNAEngine $dnaEngine,
        private readonly AgeBenchmarkEngine $ageBenchmarkEngine,
    ) {
    }

    public function build(string $teamId, string $playerId, int $days = 60): array
    {
        $assembled = $this->assembler->assembleForPlayer($teamId, $playerId, $days);
        $trendBlocks = $this->trendEngine->analyze($assembled['trend_blocks'] ?? [], $assembled);
        $ageBenchmarks = $this->ageBenchmarkEngine->benchmarkPlayer($assembled);
        $projections = $this->projectionEngine->project($trendBlocks, $assembled, $ageBenchmarks);
        $limiters = $this->limiterEngine->detect($assembled, $trendBlocks, $ageBenchmarks);
        $dna = $this->dnaEngine->build($assembled, $trendBlocks, $limiters, $ageBenchmarks);
        $signals = $this->signalEngine->buildSignals($assembled);
        $recommendations = $this->recommendationEngine->buildRecommendations($assembled, $signals, $trendBlocks, $limiters, $dna);

        return $this->formatter->formatPlayerSnapshot(
            $teamId,
            $playerId,
            $assembled,
            $signals,
            $recommendations,
            $trendBlocks,
            $dna,
            $projections,
            $limiters,
            $ageBenchmarks,
        );
    }
}
