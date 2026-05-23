@props([
    'name' => 'lightbulb',
    'state' => 'warning',
])

@php
    $stateKey = strtolower((string) $state);

    $toneClasses = match ($stateKey) {
        'success' => 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900/60 dark:bg-emerald-950/40 dark:text-emerald-200',
        'danger' => 'border-red-200 bg-red-50 text-red-700 dark:border-red-900/60 dark:bg-red-950/40 dark:text-red-200',
        default => 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-200',
    };
@endphp

<span {{ $attributes->class("inline-flex h-11 w-11 items-center justify-center rounded-2xl border {$toneClasses}") }}>
    @switch($name)
        @case('bell')
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17H9.143m5.714 0H18l-1.714-2.571V10a4.286 4.286 0 1 0-8.572 0v4.429L6 17h3.143m5.714 0a2.857 2.857 0 1 1-5.714 0" />
            </svg>
            @break
        @case('bolt')
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13 2 5 14h6l-1 8 8-12h-6l1-8Z" />
            </svg>
            @break
        @case('shield-alert')
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 3l7 3v5c0 4.5-3 8.5-7 10-4-1.5-7-5.5-7-10V6l7-3Z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4" />
                <circle cx="12" cy="16" r="1" fill="currentColor" stroke="none" />
            </svg>
            @break
        @case('piggy-bank')
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 12a6 6 0 0 1 6-6h2.5A5.5 5.5 0 0 1 19 11.5V12a3 3 0 0 1 2 2.828V17h-2.25A4.75 4.75 0 0 1 14 20.75H10A5.75 5.75 0 0 1 4.25 15v-1.25H3v-2h2Z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M10 10h2m3.5-.75h.01" />
            </svg>
            @break
        @case('triangle-alert')
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.29 3.86 1.82 18a2 2 0 0 0 1.72 3h16.92a2 2 0 0 0 1.72-3L13.71 3.86a2 2 0 0 0-3.42 0Z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4" />
                <circle cx="12" cy="17" r="1" fill="currentColor" stroke="none" />
            </svg>
            @break
        @case('cloud-alert')
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M7 18a4 4 0 1 1 .84-7.91A5.5 5.5 0 0 1 18 12.5 3.5 3.5 0 1 1 18.5 19H7Z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3" />
                <circle cx="12" cy="15.5" r=".9" fill="currentColor" stroke="none" />
            </svg>
            @break
        @case('sparkles')
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5">
                <path stroke-linecap="round" stroke-linejoin="round" d="m12 3 1.8 4.2L18 9l-4.2 1.8L12 15l-1.8-4.2L6 9l4.2-1.8L12 3Z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="m18 15 .9 2.1L21 18l-2.1.9L18 21l-.9-2.1L15 18l2.1-.9L18 15Z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 14 6 16l2 1-2 1-1 2-1-2-2-1 2-1 1-2Z" />
            </svg>
            @break
        @default
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 18h6m-5 3h4m-6.5-6.5a6.5 6.5 0 1 1 9 0c-.74.72-1.3 1.62-1.63 2.6H9.13c-.33-.98-.89-1.88-1.63-2.6Z" />
            </svg>
    @endswitch
</span>
