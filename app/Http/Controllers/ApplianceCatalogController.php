<?php

namespace App\Http\Controllers;

use App\Actions\Catalog\DescribeApplianceCatalog;
use App\Actions\Catalog\SaveCatalogAppliance;
use App\Http\Requests\CatalogApplianceRequest;
use App\Models\CatalogAppliance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Administration: the appliance catalog as a reference of consumption, and the appliances
 * administrators add to it (ADR-0017). The built-in ones are read-only.
 */
class ApplianceCatalogController extends Controller
{
    public function index(Request $request, DescribeApplianceCatalog $describeApplianceCatalog): View
    {
        $sort = array_key_exists($request->query('orden'), DescribeApplianceCatalog::SORTS) ? $request->query('orden') : 'consumo';
        $segment = array_key_exists($request->query('para'), DescribeApplianceCatalog::SEGMENTS) ? $request->query('para') : 'todos';

        return view('appliance-catalog.index', [
            'appliances' => $describeApplianceCatalog($sort, $segment),
            'sort' => $sort,
            'segment' => $segment,
        ]);
    }

    public function create(): View
    {
        return view('appliance-catalog.form', ['appliance' => null]);
    }

    public function store(CatalogApplianceRequest $request, SaveCatalogAppliance $saveCatalogAppliance): RedirectResponse
    {
        $appliance = $saveCatalogAppliance($request->validated(), null, $request->user());

        return redirect()
            ->route('appliance-catalog.index', ['orden' => 'nombre'])
            ->with('status', "Se agregó {$appliance->label} al catálogo: ya aparece en el diario de consumo.");
    }

    public function edit(CatalogAppliance $catalogAppliance): View
    {
        return view('appliance-catalog.form', ['appliance' => $catalogAppliance]);
    }

    public function update(CatalogApplianceRequest $request, CatalogAppliance $catalogAppliance, SaveCatalogAppliance $saveCatalogAppliance): RedirectResponse
    {
        $appliance = $saveCatalogAppliance($request->validated(), $catalogAppliance, $request->user());

        return redirect()
            ->route('appliance-catalog.index')
            ->with('status', $appliance->active
                ? "Se actualizó {$appliance->label}; los proyectos que lo usan se marcarán para recalcular."
                : "{$appliance->label} quedó oculto: ya no se ofrece en el diario, pero los proyectos que lo usan lo conservan.");
    }
}
