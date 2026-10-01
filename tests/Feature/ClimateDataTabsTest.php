<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClimateDataTabsTest extends TestCase
{
    use RefreshDatabase;

    public function test_ambient_is_the_default_tab_and_the_others_start_hidden(): void
    {
        $html = $this->page();

        $this->assertStringContainsString('role="tablist"', $html);
        $this->assertSame(['ambient'], $this->visiblePanels($html));
        $this->assertStringContainsString('id="api-tab-ambient"', $html);
    }

    public function test_tab_query_selects_the_source(): void
    {
        $this->assertSame(['nasa'], $this->visiblePanels($this->page(['tab' => 'nasa'])));
        $this->assertSame(['weather-station'], $this->visiblePanels($this->page(['tab' => 'weather-station'])));
    }

    public function test_unknown_tab_falls_back_to_the_paginated_source_or_ambient(): void
    {
        $this->assertSame(['ambient'], $this->visiblePanels($this->page(['tab' => 'otra'])));
        $this->assertSame(['nasa'], $this->visiblePanels($this->page(['nasa_page' => 2])));
        $this->assertSame(['weather-station'], $this->visiblePanels($this->page(['station_page' => 2])));
    }

    public function test_tab_links_keep_the_rest_of_the_query(): void
    {
        $html = $this->page(['nasa_page' => 2]);

        $this->assertStringContainsString(e(url('/api-data').'?nasa_page=2&tab=ambient'), $html);
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function page(array $query = []): string
    {
        return $this->actingAs(User::factory()->admin()->create())
            ->get(route('api-data.index', $query))
            ->assertOk()
            ->getContent();
    }

    /**
     * @return list<string>
     */
    private function visiblePanels(string $html): array
    {
        preg_match_all('/data-api-tab-panel="([a-z-]+)"\s*(hidden)?\s*>/', $html, $matches, PREG_SET_ORDER);

        return array_values(array_map(
            fn (array $match) => $match[1],
            array_filter($matches, fn (array $match) => ($match[2] ?? '') !== 'hidden'),
        ));
    }
}
