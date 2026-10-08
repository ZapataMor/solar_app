<?php

namespace Tests\Unit\Domain;

use App\Domain\Installers\QuoteInclusions;
use App\Domain\Installers\QuoteSystem;
use PHPUnit\Framework\TestCase;

/**
 * ADR-0027: lo que una cotización real dice del sistema, y lo que la app completa sola.
 */
class QuoteSystemTest extends TestCase
{
    public function test_the_panels_give_the_power_when_the_installer_did_not_write_it(): void
    {
        $this->assertSame(8.8, QuoteSystem::powerKw(null, 16, 550));
        $this->assertSame(3.0, QuoteSystem::powerKw(null, 6, 500));
    }

    public function test_what_the_installer_wrote_wins_over_the_panels(): void
    {
        // They are the ones signing it: a system is more than the sum of its panels.
        $this->assertSame(9.5, QuoteSystem::powerKw(9.5, 16, 550));
    }

    public function test_without_panels_or_power_there_is_nothing_to_say(): void
    {
        $this->assertNull(QuoteSystem::powerKw(null, null, null));
        $this->assertNull(QuoteSystem::powerKw(null, 16, null));
        $this->assertNull(QuoteSystem::powerKw(0.0, 0, 550));
    }

    public function test_the_panel_text_uses_whatever_part_is_known(): void
    {
        $this->assertSame('16 paneles de 550 W · Jinko Tiger Neo', QuoteSystem::panelText(16, 550, 'Jinko Tiger Neo'));
        $this->assertSame('16 paneles', QuoteSystem::panelText(16, null, null));
        $this->assertSame('Paneles de 550 W', QuoteSystem::panelText(null, 550, null));
        $this->assertSame('1 panel', QuoteSystem::panelText(1, null, '  '));
        $this->assertNull(QuoteSystem::panelText(null, null, null));
    }

    public function test_the_inclusions_split_into_what_is_in_and_what_is_out(): void
    {
        $split = QuoteInclusions::split([
            QuoteInclusions::RETIE => true,
            QuoteInclusions::GRID_PAPERWORK => true,
        ]);

        $this->assertSame(['Certificación RETIE', 'Trámite con el operador de red'], array_column($split['included'], 'label'));
        // The ones not ticked are listed with what their absence means for the client.
        $this->assertCount(3, $split['excluded']);
        $this->assertStringContainsString('excedentes', $split['excluded'][0]['missing']);
    }

    public function test_a_quote_without_retie_or_paperwork_is_not_cheaper_only_shorter(): void
    {
        $this->assertTrue(QuoteInclusions::missesLegalization([]));
        $this->assertTrue(QuoteInclusions::missesLegalization([QuoteInclusions::RETIE => true]));
        $this->assertFalse(QuoteInclusions::missesLegalization([
            QuoteInclusions::RETIE => true,
            QuoteInclusions::GRID_PAPERWORK => true,
        ]));
    }
}
