<?php

declare(strict_types=1);

namespace App\Services\Access;

use App\Models\PlayerAssessment;
use App\Models\User;
use Illuminate\Support\Collection;

class AssessmentResponsePolicy
{
    public function __construct(private readonly EntitlementResolver $resolver)
    {
    }

    /** @param Collection<int, PlayerAssessment> $assessments @return array<int, array<string, mixed>> */
    public function shape(Collection $assessments, User $user, ?string $teamId = null): array
    {
        $access = $this->resolver->getAccessSummary($user, $teamId);
        $comparisons = in_array('view_assessment_comparisons', $access['entitlements'], true);
        $recommendations = in_array('view_assessment_recommendations', $access['entitlements'], true);

        return $assessments->map(function (PlayerAssessment $assessment) use ($comparisons, $recommendations): array {
            $data = $assessment->toArray();
            if ( ! $comparisons) {
                foreach ([
                    'team_percentiles', 'age_group_percentiles', 'overall_team_percentile',
                    'overall_age_percentile', 'age_group_years',
                ] as $key) {
                    unset($data[$key]);
                }
            }
            if ( ! $recommendations) {
                unset($data['coach_insights']);
            }

            return $data;
        })->values()->all();
    }
}
