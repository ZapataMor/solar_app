<?php

namespace Tests\Unit\Domain;

use App\Domain\Installers\QuoteComparison;
use App\Domain\Installers\QuoteInclusions;
use PHPUnit\Framework\TestCase;

/**
 * ADR-0028: the app marks the best of each row, never the best quote.
 */
class QuoteComparisonTest extends TestCase
{
    public function test_it_marks_the_cheapest_total_and_the_longest_warranty(): void
    {
        $comparison = QuoteComparison::of([
            $this->quote(['amountCop' => 22_000_000.0, 'panelWarrantyYears' => 25]),
            $this->quote(['amountCop' => 18_400_000.0, 'panelWarrantyYears' => 12]),
        ]);

        $this->assertSame([1], $this->best($comparison, 'amountCop'));
        $this->assertSame([0], $this->best($comparison, 'panelWarrantyYears'));
    }

    public function test_the_fastest_payback_wins_even_when_it_is_not_the_cheapest(): void
    {
        // The bigger system costs more and pays for itself sooner: that is the whole point of the
        // row, and why the app never names a single winner.
        $comparison = QuoteComparison::of([
            $this->quote(['amountCop' => 18_000_000.0, 'paybackYears' => 7.2]),
            $this->quote(['amountCop' => 24_000_000.0, 'paybackYears' => 5.1]),
        ]);

        $this->assertSame([0], $this->best($comparison, 'amountCop'));
        $this->assertSame([1], $this->best($comparison, 'paybackYears'));
    }

    public function test_nothing_is_marked_when_every_quote_says_the_same(): void
    {
        $comparison = QuoteComparison::of([
            $this->quote(['deliveryDays' => 45]),
            $this->quote(['deliveryDays' => 45]),
            $this->quote(['deliveryDays' => 45]),
        ]);

        // A badge on every cell would say nothing.
        $this->assertSame([], $this->best($comparison, 'deliveryDays'));
    }

    public function test_a_tie_for_the_best_marks_both(): void
    {
        $comparison = QuoteComparison::of([
            $this->quote(['inverterWarrantyYears' => 10]),
            $this->quote(['inverterWarrantyYears' => 5]),
            $this->quote(['inverterWarrantyYears' => 10]),
        ]);

        $this->assertSame([0, 2], $this->best($comparison, 'inverterWarrantyYears'));
    }

    public function test_an_expired_price_is_read_but_never_wins(): void
    {
        $comparison = QuoteComparison::of([
            $this->quote(['amountCop' => 14_000_000.0, 'expired' => true]),
            $this->quote(['amountCop' => 19_000_000.0]),
            $this->quote(['amountCop' => 21_000_000.0]),
        ]);

        // It is still in the table —the client asked for it— with its figure in the cell.
        $this->assertSame(14_000_000.0, $this->row($comparison, 'amountCop')['cells'][0]['value']);
        $this->assertSame([1], $this->best($comparison, 'amountCop'));
    }

    public function test_with_every_price_expired_nobody_is_marked(): void
    {
        $comparison = QuoteComparison::of([
            $this->quote(['amountCop' => 14_000_000.0, 'expired' => true]),
            $this->quote(['amountCop' => 19_000_000.0, 'expired' => true]),
        ]);

        $this->assertSame([], $this->best($comparison, 'amountCop'));
    }

    public function test_what_one_quote_does_not_say_is_missing_and_wins_nothing(): void
    {
        $comparison = QuoteComparison::of([
            $this->quote(['workmanshipWarrantyYears' => null]),
            $this->quote(['workmanshipWarrantyYears' => 2]),
        ]);

        $cells = $this->row($comparison, 'workmanshipWarrantyYears')['cells'];
        $this->assertTrue($cells[0]['missing']);
        $this->assertFalse($cells[1]['missing']);
        // Only one of them says it, so saying it is not "better than": there is nothing to compare.
        $this->assertSame([], $this->best($comparison, 'workmanshipWarrantyYears'));
    }

    public function test_a_row_nobody_declares_leaves_the_table_and_is_named_once(): void
    {
        $comparison = QuoteComparison::of([
            $this->quote(['downPaymentPercentage' => null]),
            $this->quote(['downPaymentPercentage' => null]),
        ]);

        $keys = [];

        foreach ($comparison['groups'] as $group) {
            foreach ($group['rows'] as $row) {
                $keys[] = $row['key'];
            }
        }

        $this->assertNotContains('downPaymentPercentage', $keys);
        $this->assertContains('Anticipo', $comparison['silent']);
    }

