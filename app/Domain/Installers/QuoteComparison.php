<?php

namespace App\Domain\Installers;

/**
 * The quotes of one project, side by side (ADR-0028).
 *
 * The advice everybody repeats is "ask for at least three quotes". Nobody says how to compare them,
 * and the total lies: the cheapest one is usually the one that leaves out the paperwork with the grid
 * operator and the bidirectional meter, which together are several million pesos.
 *
 * So this is what the class does, and what it refuses to do: it marks **the best of each row** —the
 * fastest payback, the longest warranty, the shortest lead time— and never the best quote. Who wins
 * depends on what the client values, and ADR-0005 charges for the closing: the platform cannot tip
 * the scale.
 *
 * Three rules keep the marks honest:
 * - An expired price is not a price (ADR-0026), so it is shown but never competes.
 * - With a single candidate there is no "better": a badge would only mean "the only one that says it".
 * - When every candidate says the same, nothing is marked: a badge on every cell says nothing.
 *
 * It formats nothing. Each row carries the format its cells use and the view writes the pesos, so
 * there is one money format in the app and not two that drift apart.
 */
final class QuoteComparison
{
    /** Without two quotes there is no screen: the client stays on the detail page (ADR-0028). */
    public const MINIMUM = 2;

    /** The one row the app answers instead of the installer, so it leaves without a calculation. */
    public const PAYBACK_ROW = 'paybackYears';

    /** Cheaper, faster, sooner. */
    public const LOWER = 'lower';

    /** More years, more days of validity. */
    public const HIGHER = 'higher';

    /** More kW is not better, it is different: the row is shown without marking anybody. */
    public const NEUTRAL = 'neutral';

    public const MONEY = 'money';

    public const PAYBACK = 'payback';

    public const YEARS = 'years';

    public const KW = 'kw';

    public const KWH = 'kwh';

    public const PERCENT = 'percent';

    public const DAYS = 'days';

    public const DATE = 'date';

    /** Included or not: the cell says which, never a blank. */
    public const FLAG = 'flag';

    public const TEXT = 'text';

    /**
     * The whole table: the groups of rows with the best of each one marked, what nobody declared,
     * and the warnings that have to be read before the totals.
     *
     * @param  list<array<string, mixed>>  $quotes  one per quote, with the keys of the rows plus
     *                                              `expired`; a missing key is a cell nobody filled.
     * @param  bool  $payback  false when the project has no calculation. The payback row then leaves
     *                         the table without becoming a silence of the installers: nobody failed
     *                         to declare it, it is the app that cannot work it out yet.
     * @return array{
     *     groups: list<array{title: string, note: string|null, rows: list<array{key: string, label: string, hint: string|null, format: string, direction: string, cells: list<array{value: mixed, missing: bool, best: bool}>}>}>,
     *     silent: list<string>,
     *     caveats: array{mixedLegalization: bool, mixedBattery: bool, mixedVat: bool, powerSpreadKw: float|null, expired: int},
     *     verdict: list<array{rowsWon: int, rowsCompared: int, declared: int, declarable: int, highlights: list<string>, expired: bool}>,
     *     recommendation: array{index: int, reasons: list<string>, cheaperIndex: int|null, cheaperByCop: float|null, tradeoff: string|null}|null,
     * }
     */
    public static function of(array $quotes, bool $payback = true): array
    {
        $groups = [];
        $silent = [];

        foreach (self::definitions() as $definition) {
            $rows = [];

            foreach ($definition['rows'] as $row) {
                if ($row['key'] === self::PAYBACK_ROW && ! $payback) {
                    continue;
                }

                $cells = self::cells($row, $quotes);

                // Nobody declared it. Keeping the row would add an empty line to every column; what
                // is worth saying is that none of them says it, and that goes in `silent`.
                if (self::allMissing($cells)) {
                    $silent[] = $row['label'];

                    continue;
                }

                $rows[] = [...$row, 'cells' => $cells];
            }

            if ($rows !== []) {
                $groups[] = ['title' => $definition['title'], 'note' => $definition['note'], 'rows' => $rows];
            }
        }

        $verdict = self::verdict($groups, $quotes);

        return [
            'groups' => $groups,
            'silent' => $silent,
            'caveats' => self::caveats($quotes),
            'verdict' => $verdict,
            'recommendation' => self::recommendation($quotes, $verdict),
        ];
    }

