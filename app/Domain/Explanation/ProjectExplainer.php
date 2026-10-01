<?php

namespace App\Domain\Explanation;

/**
 * Turns a project's numbers into questions a non-technical client can understand (ADR-0011).
 *
 * Answers come from templates fed with the project's own figures (no AI), so they are instant,
 * consistent with the panel, and testable. Comparisons are approximations and are worded as such.
 */
final class ProjectExplainer
{
    /** Typical lifetime of a PV installation, used for "how much over its life" answers. */
    public const PANEL_LIFETIME_YEARS = 25;

    /** Months with fewer measured days than this are not used to name the best or worst month. */
    public const MIN_DAYS_TO_COMPARE_A_MONTH = 7;

    /**
     * @return list<ExplainedQuestion>
     */
    public function explain(ProjectFigures $figures): array
    {
        if (! $figures->calculated) {
            return [$figures->monthlyConsumptionKwh > 0 ? $this->notCalculated() : $this->noAppliances()];
        }

        return [
            $this->isItWorthIt($figures),
            $this->howMuchDoesItCost($figures),
            $this->howMuchWillISave($figures),
            $this->whenDoIGetMyMoneyBack($figures),
            $this->howMuchDoesTheSunPay($figures),
            $this->generationVersusConsumption($figures),
            $this->howManyPanels($figures),
            $this->nightAndCloudyDays(),
            $this->howReliable($figures),
            $this->whatIsAKwh($figures),
        ];
    }

    private function notCalculated(): ExplainedQuestion
    {
        return new ExplainedQuestion(
            key: 'not-calculated',
            question: '¿Por qué no veo respuestas?',
            headline: 'Falta calcular',
            caption: 'tu proyecto todavía no tiene resultados',
            paragraphs: [
                'Para responder cuánto cuesta, cuánto ahorras y si te conviene, primero hay que calcular el proyecto con los datos de sol de tu zona.',
                'Pulsa "Calcular" en el panel y vuelve aquí: todas las preguntas tendrán respuesta.',
            ],
            tone: ExplainedQuestion::TONE_WARNING,
        );
    }

    /**
     * The appliances are the base of the calculation (ADR-0013): without them there is nothing to answer.
     */
    private function noAppliances(): ExplainedQuestion
    {
        return new ExplainedQuestion(
            key: 'no-appliances',
            question: '¿Por qué no veo respuestas?',
            headline: 'Faltan tus equipos',
            caption: 'con ellos calculamos tu sistema',
            paragraphs: [
                'Para saber cuántos paneles necesitas y cuánto ahorras, primero hay que saber cuánta energía usas.',
                'En la pestaña Consumo recorre cada espacio (cocina, sala, habitaciones…) y agrega lo que tienes. Luego calcula y vuelve aquí.',
            ],
            tone: ExplainedQuestion::TONE_WARNING,
        );
    }

    private function isItWorthIt(ProjectFigures $f): ExplainedQuestion
    {
        $question = '¿Me conviene instalar paneles solares?';

        if ($f->paybackYears === null || $f->annualSavingsCop <= 0) {
            return new ExplainedQuestion('worth-it', $question, 'Con estos datos, no', 'el ahorro no alcanza a pagar la instalación', [
                'Con el consumo, la tarifa y el espacio que indicaste, el ahorro estimado no alcanza a pagar lo que cuesta instalar los paneles.',
                'Revisa que el consumo y la tarifa de tu recibo estén bien escritos, o prueba con más área disponible.',
            ], ExplainedQuestion::TONE_WARNING);
        }

        $lifetimeSavings = $f->annualSavingsCop * self::PANEL_LIFETIME_YEARS;
        $multiple = $f->installationCostCop > 0 ? $lifetimeSavings / $f->installationCostCop : 0;
        $freeYears = max(0, self::PANEL_LIFETIME_YEARS - $f->paybackYears);

        [$headline, $tone, $verdict] = match (true) {
            $f->paybackYears <= 6 => ['Sí, es una buena inversión', ExplainedQuestion::TONE_GOOD, 'Es un retorno rápido para este tipo de instalación.'],
            $f->paybackYears <= 10 => ['Probablemente sí', ExplainedQuestion::TONE_NEUTRAL, 'Es un retorno moderado: vale la pena, pero compara cotizaciones.'],
            default => ['Hoy no es la mejor inversión', ExplainedQuestion::TONE_WARNING, 'El retorno es largo: conviene reducir consumos grandes o revisar el tamaño del sistema.'],
        };

        return new ExplainedQuestion('worth-it', $question, $headline, 'recuperas tu dinero en '.$this->years($f->paybackYears), [
            "Lo que inviertes se paga solo con el ahorro en tu factura en {$this->years($f->paybackYears)}. {$verdict}",
            'Los paneles duran unos '.self::PANEL_LIFETIME_YEARS." años, así que tendrías cerca de {$this->number($freeYears, 0)} años más de energía casi gratis.",
            $multiple >= 1
                ? 'En toda su vida útil ahorrarías alrededor de '.$this->money($lifetimeSavings).", unas {$this->number($multiple, 1)} veces lo que cuesta instalarlos (sin contar subidas de tarifa ni mantenimiento)."
                : 'En toda su vida útil ahorrarías alrededor de '.$this->money($lifetimeSavings).', menos de lo que cuesta instalarlos.',
        ], $tone);
    }