    public function test_without_a_calculation_the_payback_is_not_a_silence_of_the_installers(): void
    {
        $comparison = QuoteComparison::of([
            $this->quote(['paybackYears' => null]),
            $this->quote(['paybackYears' => null]),
        ], payback: false);

        $keys = [];

        foreach ($comparison['groups'] as $group) {
            $keys = [...$keys, ...array_column($group['rows'], 'key')];
        }

        // Nobody failed to declare it: it is the app that cannot work it out without a calculation,
        // so listing it among what nobody says would blame the installers for it.
        $this->assertNotContains(QuoteComparison::PAYBACK_ROW, $keys);
        $this->assertNotContains('Se paga en', $comparison['silent']);
    }

    public function test_what_the_price_covers_marks_the_one_that_covers_it(): void
    {
        $comparison = QuoteComparison::of([
            $this->quote([QuoteInclusions::BIDIRECTIONAL_METER => false]),
            $this->quote([QuoteInclusions::BIDIRECTIONAL_METER => true]),
        ]);

        $cells = $this->row($comparison, QuoteInclusions::BIDIRECTIONAL_METER)['cells'];
        // Not included is an answer, not a blank: the cell says no.
        $this->assertFalse($cells[0]['missing']);
        $this->assertSame([1], $this->best($comparison, QuoteInclusions::BIDIRECTIONAL_METER));
    }

    public function test_the_validity_is_compared_by_the_days_that_are_left(): void
    {
        $comparison = QuoteComparison::of([
            $this->quote(['validUntil' => '2026-11-01', 'daysLeft' => 25]),
            $this->quote(['validUntil' => '2026-12-20', 'daysLeft' => 74]),
        ]);

        $row = $this->row($comparison, 'validUntil');
        // The date is what the client reads; the days left are what can be compared.
        $this->assertSame('2026-12-20', $row['cells'][1]['value']);
        $this->assertSame([1], $this->best($comparison, 'validUntil'));
    }

    public function test_the_system_it_proposes_marks_nobody(): void
    {
        $comparison = QuoteComparison::of([
            $this->quote(['powerKw' => 4.4, 'monthlyGenerationKwh' => 900.0]),
            $this->quote(['powerKw' => 6.6, 'monthlyGenerationKwh' => 1400.0]),
        ]);

        // More kW is not better, it is different: the row explains the price instead of judging it.
        $this->assertSame(QuoteComparison::NEUTRAL, $this->row($comparison, 'powerKw')['direction']);
        $this->assertSame([], $this->best($comparison, 'powerKw'));
        $this->assertSame([], $this->best($comparison, 'monthlyGenerationKwh'));
    }

    public function test_the_rows_that_decide_come_first_and_in_the_order_of_the_adr(): void
    {
        $comparison = QuoteComparison::of([$this->quote(), $this->quote()]);
        $first = $comparison['groups'][0];

        $this->assertSame(
            ['amountCop', 'pricePerKwCop', 'paybackYears'],
            array_column($first['rows'], 'key'),
        );
    }

    public function test_it_warns_when_one_legalizes_the_installation_and_another_does_not(): void
    {
        $caveats = QuoteComparison::of([
            $this->quote(),
            $this->quote([QuoteInclusions::GRID_PAPERWORK => false]),
        ])['caveats'];

        $this->assertTrue($caveats['mixedLegalization']);
    }

    public function test_it_warns_about_batteries_and_about_systems_of_different_size(): void
    {
        $caveats = QuoteComparison::of([
            $this->quote(['powerKw' => 4.0]),
            $this->quote([QuoteInclusions::BATTERY => true, 'powerKw' => 6.5]),
        ])['caveats'];

        $this->assertTrue($caveats['mixedBattery']);
        $this->assertSame(2.5, $caveats['powerSpreadKw']);
        $this->assertSame(0, $caveats['expired']);
    }

    public function test_an_expired_quote_does_not_raise_a_warning_about_a_choice_that_is_gone(): void
    {
        $caveats = QuoteComparison::of([
            $this->quote(),
            $this->quote(),
            // The only one that leaves the paperwork out expired last month: warning that "not all
            // of them legalize the installation" would be false of everything still buyable.
            $this->quote([QuoteInclusions::GRID_PAPERWORK => false, 'expired' => true]),
        ])['caveats'];

        $this->assertFalse($caveats['mixedLegalization']);
        $this->assertSame(1, $caveats['expired']);
    }