    /**
     * What each quote is worth, said in two figures anybody can check: how many of the rows that
     * compare it wins, and how much of what it could declare it actually declared.
     *
     * They are counts, not a score: no weights nobody agreed on, and every claim traces back to the
     * row it came from. `highlights` only names the rows this quote wins **alone**, so "la única
     * con RETIE" is never written about something two of them cover.
     *
     * @param  list<array{title: string, note: string|null, rows: list<array<string, mixed>>}>  $groups
     * @param  list<array<string, mixed>>  $quotes
     * @return list<array{rowsWon: int, rowsCompared: int, declared: int, declarable: int, highlights: list<string>, expired: bool}>
     */
    private static function verdict(array $groups, array $quotes): array
    {
        $verdict = array_map(fn (array $quote): array => [
            'rowsWon' => 0,
            'rowsCompared' => 0,
            'declared' => 0,
            'declarable' => 0,
            'highlights' => [],
            'expired' => (bool) ($quote['expired'] ?? false),
        ], $quotes);

        foreach ($groups as $group) {
            foreach ($group['rows'] as $row) {
                $compares = $row['direction'] !== self::NEUTRAL;
                $winners = [];

                foreach ($row['cells'] as $index => $cell) {
                    $verdict[$index]['declarable']++;

                    if (! $cell['missing']) {
                        $verdict[$index]['declared']++;
                    }

                    if ($compares) {
                        $verdict[$index]['rowsCompared']++;

                        if ($cell['best']) {
                            $verdict[$index]['rowsWon']++;
                            $winners[] = $index;
                        }
                    }
                }

                // Only a row won alone earns a headline: "la única con RETIE" has to be true.
                $headline = count($winners) === 1 ? self::headline($row['key']) : null;

                if ($headline !== null) {
                    $verdict[$winners[0]]['highlights'][] = $headline;
                }
            }
        }

        return $verdict;
    }

