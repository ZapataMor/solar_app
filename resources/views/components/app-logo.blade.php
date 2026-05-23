@props([
    'sidebar' => false,
])

@if($sidebar)
    <flux:sidebar.brand name="Solar IA Riohacha" class="solar-sidebar-brand" {{ $attributes }}>
        <x-slot name="logo" class="solar-brand-mark flex aspect-square size-10 items-center justify-center rounded-2xl">
            <x-app-logo-icon class="size-5 fill-current text-white" />
        </x-slot>
        <span class="solar-brand-copy">
            <strong>Solar IA Riohacha</strong>
            <span>Dashboard de energia limpia</span>
        </span>
    </flux:sidebar.brand>
@else
    <flux:brand name="Solar IA Riohacha" {{ $attributes }}>
        <x-slot name="logo" class="solar-brand-mark flex aspect-square size-10 items-center justify-center rounded-2xl">
            <x-app-logo-icon class="size-5 fill-current text-white" />
        </x-slot>
        <span class="solar-brand-copy">
            <strong>Solar IA Riohacha</strong>
            <span>Centro de control solar</span>
        </span>
    </flux:brand>
@endif
