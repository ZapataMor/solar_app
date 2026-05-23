<x-layouts::app.sidebar :title="$title ?? null">
    <flux:main class="solar-shell-main">
        <div class="solar-main-shell">
            {{ $slot }}
        </div>
    </flux:main>
</x-layouts::app.sidebar>