    /**
     * Who the app recommends, and why (ADR-0029).
     *
     * The rule is written here instead of hidden inside a weighting, so the client can disagree with
     * it: an expired price is not an offer; a quote that leaves the legalization out is not
     * recommended while another one covers it, because the client pays that paperwork either way;
     * and among the ones left, the one that wins the most rows, then the most complete, then the
     * cheapest. When the recommended one is not the cheapest, that is said first.
     *
     * @param  list<array<string, mixed>>  $quotes
     * @param  list<array{rowsWon: int, rowsCompared: int, declared: int, declarable: int, highlights: list<string>, expired: bool}>  $verdict
     * @return array{index: int, reasons: list<string>, cheaperIndex: int|null, cheaperByCop: float|null, tradeoff: string|null}|null
     */
    private static function recommendation(array $quotes, array $verdict): ?array
    {
        $live = [];

        foreach ($quotes as $index => $quote) {
            if (! ($quote['expired'] ?? false)) {
                $live[] = $index;
            }
        }

        if ($live === []) {
            return null;
        }

        // Legalizing is not one more row: without RETIE and the grid paperwork the client pays
        // millions on their own, so one that skips them is not recommended while another covers them.
        $legal = array_values(array_filter(
            $live,
            fn (int $index): bool => ! QuoteInclusions::missesLegalization(self::flags($quotes[$index])),
        ));
        $skippedForLegalization = $legal !== [] && count($legal) < count($live);
        $candidates = $legal !== [] ? $legal : $live;

        usort(
            $candidates,
            fn (int $a, int $b): int => [$verdict[$b]['rowsWon'], $verdict[$b]['declared'], -self::price($quotes[$b])]
                <=> [$verdict[$a]['rowsWon'], $verdict[$a]['declared'], -self::price($quotes[$a])],
        );

        $chosen = $candidates[0];
        $reasons = $verdict[$chosen]['highlights'];

        if ($skippedForLegalization) {
            // Dicho una vez: sus dos filas ya ganadas repetirían la misma razón con otras palabras.
            $covered = [self::headline(QuoteInclusions::RETIE), self::headline(QuoteInclusions::GRID_PAPERWORK)];
            $reasons = array_values(array_diff($reasons, $covered));
            array_unshift($reasons, 'cubre el RETIE y el trámite con el operador de red');
        }

        if ($reasons === []) {
            $reasons[] = 'gana '.$verdict[$chosen]['rowsWon'].' de '.$verdict[$chosen]['rowsCompared'].' filas';
        }

        $cheaper = null;

        foreach ($live as $index) {
            $cheaperThanChosen = self::price($quotes[$index]) < self::price($quotes[$chosen]);
            $cheapestSoFar = $cheaper === null || self::price($quotes[$index]) < self::price($quotes[$cheaper]);

            if ($index !== $chosen && $cheaperThanChosen && $cheapestSoFar) {
                $cheaper = $index;
            }
        }

        return [
            'index' => $chosen,
            'reasons' => array_slice($reasons, 0, 3),
            'cheaperIndex' => $cheaper,
            'cheaperByCop' => $cheaper === null
                ? null
                : round(self::price($quotes[$chosen]) - self::price($quotes[$cheaper]), 2),
            'tradeoff' => $cheaper === null ? null : self::tradeoff($quotes[$cheaper], $verdict[$cheaper]),
        ];
    }

    /**
     * Why the cheaper one is not the recommended one, in one line.
     *
     * @param  array<string, mixed>  $quote
     * @param  array{rowsWon: int, rowsCompared: int, declared: int, declarable: int, highlights: list<string>, expired: bool}  $verdict
     */
    private static function tradeoff(array $quote, array $verdict): string
    {
        if (QuoteInclusions::missesLegalization(self::flags($quote))) {
            return 'no cubre lo que legaliza la instalación, y eso lo terminas pagando aparte';
        }

        if ($verdict['declared'] < $verdict['declarable']) {
            return 'deja sin decir '.($verdict['declarable'] - $verdict['declared']).' de los datos de la tabla';
        }

        return 'gana menos filas de la tabla';
    }

    /**
     * @param  array<string, mixed>  $quote
     */
    private static function price(array $quote): float
    {
        return self::numeric($quote['amountCop'] ?? null) ?? INF;
    }

    /**
     * What winning a row alone is worth saying as, for the reasons of a recommendation. A row with
     * no headline explains nothing on its own and adds nothing to them.
     */
    private static function headline(string $rowKey): ?string
    {
        return match ($rowKey) {
            'amountCop' => 'es la más barata',
            'pricePerKwCop' => 'cobra menos por cada kW instalado',
            self::PAYBACK_ROW => 'se paga en menos tiempo',
            'panelWarrantyYears' => 'garantiza los paneles por más años',
            'inverterWarrantyYears' => 'garantiza el inversor por más años',
            'workmanshipWarrantyYears' => 'responde por la obra por más tiempo',
            'downPaymentPercentage' => 'pide el anticipo más bajo',
            'deliveryDays' => 'es la que menos se demora',
            'vatIncluded' => 'es la única con el IVA incluido',
            QuoteInclusions::RETIE => 'es la única que trae el RETIE',
            QuoteInclusions::GRID_PAPERWORK => 'es la única que hace el trámite con el operador de red',
            QuoteInclusions::BIDIRECTIONAL_METER => 'es la única que pone el medidor bidireccional',
            QuoteInclusions::BATTERY => 'es la única con baterías',
            QuoteInclusions::MAINTENANCE => 'es la única con mantenimiento del primer año',
            default => null,
        };
    }

