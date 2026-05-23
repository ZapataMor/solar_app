@props([
    'state' => 'warning',
])

@php
    $stateKey = strtolower((string) $state);

    $classes = match ($stateKey) {
        'success' => 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900/60 dark:bg-emerald-950/40 dark:text-emerald-200',
        'danger' => 'border-red-200 bg-red-50 text-red-700 dark:border-red-900/60 dark:bg-red-950/40 dark:text-red-200',
        default => 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-200',
    };

    $label = match ($stateKey) {
        'success' => 'Success',
        'danger' => 'Danger',
        default => 'Warning',
    };
@endphp

<span {{ $attributes->class("inline-flex items-center rounded-full border px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] {$classes}") }}>
    {{ $label }}
</span>
