@props([
    'sidebar' => false,
])

@if($sidebar)
    {{-- Clicking the logo collapses / expands the sidebar --}}
    <button
        type="button"
        {{ $attributes->class('solar-sidebar-brand') }}
        x-on:click="$dispatch('flux-sidebar-toggle')"
        aria-label="{{ __('Recoger / expandir menú') }}"
        title="{{ __('Recoger / expandir menú') }}"
        data-flux-sidebar-brand
    >
        <span class="solar-brand-mark">
            <img
                src="{{ asset('images/natalia-logo.png') }}"
                alt="Natal-IA"
                class="solar-brand-logo solar-brand-logo-full"
            />
            <img
                src="{{ asset('images/natalia-icon.png') }}"
                alt="Natal-IA"
                class="solar-brand-logo-icon"
            />
        </span>
    </button>
@else
    <flux:brand name="" {{ $attributes }}>
        <x-slot name="logo" class="solar-brand-mark flex items-center justify-center">
            <img
                src="{{ asset('images/fondoNathalIA.png') }}"
                alt="Natal-IA"
                class="solar-brand-logo"
            />
        </x-slot>
        <span class="sr-only">Logo</span>
    </flux:brand>
@endif