    /**
     * What has to be said above the numbers: comparing quotes with a different scope is comparing
     * apples to oranges, and the screen cannot stop it, only warn about it.
     *
     * Only the quotes that can still be bought are scanned, for the same reason an expired price
     * wins nothing: warning that "not all of them legalize the installation" because of an offer
     * that expired last month is a false alarm about a choice the client no longer has. The expired
     * ones are counted apart, which is the warning they do deserve.
     *
     * @param  list<array<string, mixed>>  $quotes
     * @return array{mixedLegalization: bool, mixedBattery: bool, mixedVat: bool, powerSpreadKw: float|null, expired: int}
     */
    public static function caveats(array $quotes): array
    {
        $legalization = [];
        $batteries = [];
        $vat = [];
        $powers = [];
        $expired = 0;

        foreach ($quotes as $quote) {
            if ($quote['expired'] ?? false) {
                $expired++;

                continue;
            }

            $legalization[] = QuoteInclusions::missesLegalization(self::flags($quote));
            $batteries[] = (bool) ($quote[QuoteInclusions::BATTERY] ?? false);

            // Null is "they did not say", which is not the same as saying VAT goes on top.
            if (($quote['vatIncluded'] ?? null) !== null) {
                $vat[] = (bool) $quote['vatIncluded'];
            }

            $power = self::numeric($quote['powerKw'] ?? null);

            if ($power !== null && $power > 0) {
                $powers[] = $power;
            }
        }

        return [
            // One legalizes the installation and another does not: their totals are not the same thing.
            'mixedLegalization' => count(array_unique($legalization, SORT_REGULAR)) > 1,
            'mixedBattery' => count(array_unique($batteries, SORT_REGULAR)) > 1,
            // One total carries VAT and another does not: 16 millones + IVA is more than 18 with it.
            'mixedVat' => count(array_unique($vat, SORT_REGULAR)) > 1,
            'powerSpreadKw' => $powers === [] ? null : round(max($powers) - min($powers), 2),
            'expired' => $expired,
        ];
    }

