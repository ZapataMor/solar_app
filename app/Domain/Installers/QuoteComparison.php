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
     *     caveats: array{mixedLegalization: bool, mixedBattery: bool, powerSpreadKw: float|null, expired: int},
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

        return [
            'groups' => $groups,
            'silent' => $silent,
            'caveats' => self::caveats($quotes),
        ];
    }

    /**
     * What has to be said above the numbers: comparing quotes with a different scope is comparing
     * apples to oranges, and the screen cannot stop it, only warn about it.
     *
     * @param  list<array<string, mixed>>  $quotes
     * @return array{mixedLegalization: bool, mixedBattery: bool, powerSpreadKw: float|null, expired: int}
     */
    public static function caveats(array $quotes): array
    {
        $legalization = [];
        $batteries = [];
        $powers = [];
        $expired = 0;

        foreach ($quotes as $quote) {
            $legalization[] = QuoteInclusions::missesLegalization(self::flags($quote));
            $batteries[] = (bool) ($quote[QuoteInclusions::BATTERY] ?? false);
            $power = self::numeric($quote['powerKw'] ?? null);

            if ($power !== null && $power > 0) {
                $powers[] = $power;
            }

            if ($quote['expired'] ?? false) {
                $expired++;
            }
        }

        return [
            // One legalizes the installation and another does not: their totals are not the same thing.
            'mixedLegalization' => count(array_unique($legalization, SORT_REGULAR)) > 1,
            'mixedBattery' => count(array_unique($batteries, SORT_REGULAR)) > 1,
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
                'note' => 'En Colombia el trámite ante el operador de red y el medidor bidireccional son millones de pesos: la cotización más barata suele ser la que los deja afuera.',
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
                'note' => 'Aquí no hay mejor ni peor: hay distinto. Dos cotizaciones solo se comparan si llevan lo mismo.',
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

            $cells[] = ['value' => $value, 'missing' => $value === null, 'best' => false];
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