    private function howMuchDoesItCost(ProjectFigures $f): ExplainedQuestion
    {
        $bill = $f->monthlyBillCop();
        $paragraphs = [];

        if ($bill > 0) {
            $paragraphs[] = 'Es lo que pagas de luz en unos '.$this->number($f->installationCostCop / $bill, 0).' meses (tu factura es de unos '.$this->money($bill).' al mes).';
        }

        $paragraphs[] = "Incluye un sistema de {$f->numberOfPanels} paneles con una potencia de {$this->number($f->installedCapacityKwp, 1)} kWp.";
        $paragraphs[] = 'Es un precio de referencia: el valor final lo define el instalador después de visitar el lugar (tipo de techo, estructura y cableado).';

        return new ExplainedQuestion('cost', '¿Cuánto me cuesta instalarlo?', $this->money($f->installationCostCop), 'inversión estimada', $paragraphs);
    }

    private function howMuchWillISave(ProjectFigures $f): ExplainedQuestion
    {
        $bill = $f->monthlyBillCop();
        $savings = $f->monthlySavingsCop();

        $billSentence = $savings >= $bill
            ? 'Tus paneles producirían tanta energía como la que usas: tu factura de unos '.$this->money($bill).' podría quedar casi en cero, salvo los cargos fijos.'
            : 'Tu factura de luz bajaría de unos '.$this->money($bill).' a unos '.$this->money($bill - $savings).' al mes.';

        return new ExplainedQuestion('savings', '¿Cuánto voy a ahorrar?', $this->money($savings), 'de ahorro al mes', [
            $billSentence,
            'En un año son unos '.$this->money($f->annualSavingsCop).'.',
        ], $savings > 0 ? ExplainedQuestion::TONE_GOOD : ExplainedQuestion::TONE_WARNING);
    }

    private function whenDoIGetMyMoneyBack(ProjectFigures $f): ExplainedQuestion
    {
        $question = '¿En cuánto tiempo recupero mi dinero?';

        if ($f->paybackYears === null) {
            return new ExplainedQuestion('payback', $question, 'No se recupera', 'con los datos actuales', [
                'El ahorro estimado es demasiado bajo para pagar la instalación.',
            ], ExplainedQuestion::TONE_WARNING);
        }

        return new ExplainedQuestion('payback', $question, $this->years($f->paybackYears), 'para recuperar lo invertido', [
            'Es el tiempo que tarda el ahorro en tu factura en pagar lo que costó la instalación.',
            'Desde ese momento, todo lo que ahorras es ganancia: los paneles suelen durar '.self::PANEL_LIFETIME_YEARS.' años o más.',
            'No contamos posibles subidas de la tarifa de luz; si sube, recuperas tu dinero más rápido.',
        ], $f->paybackYears <= 10 ? ExplainedQuestion::TONE_GOOD : ExplainedQuestion::TONE_WARNING);
    }

