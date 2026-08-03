<?php
declare(strict_types=1);
namespace Tests\Unit\Blast;
use App\Services\Blast\BlastBenchmarkComparator;
use App\Services\Blast\BlastSessionDevelopmentReportService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
final class BlastBestSwingSelectionTest extends TestCase
{
    public function test_best_swing_is_one_real_row_not_per_metric_maxima(): void
    {
        $events = [
            ['id'=>'fastest','order'=>1,'metrics'=>['hitting.blast_plane_score'=>40.0,'hitting.blast_connection_score'=>40.0,'hitting.blast_rotation_score'=>40.0,'hitting.bat_speed'=>99.0,'hitting.time_to_contact'=>0.10]],
            ['id'=>'best-scores','order'=>2,'metrics'=>['hitting.blast_plane_score'=>48.0,'hitting.blast_connection_score'=>51.0,'hitting.blast_rotation_score'=>56.0,'hitting.bat_speed'=>79.2,'hitting.time_to_contact'=>0.15]],
            ['id'=>'tie-later','order'=>3,'metrics'=>['hitting.blast_plane_score'=>48.0,'hitting.blast_connection_score'=>51.0,'hitting.blast_rotation_score'=>56.0,'hitting.bat_speed'=>79.1,'hitting.time_to_contact'=>0.14]],
        ];
        $method = new ReflectionMethod(BlastSessionDevelopmentReportService::class, 'bestSwing');
        $method->setAccessible(true);
        $best = $method->invoke(new BlastSessionDevelopmentReportService(new BlastBenchmarkComparator()), $events);
        $this->assertSame('best-scores', $best['id']);
        $this->assertSame(79.2, $best['metrics']['hitting.bat_speed']);
        $this->assertNotSame(99.0, $best['metrics']['hitting.bat_speed']);
    }
}
