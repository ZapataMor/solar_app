<?php

namespace App\Http\Controllers;

use App\Models\SolarProject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Free notes of a project (the former description): optional for the client and a place for
 * whoever advises them to write down visits, roof details or agreements (ADR-0013).
 */
class SolarProjectNotesController extends Controller
{
    public function edit(Request $request, SolarProject $solarProject): View
    {
        abort_unless($request->user()->can('manage', $solarProject), 403);

        return view('solar-projects.notes', ['solarProject' => $solarProject]);
    }

    public function update(Request $request, SolarProject $solarProject): RedirectResponse
    {
        abort_unless($request->user()->can('manage', $solarProject), 403);

        $validated = $request->validate([
            'description' => ['nullable', 'string', 'max:5000'],
        ]);

        $solarProject->update(['description' => $validated['description'] ?? null]);

        return redirect()
            ->route('solar-projects.notes', $solarProject)
            ->with('status', 'Notas guardadas.');
    }
}
