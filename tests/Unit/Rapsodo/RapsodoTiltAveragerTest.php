<?php

declare(strict_types=1);

namespace Tests\Unit\Rapsodo;

use App\Services\Rapsodo\RapsodoTiltAverager;
use PHPUnit\Framework\TestCase;

final class RapsodoTiltAveragerTest extends TestCase
{
    public function test_it_uses_a_circular_average_across_twelve_oclock(): void
    {
        $averager = new RapsodoTiltAverager();

        $this->assertSame('12:00', $averager->average(['11h:50m', '12h:10m']));
        $this->assertSame('1:00', $averager->average(['12h:50m', '01h:10m']));
        $this->assertNull($averager->average(['', 'not-a-clock']));
    }
}