    public function test_it_warns_when_one_total_carries_vat_and_another_does_not(): void
    {
        $caveats = QuoteComparison::of([
            $this->quote(['amountCop' => 16_000_000.0, 'vatIncluded' => false]),
            $this->quote(['amountCop' => 18_000_000.0, 'vatIncluded' => true]),
        ])['caveats'];

        // 16 millones + IVA is more than 18 with it: the lowest total is not the cheapest to pay.
        $this->assertTrue($caveats['mixedVat']);
    }

    public function test_a_quote_that_does_not_mention_vat_is_not_a_quote_without_vat(): void
    {
        $caveats = QuoteComparison::of([
            $this->quote(['vatIncluded' => true]),
            $this->quote(['vatIncluded' => null]),
        ])['caveats'];

        // Not saying is not the same as saying VAT goes on top; the cell already reads "no lo dice".
        $this->assertFalse($caveats['mixedVat']);
    }

    public function test_quotes_with_the_same_scope_raise_no_warning(): void
    {
        $caveats = QuoteComparison::of([$this->quote(), $this->quote()])['caveats'];

        $this->assertFalse($caveats['mixedLegalization']);
        $this->assertFalse($caveats['mixedBattery']);
        $this->assertFalse($caveats['mixedVat']);
        $this->assertSame(0.0, $caveats['powerSpreadKw']);
        $this->assertSame(0, $caveats['expired']);
    }

    public function test_each_cell_says_how_much_it_wins_or_loses_against_the_best_of_the_others(): void
    {
        $comparison = QuoteComparison::of([
            $this->quote(['panelWarrantyYears' => 25]),
            $this->quote(['panelWarrantyYears' => 12]),
        ]);

        $cells = $this->row($comparison, 'panelWarrantyYears')['cells'];
        // The head to head of the design: +13 on one side, -13 on the other.
        $this->assertSame(13.0, $cells[0]['advantage']);
        $this->assertSame(-13.0, $cells[1]['advantage']);
    }

    public function test_the_winner_of_a_row_shows_what_it_takes_off_the_runner_up(): void
    {
        $comparison = QuoteComparison::of([
            $this->quote(['amountCop' => 16_000_000.0]),
            $this->quote(['amountCop' => 18_000_000.0]),
            $this->quote(['amountCop' => 25_000_000.0]),
        ]);

        $cells = $this->row($comparison, 'amountCop')['cells'];
        // Cheaper is better, so being 2 millones under the runner-up is an advantage of 2 millones.
        $this->assertSame(2_000_000.0, $cells[0]['advantage']);
        $this->assertSame(-2_000_000.0, $cells[1]['advantage']);
        $this->assertSame(-9_000_000.0, $cells[2]['advantage']);
    }

    public function test_the_verdict_counts_rows_won_and_how_much_each_one_declares(): void
    {
        $comparison = QuoteComparison::of([
            $this->quote(['amountCop' => 16_000_000.0, 'panelWarrantyYears' => null, 'deliveryDays' => null]),
            $this->quote(['amountCop' => 25_000_000.0, 'panelWarrantyYears' => 25, 'deliveryDays' => 30]),
        ]);

        [$cheap, $complete] = $comparison['verdict'];

        $this->assertGreaterThan(0, $cheap['rowsWon']);
        $this->assertSame($cheap['rowsCompared'], $complete['rowsCompared']);
        // What it leaves blank is counted too: that is the other half of the verdict.
        $this->assertSame(2, $complete['declared'] - $cheap['declared']);
        $this->assertContains('es la más barata', $cheap['highlights']);
    }

    public function test_a_row_won_by_two_quotes_is_nobody_headline(): void
    {
        $comparison = QuoteComparison::of([
            $this->quote([QuoteInclusions::BATTERY => true]),
            $this->quote([QuoteInclusions::BATTERY => true]),
            $this->quote([QuoteInclusions::BATTERY => false]),
        ]);

        // Two of them carry batteries, so "la única con baterías" would be false of both.
        foreach ($comparison['verdict'] as $verdict) {
            $this->assertNotContains('es la única con baterías', $verdict['highlights']);
        }
    }

    public function test_it_recommends_the_one_that_wins_more_rows_and_says_why(): void
    {
        $comparison = QuoteComparison::of([
            $this->quote(['amountCop' => 25_000_000.0, 'panelWarrantyYears' => 25, 'deliveryDays' => 30]),
            $this->quote(['amountCop' => 24_000_000.0, 'panelWarrantyYears' => 12, 'deliveryDays' => 60]),
        ]);

        $recommendation = $comparison['recommendation'];

        $this->assertSame(0, $recommendation['index']);
        $this->assertNotEmpty($recommendation['reasons']);
        // It is not the cheapest, so the app says so with the difference in hand.
        $this->assertSame(1, $recommendation['cheaperIndex']);
        $this->assertSame(1_000_000.0, $recommendation['cheaperByCop']);
        $this->assertNotNull($recommendation['tradeoff']);
    }

