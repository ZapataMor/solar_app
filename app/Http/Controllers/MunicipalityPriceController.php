<?php

namespace App\Http\Controllers;

use App\Actions\Pricing\DescribeMunicipalityPrices;
use App\Actions\Pricing\SaveMunicipalityPrice;
use App\Http\Requests\MunicipalityPriceRequest;
use App\Models\Municipality;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Administration: the price per kW the app quotes each municipality with (ADR-0024).
 */
class MunicipalityPriceController extends Controller
{
    public function index(DescribeMunicipalityPrices $describeMunicipalityPrices): View
    {
        return view('municipality-prices.index', $describeMunicipalityPrices());
    }

    public function store(MunicipalityPriceRequest $request, SaveMunicipalityPrice $saveMunicipalityPrice): RedirectResponse
    {
        $data = $request->validated();
        $municipality = Municipality::query()->findOrFail($data['municipality_id']);
        $price = $saveMunicipalityPrice($municipality, $data['location_type'], $data);

        return redirect()
            ->route('municipality-prices.index')
            ->with('status', $price->active
                ? "Se guardó el precio de {$municipality->name}: rige para lo que se cotice desde ahora."
                : "El precio de {$municipality->name} quedó inactivo: ya no se cotiza con él.");
    }
}
