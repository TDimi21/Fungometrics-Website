<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Intelligence;

use App\Services\Intelligence\BenchmarkDefinitions;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class BenchmarkDefinitionsTest extends TestCase
{
    public function test_it_changes_age_groups_on_the_exact_governed_day_boundaries(): void
    {
        $born = CarbonImmutable::parse('2000-02-29');
        $boundaries = [
            [12, BenchmarkDefinitions::AGE_10U_12U, BenchmarkDefinitions::AGE_13U_14U],
            [14, BenchmarkDefinitions::AGE_13U_14U, BenchmarkDefinitions::AGE_15U_16U],
            [16, BenchmarkDefinitions::AGE_15U_16U, BenchmarkDefinitions::AGE_17U_18U],
            [18, BenchmarkDefinitions::AGE_17U_18U, BenchmarkDefinitions::AGE_COLLEGE_19_PLUS],
        ];

        $this->assertSame(
            BenchmarkDefinitions::AGE_10U_12U,
            BenchmarkDefinitions::ageGroupFromDate($born, $born),
        );

        foreach ($boundaries as [$years, $before, $after]) {
            $boundary = $born->addYearsNoOverflow($years)->addDays(356);

            $this->assertSame($before, BenchmarkDefinitions::ageGroupFromDate($born, $boundary->subDay()));
            $this->assertSame($after, BenchmarkDefinitions::ageGroupFromDate($born, $boundary));
        }
    }

    public function test_it_rejects_missing_invalid_and_future_birth_dates(): void
    {
        $this->assertSame(BenchmarkDefinitions::AGE_UNKNOWN, BenchmarkDefinitions::ageGroupFromDate(null));
        $this->assertSame(BenchmarkDefinitions::AGE_UNKNOWN, BenchmarkDefinitions::ageGroupFromDate('not-a-date'));
        $this->assertSame(BenchmarkDefinitions::AGE_UNKNOWN, BenchmarkDefinitions::ageGroupFromDate('2030-01-01', '2029-01-01'));
    }

    /** @dataProvider unambiguousLevelProvider */
    public function test_it_resolves_unambiguous_player_levels_to_age_groups(string $level, string $expected): void
    {
        $this->assertSame($expected, BenchmarkDefinitions::ageGroupFromLevel($level));
    }

    public static function unambiguousLevelProvider(): array
    {
        return [
            ['MID', BenchmarkDefinitions::AGE_13U_14U],
            ['HIGH', BenchmarkDefinitions::AGE_17U_18U],
            ['D1', BenchmarkDefinitions::AGE_COLLEGE_19_PLUS],
            ['JUCO', BenchmarkDefinitions::AGE_COLLEGE_19_PLUS],
            ['NAIA', BenchmarkDefinitions::AGE_COLLEGE_19_PLUS],
        ];
    }

    /** @dataProvider ambiguousLevelProvider */
    public function test_it_does_not_guess_an_age_group_for_ambiguous_levels(string $level): void
    {
        $this->assertSame(BenchmarkDefinitions::AGE_UNKNOWN, BenchmarkDefinitions::ageGroupFromLevel($level));
    }

    public static function ambiguousLevelProvider(): array
    {
        return [
            ['TRAVEL'],
            ['CLUB'],
            ['PLAYER'],
            [''],
        ];
    }
}