    public function test_it_does_not_recommend_a_cheaper_quote_that_skips_the_legalization(): void
    {
        $comparison = QuoteComparison::of([
            // Much cheaper, and without RETIE or grid paperwork: the client pays those anyway.
            $this->quote([
                'amountCop' => 14_000_000.0,
                QuoteInclusions::RETIE => false,
                QuoteInclusions::GRID_PAPERWORK => false,
            ]),
            $this->quote(['amountCop' => 22_000_000.0]),
        ]);

        $recommendation = $comparison['recommendation'];

        $this->assertSame(1, $recommendation['index']);
        $this->assertContains('cubre el RETIE y el trámite con el operador de red', $recommendation['reasons']);
        $this->assertSame(0, $recommendation['cheaperIndex']);
        $this->assertSame('no cubre lo que legaliza la instalación, y eso lo terminas pagando aparte', $recommendation['tradeoff']);
    }

    public function test_an_expired_quote_is_never_the_recommended_one(): void
    {
        $comparison = QuoteComparison::of([
            // The best on paper, but its price is no longer a price (ADR-0026).
            $this->quote(['amountCop' => 15_000_000.0, 'panelWarrantyYears' => 30, 'expired' => true]),
            $this->quote(['amountCop' => 22_000_000.0]),
        ]);

        $this->assertSame(1, $comparison['recommendation']['index']);
    }

    public function test_with_every_price_expired_the_app_recommends_nobody(): void
    {
        $comparison = QuoteComparison::of([
            $this->quote(['expired' => true]),
            $this->quote(['amountCop' => 15_000_000.0, 'expired' => true]),
        ]);

        $this->assertNull($comparison['recommendation']);
    }

    public function test_the_cheapest_one_recommended_has_nothing_to_warn_about(): void
    {
        $comparison = QuoteComparison::of([
            $this->quote(['amountCop' => 16_000_000.0, 'panelWarrantyYears' => 25]),
            $this->quote(['amountCop' => 22_000_000.0, 'panelWarrantyYears' => 12]),
        ]);

        $recommendation = $comparison['recommendation'];

        $this->assertSame(0, $recommendation['index']);
        $this->assertNull($recommendation['cheaperIndex']);
        $this->assertNull($recommendation['tradeoff']);
    }

    /**
     * One quote with everything filled in, so each test changes only what it is about.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function quote(array $overrides = []): array
    {
        return [
            'amountCop' => 20_000_000.0,
            'powerKw' => 5.0,
            'pricePerKwCop' => 4_000_000.0,
            'paybackYears' => 5.4,
            QuoteInclusions::RETIE => true,
            QuoteInclusions::GRID_PAPERWORK => true,
            QuoteInclusions::BIDIRECTIONAL_METER => true,
            QuoteInclusions::BATTERY => false,
            QuoteInclusions::MAINTENANCE => true,
            'panelWarrantyYears' => 25,
            'inverterWarrantyYears' => 10,
            'workmanshipWarrantyYears' => 2,
            'downPaymentPercentage' => 40,
            'deliveryDays' => 45,
            'vatIncluded' => true,
            'validUntil' => '2026-11-15',
            'daysLeft' => 30,
            'panelText' => '16 paneles de 550 W',
            'inverterModel' => 'Growatt MIN 5000TL-X',
            'batteryText' => 'No lleva',
            'monthlyGenerationKwh' => 1_100.0,
            'expired' => false,
            ...$overrides,
        ];
    }

    /**
     * @param  array<string, mixed>  $comparison
     * @return array{key: string, label: string, hint: string|null, format: string, direction: string, cells: list<array{value: mixed, missing: bool, best: bool}>}
     */
    private function row(array $comparison, string $key): array
    {
        foreach ($comparison['groups'] as $group) {
            foreach ($group['rows'] as $row) {
                if ($row['key'] === $key) {
                    return $row;
                }
            }
        }

        $this->fail("La fila {$key} no está en la tabla.");
    }

    /**
     * The columns marked as the best of a row.
     *
     * @param  array<string, mixed>  $comparison
     * @return list<int>
     */
    private function best(array $comparison, string $key): array
    {
        $marked = [];

        foreach ($this->row($comparison, $key)['cells'] as $index => $cell) {
            if ($cell['best']) {
                $marked[] = $index;
            }
        }

        return $marked;
    }
}
