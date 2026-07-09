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
    ) {
    }

    public function build(string $teamId, string $playerId, int $days = 60): array
    {
        $assembled = $this->assembler->assembleForPlayer($teamId, $playerId, $days);
        $signals = $this->signalEngine->buildSignals($assembled);
        $recommendations = $this->recommendationEngine->buildRecommendations($assembled, $signals);

        return $this->formatter->formatPlayerSnapshot($teamId, $playerId, $assembled, $signals, $recommendations);
    }
}
