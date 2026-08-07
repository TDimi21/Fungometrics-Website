<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Intelligence;

use App\Services\Intelligence\BenchmarkDefinitions;
use PHPUnit\Framework\TestCase;

class BenchmarkDefinitionsTest extends TestCase
{
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
