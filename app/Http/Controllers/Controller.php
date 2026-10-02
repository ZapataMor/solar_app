<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * Portfolio URL that keeps the search and page the user came from (the project pages' back link).
     */
    protected function portfolioUrl(Request $request): string
    {
        return route('solar-projects.index', array_filter([
            'search' => trim((string) $request->query('search', '')),
            'page' => $request->query('page'),
        ], fn ($value) => filled($value)));
    }
}
