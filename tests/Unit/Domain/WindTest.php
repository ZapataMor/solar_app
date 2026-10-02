<?php

namespace Tests\Unit\Domain;

use App\Domain\Climate\Wind;
use PHPUnit\Framework\TestCase;

/**
 * ADR-0018: the wind of the latest reading, as the vane of the 3D station shows it.
 */
class WindTest extends TestCase
{
    public function test_the_direction_is_the_nearest_point_of_the_compass_it_comes_from(): void
    {
        $this->assertSame('norte', (new Wind(10, 0))->from());
        $this->assertSame('norte', (new Wind(10, 350))->from());
        $this->assertSame('noreste', (new Wind(10, 45))->from());
        $this->assertSame('este', (new Wind(10, 100))->from());
        $this->assertSame('sur', (new Wind(10, 200))->from());
        $this->assertSame('noroeste', (new Wind(10, 315))->from());
        $this->assertSame('norte', (new Wind(10, 360))->from());
        $this->assertNull((new Wind(10, null))->from());
    }

    public function test_it_reads_as_speed_and_origin_or_as_calm(): void
    {
        $this->assertSame('13 km/h del noreste', (new Wind(13.4, 45))->describe());
        $this->assertSame('4,8 km/h del este', (new Wind(4.8, 80))->describe());
        $this->assertSame('22 km/h', (new Wind(22, null))->describe());
        $this->assertSame('en calma', (new Wind(0.4, 167))->describe());
        $this->assertTrue((new Wind(0, 167))->isCalm());
    }
}
