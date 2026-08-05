<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Intelligence;

use App\Services\Intelligence\StrengthOneRepMaxCalculator;
use PHPUnit\Framework\TestCase;

class StrengthOneRepMaxCalculatorTest extends TestCase
{
    /** @dataProvider supportedRepetitions */
    public function test_epley_is_governed_for_one_through_ten_repetitions(int $repetitions): void
    {
        $result = (new StrengthOneRepMaxCalculator())->estimate(180, $repetitions);

        $this->assertTrue($result['supported']);
        $this->assertSame(round(1 === $repetitions ? 180.0 : 180 * (1 + ($repetitions / 30)), 1), $result['estimated_1rm']);
        $this->assertSame(1 === $repetitions, $result['tested_1rm']);
        $this->assertSame($repetitions > 1 ? 'epley' : null, $result['formula']);
        $this->assertSame($repetitions > 1 ? '1.0.0' : null, $result['formula_version']);
    }

    public function test_more_than_ten_repetitions_is_not_silently_estimated(): void
    {
        $result = (new StrengthOneRepMaxCalculator())->estimate(180, 11);

        $this->assertFalse($result['supported']);
        $this->assertNull($result['estimated_1rm']);
        $this->assertSame('rep_range_unsupported', $result['quality_flag']);
        $this->assertSame(180.0, $result['actual_load']);
    }

    public static function supportedRepetitions(): array
    {
        return array_map(fn (int $repetitions): array => [$repetitions], range(1, 10));
    }
}