    private function howMuchDoesTheSunPay(ProjectFigures $f): ExplainedQuestion
    {
        $coverage = max(0, $f->coveragePercentage);
        $pesosOfTen = (int) min(10, round($coverage / 10));

        $paragraphs = ["De cada \$10 que pagas hoy de luz, el sol pagaría \${$pesosOfTen}."];
        $paragraphs[] = $coverage >= 100
            ? 'Tus paneles producirían más energía de la que usas. En Colombia la regulación permite entregar ese sobrante a la red, y la empresa de energía lo reconoce en tu factura (normalmente a un valor menor).'
            : 'El resto lo sigues recibiendo de la red eléctrica, como hoy.';

        return new ExplainedQuestion('coverage', '¿Qué parte de mi luz pagaría el sol?', $this->number(min($coverage, 999), 0).' %', 'de tu consumo cubierto por el sol', $paragraphs,
            $coverage >= 70 ? ExplainedQuestion::TONE_GOOD : ExplainedQuestion::TONE_NEUTRAL);
    }

    private function generationVersusConsumption(ProjectFigures $f): ExplainedQuestion
    {
        $paragraphs = [
            'Generación es la energía que producirían tus paneles. Consumo es la que gasta tu casa o negocio.',
            'En la gráfica, cuando la generación de un mes es más alta que el consumo, ese mes el sol cubre toda tu luz. Cuando es más baja, la diferencia la pone la red eléctrica.',
        ];

        // Months are compared per day: a month with only a few measured days would otherwise look
        // like it produces almost nothing. Months with too few days are left out.
        $months = array_values(array_filter($f->months, fn (array $month) => $month['days'] >= self::MIN_DAYS_TO_COMPARE_A_MONTH));

        if ($months !== []) {
            $dailyGeneration = fn (array $month): float => $month['generation'] / $month['days'];
            $coverageOf = fn (array $month): float => $month['consumption'] > 0 ? $dailyGeneration($month) * 30 / $month['consumption'] : INF;
            $best = array_reduce($months, fn (?array $carry, array $month) => $carry === null || $dailyGeneration($month) > $dailyGeneration($carry) ? $month : $carry);
            $worst = array_reduce($months, fn (?array $carry, array $month) => $carry === null || $coverageOf($month) < $coverageOf($carry) ? $month : $carry);

            $sentence = 'El mes con más sol es '.$this->monthName($best['name']).' (unos '.$this->number($dailyGeneration($best), 0).' kWh al día).';

            if ($worst['name'] !== $best['name'] && $worst['consumption'] > 0) {
                $sentence .= ' El más flojo es '.$this->monthName($worst['name']).', cuando el sol cubriría cerca del '.$this->number(min($coverageOf($worst) * 100, 999), 0).' % de lo que consumes.';
            }

            $paragraphs[] = $sentence;
        }

        return new ExplainedQuestion(
            'generation-vs-consumption',
            '¿Qué significa "generación vs. consumo"?',
            $this->number($f->monthlyGenerationKwh, 0).' kWh vs. '.$this->number($f->monthlyConsumptionKwh, 0).' kWh',
            'lo que producen tus paneles vs. lo que gastas, en un mes promedio',
            $paragraphs,
        );
    }

    private function howManyPanels(ProjectFigures $f): ExplainedQuestion
    {
        $question = '¿Cuántos paneles necesito y cuánto espacio ocupan?';

        if ($f->numberOfPanels === 0) {
            return new ExplainedQuestion('panels', $question, 'Ninguno cabe', 'con el área que indicaste', [
                'El área disponible es menor que el tamaño de un panel. Revisa los metros cuadrados en "Editar datos".',
            ], ExplainedQuestion::TONE_WARNING);
        }

        return new ExplainedQuestion('panels', $question, "{$f->numberOfPanels} paneles", 'para cubrir tu consumo con el espacio disponible', [
            'Cada panel mide unos '.$this->number($f->panelAreaM2, 1).' m², un poco más que una puerta. En total ocuparían unos '.$this->number($f->numberOfPanels * $f->panelAreaM2, 0).' m² de techo o terreno.',
            'Juntos suman '.$this->number($f->installedCapacityKwp, 1).' kWp: la "p" es de "pico", la potencia máxima que dan los paneles a pleno sol del mediodía.',
        ]);
    }

    private function nightAndCloudyDays(): ExplainedQuestion
    {
        return new ExplainedQuestion('night', '¿Me quedo sin luz de noche o si está nublado?', 'No te quedas sin luz', 'el sistema va conectado a la red', [
            'De día usas la energía del sol. De noche, o cuando está muy nublado, la red eléctrica te da la que falte, como hoy.',
            'Si quieres seguir con luz durante los cortes, se pueden agregar baterías. No están incluidas en esta estimación y aumentan el costo.',
        ]);
    }

