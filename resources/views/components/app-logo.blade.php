@props([
    'sidebar' => false,
])

@if($sidebar)
    <flux:sidebar.brand name="" class="solar-sidebar-brand" {{ $attributes }}>
        <x-slot name="logo" class="solar-brand-mark flex items-center justify-center">
            <img
                src="{{ asset('images/fondoNathalIA.png') }}"
                alt="Natal-IA"
                class="solar-brand-logo"
            />
        </x-slot>
        <span class="sr-only">Logo</span>
    </flux:sidebar.brand>
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
