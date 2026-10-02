<?php

namespace App\Http\Controllers;

use App\Actions\ReferenceValues\DescribeReferenceValues;
use App\Actions\ReferenceValues\RecordReferenceValue;
use App\Domain\Reference\ReferenceValueCatalog;
use App\Http\Requests\ReferenceValueRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Administration: general values of the system, such as the kWh tariff (ADR-0015).
 */
class ReferenceValueController extends Controller
{
    public function index(DescribeReferenceValues $describeReferenceValues): View
    {
        return view('reference-values.index', [
            'referenceValues' => $describeReferenceValues(),
        ]);
    }

    public function store(ReferenceValueRequest $request, RecordReferenceValue $recordReferenceValue): RedirectResponse
    {
        $data = $request->validated();
        $validFrom = Carbon::parse($data['valid_from'])->startOfDay();

        $recordReferenceValue(
            $data['key'],
            (float) $data['value'],
            $validFrom->toDateString(),
            $data['source'] ?? null,
            $data['notes'] ?? null,
            $request->user(),
        );

        $label = ReferenceValueCatalog::definition($data['key'])->label;
        $applies = $validFrom->isFuture() ? 'regirá desde el '.$validFrom->locale('es')->isoFormat('D [de] MMMM [de] YYYY') : 'ya rige';

        return redirect()
            ->to(route('reference-values.index').'#valor-'.$data['key'])
            ->with('status', "Se guardó {$label}: {$applies}.");
    }
}
