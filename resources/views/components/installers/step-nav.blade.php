{{--
    Moving between the steps of a quote (ADR-0026): real links, so the keyboard and the back button
    keep working and the page still reads top to bottom without JavaScript.
--}}
@props(['steps', 'current'])

@php
    $keys = array_keys($steps);
    $index = array_search($current, $keys, true);
    $previous = $index > 0 ? $keys[$index - 1] : null;
    $next = $index < count($keys) - 1 ? $keys[$index + 1] : null;
@endphp

<nav class="solar-steps__move" aria-label="Paso siguiente y anterior">
    @if ($previous)
        <a href="#paso-{{ $previous }}" class="solar-button-ghost" data-quote-go="{{ $previous }}">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
            {{ $steps[$previous]['label'] }}
        </a>
    @else
        <span></span>
    @endif

    @if ($next)
        <a href="#paso-{{ $next }}" class="solar-button" data-quote-go="{{ $next }}">
            {{ $steps[$next]['label'] }}
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg>
        </a>
    @endif
</nav>
