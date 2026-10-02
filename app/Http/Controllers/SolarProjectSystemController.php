<?php

namespace App\Http\Controllers;

use App\Actions\SolarProjects\CheckCalculationFreshness;
use App\Actions\SolarProjects\DescribeProjectSystem;
use App\Actions\SolarProjects\ExplainSolarProject;
use App\Models\SolarProject;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Alternative panel "Mi sistema" (ADR-0014): the project told in money and plain words, next to
 * the current panel so both can be compared before choosing one.
 */
class SolarProjectSystemController extends Controller
{
    public function show(
        Request $request,
        SolarProject $solarProject,
        DescribeProjectSystem $describeProjectSystem,
        CheckCalculationFreshness $checkFreshness,
        ExplainSolarProject $explainSolarProject,
    ): View {
        abort_unless($request->user()->can('manage', $solarProject), 403);

        $calculationFreshness = $checkFreshness($solarProject);

        return view('solar-projects.system', [
            'solarProject' => $solarProject,
            'system' => $describeProjectSystem($solarProject),
            'calculationFreshness' => $calculationFreshness,
            'projectQuestions' => $explainSolarProject($solarProject, $calculationFreshness),
            // Projects open here from the portfolio: going back keeps its search and page.
            'portfolioUrl' => $this->portfolioUrl($request),
        ]);
    }
}