    /**
     * The rows, in the order of ADR-0028: total price, price per kW, payback, what it covers,
     * warranties, down payment and lead time, validity. The system each installer proposes closes
     * the table as context, without marks: it explains the price instead of judging it.
     *
     * @return list<array{title: string, note: string|null, rows: list<array{key: string, label: string, hint: string|null, format: string, direction: string, compare?: string}>}>
     */
    private static function definitions(): array
    {
        return [
            [
                'title' => 'Lo que decide',
                'note' => null,
                'rows' => [
                    [
                        'key' => 'amountCop',
                        'label' => 'Precio total',
                        'hint' => 'Lo que te cuesta la instalación según esta cotización.',
                        'format' => self::MONEY,
                        'direction' => self::LOWER,
                    ],
                    [
                        'key' => 'pricePerKwCop',
                        'label' => 'Precio por kW',
                        'hint' => 'Lo que cobra por cada kilovatio: así se comparan dos sistemas de distinto tamaño.',
                        'format' => self::MONEY,
                        'direction' => self::LOWER,
                    ],
                    [
                        'key' => self::PAYBACK_ROW,
                        'label' => 'Se paga en',
                        'hint' => 'Con el ahorro que calculamos para tu proyecto, no con el que promete la cotización.',
                        'format' => self::PAYBACK,
                        'direction' => self::LOWER,
                    ],
                ],
            ],
            [
                'title' => 'Qué cubre el precio',
                'note' => 'Lo que no cubra el precio lo pagas aparte.',
                'rows' => self::inclusionRows(),
            ],
            [
                'title' => 'Garantías',
                'note' => 'Lo usual son 25 años en paneles, 5 a 10 en el inversor y 1 a 2 en la obra.',
                'rows' => [
                    [
                        'key' => 'panelWarrantyYears',
                        'label' => 'Garantía de los paneles',
                        'hint' => null,
                        'format' => self::YEARS,
                        'direction' => self::HIGHER,
                    ],
                    [
                        'key' => 'inverterWarrantyYears',
                        'label' => 'Garantía del inversor',
                        'hint' => 'El inversor es la pieza que más se cambia: es la garantía que más vale.',
                        'format' => self::YEARS,
                        'direction' => self::HIGHER,
                    ],
                    [
                        'key' => 'workmanshipWarrantyYears',
                        'label' => 'Garantía de la obra',
                        'hint' => null,
                        'format' => self::YEARS,
                        'direction' => self::HIGHER,
                    ],
                ],
            ],
            [
                'title' => 'Condiciones',
                'note' => null,
                'rows' => [
                    [
                        'key' => 'downPaymentPercentage',
                        'label' => 'Anticipo',
                        'hint' => 'Cuánto hay que pagar antes de que empiecen.',
                        'format' => self::PERCENT,
                        'direction' => self::LOWER,
                    ],
                    [
                        'key' => 'deliveryDays',
                        'label' => 'Plazo hasta energizar',
                        'hint' => null,
                        'format' => self::DAYS,
                        'direction' => self::LOWER,
                    ],
                    [
                        'key' => 'vatIncluded',
                        'label' => 'IVA incluido en el precio',
                        'hint' => 'Si va aparte, el total que ves todavía puede subir.',
                        'format' => self::FLAG,
                        'direction' => self::HIGHER,
                    ],
                    [
                        'key' => 'validUntil',
                        'label' => 'El precio vale hasta',
                        'hint' => 'Los equipos son importados: cuando vence, hay que pedirlo otra vez.',
                        'format' => self::DATE,
                        'direction' => self::HIGHER,
                        // The date is what the client reads; the days left are what can be compared.
                        'compare' => 'daysLeft',
                    ],
                ],
            ],
            [
                'title' => 'El sistema que te propone',
                'note' => 'Aquí no hay mejor ni peor: hay distinto.',
                'rows' => [
                    [
                        'key' => 'powerKw',
                        'label' => 'Potencia',
                        'hint' => null,
                        'format' => self::KW,
                        'direction' => self::NEUTRAL,
                    ],
                    [
                        'key' => 'panelText',
                        'label' => 'Paneles',
                        'hint' => null,
                        'format' => self::TEXT,
                        'direction' => self::NEUTRAL,
                    ],
                    [
                        'key' => 'inverterModel',
                        'label' => 'Inversor',
                        'hint' => null,
                        'format' => self::TEXT,
                        'direction' => self::NEUTRAL,
                    ],
                    [
                        'key' => 'batteryText',
                        'label' => 'Baterías',
                        'hint' => null,
                        'format' => self::TEXT,
                        'direction' => self::NEUTRAL,
                    ],
                    [
                        'key' => 'monthlyGenerationKwh',
                        'label' => 'Producción que promete al mes',
                        'hint' => 'Lo que dice que produce al mes; nuestro cálculo puede decir otra cosa.',
                        'format' => self::KWH,
                        'direction' => self::NEUTRAL,
                    ],
                ],
            ],
        ];
    }

    /**
     * One row per item of what a quote covers, with the labels of ADR-0027. The list is not copied
     * here: it is the same one the installer ticks and the client reads in the detail page.
     *
     * @return list<array{key: string, label: string, hint: string|null, format: string, direction: string}>
     */
    private static function inclusionRows(): array
    {
        $rows = [];

        foreach (QuoteInclusions::all() as $key => $item) {
            $rows[] = [
                'key' => $key,
                'label' => $item['label'],
                'hint' => $item['hint'],
                'format' => self::FLAG,
                'direction' => self::HIGHER,
            ];
        }

        return $rows;
    }

