<?php

declare(strict_types=1);

namespace App\Services\Intelligence;

class TeamIntelligenceService
{
    public function __construct(
        private readonly IntelligenceDataAssembler $assembler,
        private readonly PlayerIntelligenceService $playerIntelligence,
        private readonly IntelligenceSignalEngine $signalEngine,
        private readonly IntelligenceRecommendationEngine $recommendationEngine,
        private readonly IntelligenceSnapshotFormatter $formatter,
        private readonly TeamBenchmarkProfileService $teamBenchmarkProfileService,
    ) {
    }

    public function build(string $teamId, int $days = 60): array
    {
        $assembled = $this->assembler->assembleForTeam($teamId, $days);
        $playerSnapshots = [];

        foreach ($assembled['players_assembled'] ?? [] as $playerAssembled) {
            $playerId = $playerAssembled['player_context']['id'] ?? null;
            if (! $playerId) {
                continue;
            }

            $playerSnapshots[] = $this->playerIntelligence->build($teamId, (string) $playerId, $days);
        }

        $signals = $this->signalEngine->buildTeamSignals($assembled, $playerSnapshots);
        $recommendations = $this->recommendationEngine->buildTeamRecommendations($assembled, $signals, $playerSnapshots);
        $benchmarkProfile = $this->teamBenchmarkProfileService->build($teamId, $days);

        return $this->formatter->formatTeamSnapshot($teamId, $assembled, $playerSnapshots, $signals, $recommendations, $benchmarkProfile);
    }
}
