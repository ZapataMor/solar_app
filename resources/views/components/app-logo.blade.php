@props([
    'sidebar' => false,
])

@if($sidebar)
    <flux:sidebar.brand name="" class="solar-sidebar-brand" {{ $attributes }}>
        <x-slot name="logo" class="solar-brand-mark flex items-center justify-start">
            <img
                src="{{ asset('images/fondoNathalIA.png') }}"
                alt="Natal-IA"
                class="solar-brand-logo"
                style="width:100%;max-width:100%;height:auto;object-fit:contain;"
            />
        </x-slot>
        <span class="sr-only">Logo</span>
    </flux:sidebar.brand>
@else
    <flux:brand name="" {{ $attributes }}>
        <x-slot name="logo" class="solar-brand-mark flex items-center justify-start">
            <img
                src="{{ asset('images/fondoNathalIA.png') }}"
                alt="Natal-IA"
                class="solar-brand-logo"
                style="width:100%;max-width:100%;height:auto;object-fit:contain;"
            />
        </x-slot>
        <span class="sr-only">Logo</span>
    </flux:brand>
@endif
