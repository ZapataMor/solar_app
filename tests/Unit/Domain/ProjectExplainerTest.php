<?php

namespace Tests\Unit\Domain;

use App\Domain\Explanation\ExplainedQuestion;
use App\Domain\Explanation\ProjectExplainer;
use App\Domain\Explanation\ProjectFigures;
use PHPUnit\Framework\TestCase;

class ProjectExplainerTest extends TestCase
{
    public function test_a_calculated_project_answers_the_ten_questions_in_order(): void
    {
        $keys = array_map(fn (ExplainedQuestion $question) => $question->key, (new ProjectExplainer)->explain($this->figures()));

        $this->assertSame(['worth-it', 'cost', 'savings', 'payback', 'coverage', 'generation-vs-consumption', 'panels', 'night', 'reliability', 'kwh'], $keys);
    }

    public function test_an_uncalculated_project_only_explains_why_there_are_no_answers(): void
    {
        $questions = (new ProjectExplainer)->explain(new ProjectFigures(calculated: false, monthlyConsumptionKwh: 300, energyRateCopKwh: 900));

        $this->assertCount(1, $questions);
        $this->assertSame('not-calculated', $questions[0]->key);
    }

    public function test_without_appliances_it_asks_for_them_before_calculating(): void
    {
        $questions = (new ProjectExplainer)->explain(new ProjectFigures(calculated: false, monthlyConsumptionKwh: 0, energyRateCopKwh: 900));

        $this->assertCount(1, $questions);
        $this->assertSame('no-appliances', $questions[0]->key);
        $this->assertSame('Faltan tus equipos', $questions[0]->headline);
    }

    public function test_answers_use_the_project_numbers_in_plain_language(): void
    {
        $answers = $this->answersByKey($this->figures());

        // Bill 400 kWh × $900 = $360.000; savings 4.200.000 / 12 = $350.000.
        $this->assertSame('$350.000', $answers['savings']->headline);
        $this->assertStringContainsString('bajaría de unos $360.000 a unos $10.000 al mes', $answers['savings']->paragraphs[0]);
        $this->assertSame('$15 millones', $answers['cost']->headline);
        $this->assertSame('3 años y 7 meses', $answers['payback']->headline);
        $this->assertSame('Sí, es una buena inversión', $answers['worth-it']->headline);
        $this->assertSame(ExplainedQuestion::TONE_GOOD, $answers['worth-it']->tone);
        $this->assertStringContainsString('De cada $10 que pagas hoy de luz, el sol pagaría $9', $answers['coverage']->paragraphs[0]);
        $this->assertSame('11 paneles', $answers['panels']->headline);
        $this->assertStringContainsString('El mes con más sol es Marzo', $answers['generation-vs-consumption']->paragraphs[2]);
        $this->assertStringContainsString('El más flojo es Octubre, cuando el sol cubriría cerca del 75 %', $answers['generation-vs-consumption']->paragraphs[2]);
        $this->assertStringNotContainsString('Noviembre', $answers['generation-vs-consumption']->paragraphs[2]);
        $this->assertStringContainsString('Aire acondicionado, con 192 kWh al mes (48 % del total)', $answers['kwh']->paragraphs[2]);
    }

    public function test_long_payback_and_stale_data_are_flagged(): void
    {
        $answers = $this->answersByKey($this->figures(paybackYears: 12.5, staleReasons: ['Llegaron datos nuevos de NASA POWER.']));

        $this->assertSame('Hoy no es la mejor inversión', $answers['worth-it']->headline);
        $this->assertSame(ExplainedQuestion::TONE_WARNING, $answers['worth-it']->tone);
        $this->assertSame('12 años y 6 meses', $answers['payback']->headline);
        $this->assertSame(ExplainedQuestion::TONE_WARNING, $answers['reliability']->tone);
        $this->assertStringContainsString('llegaron datos nuevos de nasa power', $answers['reliability']->paragraphs[1]);
    }

    public function test_surplus_generation_mentions_selling_to_the_grid(): void
    {
        $answers = $this->answersByKey($this->figures(coverage: 130));

        $this->assertSame('130 %', $answers['coverage']->headline);
        $this->assertStringContainsString('el sol pagaría $10', $answers['coverage']->paragraphs[0]);
        $this->assertStringContainsString('entregar ese sobrante a la red', $answers['coverage']->paragraphs[1]);
    }

    /**
     * @param  list<string>  $staleReasons
     */
    private function figures(float $paybackYears = 3.57, float $coverage = 92, array $staleReasons = []): ProjectFigures
    {
        return new ProjectFigures(
            calculated: true,
            monthlyConsumptionKwh: 400,
            energyRateCopKwh: 900,
            installedCapacityKwp: 6.05,
            numberOfPanels: 11,
            panelAreaM2: 2.6,
            monthlyGenerationKwh: 368,
            coveragePercentage: $coverage,
            annualSavingsCop: 4_200_000,
            installationCostCop: 15_000_000,
            paybackYears: $paybackYears,
            months: [
                ['name' => 'marzo', 'days' => 31, 'generation' => 434, 'consumption' => 400],
                ['name' => 'julio', 'days' => 31, 'generation' => 403, 'consumption' => 400],
                ['name' => 'octubre', 'days' => 30, 'generation' => 300, 'consumption' => 400],
                // Only one measured day: must not be named the worst month.
                ['name' => 'noviembre', 'days' => 1, 'generation' => 10, 'consumption' => 400],
            ],
            climateSourceKey: 'ambient',
            climateSourceLabel: 'Ambient Weather',
            staleReasons: $staleReasons,
            biggestApplianceLabel: 'Aire acondicionado',
            biggestApplianceKwh: 192,
        );
    }

    /**
     * @return array<string, ExplainedQuestion>
     */
    private function answersByKey(ProjectFigures $figures): array
    {
        $answers = [];
        foreach ((new ProjectExplainer)->explain($figures) as $question) {
            $answers[$question->key] = $question;
        }

        return $answers;
    }
}
