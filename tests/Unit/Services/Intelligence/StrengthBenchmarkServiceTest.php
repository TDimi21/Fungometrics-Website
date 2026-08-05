<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Intelligence;

use App\Services\Intelligence\CompositeBenchmarkEngine;
use App\Services\Intelligence\StrengthBenchmarkRegistry;
use App\Services\Intelligence\StrengthBenchmarkService;
use App\Services\Intelligence\StrengthOneRepMaxCalculator;
use Mockery;
use PHPUnit\Framework\TestCase;

class StrengthBenchmarkServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_result_preserves_semantics_relative_strength_grip_and_score_eligibility(): void
    {
        $service = $this->serviceWithPercentile(72);
        $result = $service->benchmark([
            'fitness_date' => '2026-08-05',
            'body_weight' => 180,
            'back_squat' => 225,
            'bench_press' => 185,
            'dead_lift' => 275,
            'trap_bar_deadlift' => 315,
            'vertical_jump' => 24,
            'grip_strength_left' => 82,
            'grip_strength_right' => 90,
            'strength_test_metadata' => [
                'metrics' => ['bench_press' => ['repetitions' => 5, 'method' => 'rep_max']],
                'protocols' => ['grip' => 'three-trial best', 'grip_device' => 'dynamometer'],
            ],
        ], null, ['age' => 16, 'age_group' => '15U_16U', 'level' => 'high_school']);

        $metrics = $result['metric_map'];
        $this->assertSame('back_squat', $metrics['back_squat']['metric_key']);
        $this->assertSame('trap_bar_deadlift', $metrics['trap_bar_deadlift']['metric_key']);
        $this->assertSame(215.8, $metrics['bench_press']['test']['estimated_1rm']);
        $this->assertSame(1.199, $metrics['bench_press']['test']['relative_strength']);
        $this->assertSame(8.9, $metrics['grip_asymmetry_percentage']['test']['actual_value']);
        $this->assertSame('Descriptive', $metrics['body_weight']['benchmark']['classification']);
        $this->assertSame('available', $result['status']);
        $this->assertNotNull($result['score']);
        $this->assertSame([], $result['missing_requirements']);
    }

    public function test_missing_body_weight_or_coverage_returns_null_not_zero(): void
    {
        $result = $this->serviceWithPercentile(72)->benchmark([
            'bench_press' => 185,
            'grip_strength_left' => 80,
        ], null, ['age' => 16, 'age_group' => '15U_16U']);

        $this->assertNull($result['score']);
        $this->assertSame('needs_data', $result['status']);
        $this->assertContains('valid_body_weight_at_test', $result['missing_requirements']);
        $this->assertNull($result['metric_map']['bench_press']['test']['relative_strength']);
        $this->assertNull($result['metric_map']['grip_asymmetry_percentage']['test']['actual_value']);
    }

    public function test_missing_age_never_claims_an_age_group(): void
    {
        $result = $this->serviceWithPercentile(null)->benchmark([
            'body_weight' => 180,
            'bench_press' => 185,
        ]);

        $bench = $result['metric_map']['bench_press']['benchmark'];
        $this->assertSame('UNKNOWN', $bench['age_group']);
        $this->assertNull($bench['percentile']);
        $this->assertContains('missing_age', $result['metric_map']['bench_press']['data_quality']);
    }

    private function serviceWithPercentile(?int $percentile): StrengthBenchmarkService
    {
        $composite = Mockery::mock(CompositeBenchmarkEngine::class);
        $composite->shouldReceive('benchmarkMetric')->zeroOrMoreTimes()->andReturnUsing(
            function (string $key, mixed $value, ?string $dob, array $context) use ($percentile): array {
                if (null === $percentile) {
                    return [
                        'score_0_100' => null,
                        'confidence' => 'low',
                        'source' => 'research_benchmark',
                        'evidence' => ['reason' => 'Date of birth is missing.'],
                        'population_percentile' => ['bucket_count' => 0],
                    ];
                }

                return [
                    'score_0_100' => $percentile,
                    'confidence' => 'medium',
                    'source' => 'research_benchmark',
                    'evidence' => ['age_percentile_anchors' => ['p25' => 100, 'p50' => 150, 'p75' => 225, 'p90' => 250]],
                    'population_percentile' => ['bucket_count' => 0],
                ];
            }
        );

        return new StrengthBenchmarkService(
            new StrengthBenchmarkRegistry(),
            new StrengthOneRepMaxCalculator(),
            $composite,
        );
    }
}