    private function howReliable(ProjectFigures $f): ExplainedQuestion
    {
        $sourceSentence = match ($f->climateSourceKey) {
            'ambient' => 'Usamos la estación meteorológica de Riohacha, que mide el sol cada 5 minutos. Es la fuente más precisa para tu zona.',
            'local' => 'Usamos la estación meteorológica de Maicao, que mide la radiación en la región.',
            'nasa_power' => 'Usamos datos satelitales de la NASA. Los últimos días se estiman hasta que la NASA los publica, y luego se confirman solos.',
            default => 'Usamos los datos de sol disponibles para tu zona.',
        };

        $paragraphs = [$sourceSentence];

        if ($f->staleReasons !== []) {
            $paragraphs[] = 'Ojo: desde el último cálculo cambió algo ('.mb_strtolower(rtrim(implode(' ', $f->staleReasons), '.')).'). Pulsa "Recalcular" para tener los números al día.';
        }

        $paragraphs[] = 'Es una estimación para ayudarte a decidir. Antes de comprar, un instalador debe visitar el lugar y confirmar el diseño y el precio.';

        return new ExplainedQuestion('reliability', '¿Qué tan confiables son estos datos?', $f->climateSourceLabel ?? 'Datos de tu zona', 'fuente usada para calcular el sol', $paragraphs,
            $f->staleReasons === [] ? ExplainedQuestion::TONE_GOOD : ExplainedQuestion::TONE_WARNING);
    }

    private function whatIsAKwh(ProjectFigures $f): ExplainedQuestion
    {
        $paragraphs = [
            'El kWh (kilovatio-hora) es la unidad en la que te cobran la luz. Un aire acondicionado de 12.000 BTU gasta cerca de 1 kWh por cada hora encendido; un bombillo LED necesita unas 110 horas para gastar 1 kWh.',
            'Tu proyecto consume unos '.$this->number($f->monthlyConsumptionKwh, 0).' kWh al mes; a '.$this->money($f->energyRateCopKwh).' cada uno, son unos '.$this->money($f->monthlyBillCop()).' de luz.',
        ];

        if ($f->biggestApplianceLabel !== null && $f->biggestApplianceKwh !== null && $f->monthlyConsumptionKwh > 0) {
            $paragraphs[] = "Lo que más consume en tu lista es: {$f->biggestApplianceLabel}, con ".$this->number($f->biggestApplianceKwh, 0).' kWh al mes ('.$this->number($f->biggestApplianceKwh / $f->monthlyConsumptionKwh * 100, 0).' % del total).';
        }

        return new ExplainedQuestion('kwh', '¿Qué es un kWh?', '1 kWh ≈ '.$this->money($f->energyRateCopKwh), 'lo que pagas por cada kilovatio-hora', $paragraphs);
    }

    // ── Formatting (Colombian conventions) ────────────────────────────────

    private function money(float $cop): string
    {
        if (abs($cop) >= 1_000_000) {
            return '$'.$this->number($cop / 1_000_000, 1).' millones';
        }

        return '$'.number_format(round($cop, abs($cop) >= 10_000 ? -3 : 0), 0, ',', '.');
    }

    private function number(float $value, int $decimals): string
    {
        $formatted = number_format($value, $decimals, ',', '.');

        return $decimals > 0 ? rtrim(rtrim($formatted, '0'), ',') : $formatted;
    }

    private function years(float $years): string
    {
        if ($years < 1) {
            $months = max(1, (int) round($years * 12));

            return $months === 1 ? '1 mes' : "{$months} meses";
        }

        $whole = (int) floor($years);
        $months = (int) round(($years - $whole) * 12);

        if ($months === 12) {
            $whole++;
            $months = 0;
        }

        $text = $whole === 1 ? '1 año' : "{$whole} años";

        return $months === 0 ? $text : $text.' y '.($months === 1 ? '1 mes' : "{$months} meses");
    }

    private function monthName(string $name): string
    {
        return mb_convert_case($name, MB_CASE_TITLE, 'UTF-8');
    }
}