    /**
     * @param  array{key: string, label: string, hint: string|null, format: string, direction: string, compare?: string}  $row
     * @param  list<array<string, mixed>>  $quotes
     * @return list<array{value: mixed, missing: bool, best: bool}>
     */
    private static function cells(array $row, array $quotes): array
    {
        $cells = [];

        foreach ($quotes as $quote) {
            $value = $quote[$row['key']] ?? null;
            $value = is_string($value) && trim($value) === '' ? null : $value;

            $cells[] = ['value' => $value, 'missing' => $value === null, 'best' => false, 'advantage' => null];
        }

        return self::markBest($row, $quotes, $cells);
    }

    /**
     * @param  array{key: string, label: string, hint: string|null, format: string, direction: string, compare?: string}  $row
     * @param  list<array<string, mixed>>  $quotes
     * @param  list<array{value: mixed, missing: bool, best: bool}>  $cells
     * @return list<array{value: mixed, missing: bool, best: bool}>
     */
    private static function markBest(array $row, array $quotes, array $cells): array
    {
        if ($row['direction'] === self::NEUTRAL) {
            return $cells;
        }

        $compareKey = $row['compare'] ?? $row['key'];
        $candidates = [];

        foreach ($quotes as $index => $quote) {
            // An expired price is not a price: it is read, but it does not win anything (ADR-0026).
            if ($cells[$index]['missing'] || ($quote['expired'] ?? false)) {
                continue;
            }

            $comparable = self::numeric($quote[$compareKey] ?? null);

            if ($comparable !== null) {
                $candidates[$index] = $comparable;
            }
        }

        // One candidate is not a comparison —the badge would mean "the only one that says it"— and
        // when everybody says the same there is nothing to point at: a badge on every cell is noise
        // with the shape of information.
        if (count($candidates) < 2 || self::allEqual($candidates)) {
            return $cells;
        }

        $best = $row['direction'] === self::LOWER ? min($candidates) : max($candidates);

        foreach ($candidates as $index => $value) {
            if (abs($value - $best) < 1e-9) {
                $cells[$index]['best'] = true;
            }

            // How much this cell wins or loses against the best of the others, positive when it is
            // ahead. With two quotes it reads as the +13 / -13 of a head to head; with five, the
            // winner shows what it takes off the runner-up and the rest what they give away.
            $others = $candidates;
            unset($others[$index]);

            if ($others !== []) {
                $rival = $row['direction'] === self::LOWER ? min($others) : max($others);
                $cells[$index]['advantage'] = round(
                    $row['direction'] === self::LOWER ? $rival - $value : $value - $rival,
                    2,
                );
            }
        }

        return $cells;
    }

    /**
     * @param  array<string, mixed>  $quote
     * @return array<string, bool>
     */
    private static function flags(array $quote): array
    {
        $flags = [];

        foreach (QuoteInclusions::ALL as $key) {
            $flags[$key] = (bool) ($quote[$key] ?? false);
        }

        return $flags;
    }

    /** Booleans count as 1 and 0, so "included" and "25 years" are compared the same way. */
    private static function numeric(mixed $value): ?float
    {
        return match (true) {
            is_bool($value) => $value ? 1.0 : 0.0,
            is_int($value), is_float($value) => (float) $value,
            is_string($value) && is_numeric($value) => (float) $value,
            default => null,
        };
    }

    /**
     * @param  array<int, float>  $values
     */
    private static function allEqual(array $values): bool
    {
        return $values === [] || abs(max($values) - min($values)) < 1e-9;
    }

    /**
     * @param  list<array{value: mixed, missing: bool, best: bool}>  $cells
     */
    private static function allMissing(array $cells): bool
    {
        foreach ($cells as $cell) {
            if (! $cell['missing']) {
                return false;
            }
        }

        return true;
    }
}
