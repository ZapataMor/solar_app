<?php

namespace Tests\Unit\Domain;

use App\Domain\Installers\InstallerCoverage;
use PHPUnit\Framework\TestCase;

/**
 * ADR-0021: how the directory says the municipalities an installer covers.
 */
class InstallerCoverageTest extends TestCase
{
    public function test_up_to_three_municipalities_are_listed_and_the_rest_are_counted(): void
    {
        $this->assertSame('Sin municipios asignados', InstallerCoverage::text([]));
        $this->assertSame('Riohacha', InstallerCoverage::text(['Riohacha']));
        $this->assertSame('Riohacha y Maicao', InstallerCoverage::text(['Riohacha', 'Maicao']));
        $this->assertSame('Riohacha, Maicao y Uribia', InstallerCoverage::text(['Riohacha', 'Maicao', 'Uribia']));
        $this->assertSame(
            'Riohacha, Maicao y 2 municipios más',
            InstallerCoverage::text(['Riohacha', 'Maicao', 'Uribia', 'Manaure']),
        );
    }

    public function test_covering_every_active_municipality_is_the_whole_department(): void
    {
        $names = ['Riohacha', 'Maicao', 'Uribia', 'Manaure'];

        $this->assertSame('Toda La Guajira', InstallerCoverage::text($names, 4));
        // One short of the department is still a list.
        $this->assertSame('Riohacha, Maicao y 2 municipios más', InstallerCoverage::text($names, 5));
        // Without knowing how many there are, it never claims the whole department.
        $this->assertSame('Riohacha, Maicao y 2 municipios más', InstallerCoverage::text($names));
    }

    public function test_a_project_without_a_municipality_is_covered_by_anyone(): void
    {
        $this->assertTrue(InstallerCoverage::covers([1, 2], 2));
        $this->assertFalse(InstallerCoverage::covers([1, 2], 3));
        $this->assertFalse(InstallerCoverage::covers([], 3));
        $this->assertTrue(InstallerCoverage::covers([], null));
    }
}
