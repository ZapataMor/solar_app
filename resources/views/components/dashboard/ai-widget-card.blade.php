@props([
    'title',
    'icon' => 'lightbulb',
    'state' => 'warning',
    'priority' => 'media',
    'summary' => null,
    'helper' => null,
    'items' => [],
    'metrics' => [],
    'empty' => 'Sin novedades.',
])

@php
    $stateKey = strtolower((string) $state);

    $cardClasses = match ($stateKey) {
        'success' => 'border-emerald-200/80 bg-gradient-to-br from-white via-emerald-50/40 to-emerald-100/50 dark:border-emerald-900/60 dark:from-zinc-900 dark:via-emerald-950/20 dark:to-zinc-900',
        'danger' => 'border-red-200/80 bg-gradient-to-br from-white via-red-50/30 to-red-100/50 dark:border-red-900/60 dark:from-zinc-900 dark:via-red-950/20 dark:to-zinc-900',
        default => 'border-amber-200/80 bg-gradient-to-br from-white via-amber-50/30 to-amber-100/50 dark:border-amber-900/60 dark:from-zinc-900 dark:via-amber-950/20 dark:to-zinc-900',
    };

    $metricClasses = match ($stateKey) {
        'success' => 'bg-emerald-50/80 dark:bg-emerald-950/30',
        'danger' => 'bg-red-50/80 dark:bg-red-950/30',
        default => 'bg-amber-50/80 dark:bg-amber-950/30',
    };
@endphp

<article {{ $attributes->class("flex h-full min-w-0 flex-col rounded-2xl border p-4 shadow-sm sm:p-5 {$cardClasses}") }}>
    <div class="flex items-start justify-between gap-3">
        <div class="flex min-w-0 items-center gap-3">
            <x-dashboard.ai-icon :name="$icon" :state="$state" />
            <div class="min-w-0">
                <h3 class="text-sm font-semibold text-zinc-950 dark:text-zinc-50">{{ $title }}</h3>
                @if ($helper)
                    <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ $helper }}</p>
                @endif
            </div>
        </div>

        <div class="flex flex-wrap justify-end gap-2">
            <x-dashboard.ai-state-badge :state="$state" />
            <x-dashboard.ai-priority-badge :priority="$priority" />
        </div>
    </div>

    @if ($summary)
        <p class="mt-4 text-sm leading-6 text-zinc-700 dark:text-zinc-200">{{ $summary }}</p>
    @endif

    @if (filled($metrics))
        <dl class="mt-4 grid gap-3 sm:grid-cols-3">
            @foreach ($metrics as $metric)
                <div class="rounded-xl p-3 {{ $metricClasses }}">
                    <dt class="text-[11px] font-medium uppercase tracking-[0.16em] text-zinc-500 dark:text-zinc-400">{{ $metric['label'] }}</dt>
                    <dd class="mt-1 text-sm font-semibold text-zinc-950 dark:text-zinc-50">{{ $metric['value'] }}</dd>
                </div>
            @endforeach
        </dl>
    @endif

    <div class="mt-4 space-y-3">
        @forelse ($items as $item)
            <div class="rounded-xl border border-white/70 bg-white/80 p-3 backdrop-blur dark:border-zinc-800 dark:bg-zinc-950/60">
                <div class="flex items-start gap-3">
                    <x-dashboard.ai-icon :name="$item['icon'] ?? 'lightbulb'" :state="$item['state'] ?? $state" class="h-9 w-9 rounded-xl" />
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <x-dashboard.ai-priority-badge :priority="$item['priority'] ?? 'media'" />
                            <x-dashboard.ai-state-badge :state="$item['state'] ?? $state" />
                        </div>
                        <p class="mt-2 text-sm leading-6 text-zinc-700 dark:text-zinc-200">{{ $item['message'] }}</p>
                    </div>
                </div>
            </div>
        @empty
            <div class="rounded-xl border border-dashed border-zinc-300 bg-white/70 p-4 text-sm text-zinc-500 dark:border-zinc-700 dark:bg-zinc-950/40 dark:text-zinc-400">
                {{ $empty }}
            </div>
        @endforelse
    </div>
</article>
