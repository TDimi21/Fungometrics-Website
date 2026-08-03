<?php
declare(strict_types=1);
namespace Tests\Unit\Blast;
use App\Services\Blast\BlastBenchmarkComparator;
use PHPUnit\Framework\TestCase;
final class BlastBenchmarkComparatorTest extends TestCase
{
    public function test_comparison_modes_preserve_their_distinct_meaning(): void
    {
        $service = new BlastBenchmarkComparator();
        $this->assertSame('above_benchmark', $service->compare(23.3, ['min'=>19,'max'=>22,'unit'=>'mph','mode'=>'higher_is_better'])['status']);
        $this->assertSame('faster_than_range', $service->compare(0.144, ['min'=>0.15,'max'=>0.18,'unit'=>'sec','mode'=>'lower_is_better'])['status']);
        $this->assertSame('above_range', $service->compare(14, ['min'=>2,'max'=>13,'unit'=>'deg','mode'=>'target_range'])['status']);
        $this->assertSame('in_range', $service->compare(10.9, ['min'=>2,'max'=>13,'unit'=>'deg','mode'=>'target_range'])['status']);
    }
}
