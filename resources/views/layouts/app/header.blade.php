<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="solar-shell-body min-h-screen">
        <flux:header container class="solar-shell-header">
            <flux:sidebar.toggle class="lg:hidden mr-2" icon="bars-2" inset="left" />

            <x-app-logo href="{{ route('solar-projects.index') }}" wire:navigate />

            <flux:navbar class="-mb-px max-lg:hidden">
                <flux:navbar.item class="solar-header-item" icon="sun" :href="route('solar-projects.index')" :current="request()->routeIs('solar-projects.*')" wire:navigate>
                    {{ __('Proyectos solares') }}
                </flux:navbar.item>
                <flux:navbar.item class="solar-header-item" icon="table-cells" :href="route('api-data.index')" :current="request()->routeIs('api-data.*')" wire:navigate>
                    {{ __('Datos APIs') }}
                </flux:navbar.item>
            </flux:navbar>

            <flux:spacer />

            <x-desktop-user-menu />
        </flux:header>

        <flux:sidebar collapsible="mobile" sticky class="solar-shell-sidebar lg:hidden">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('solar-projects.index') }}" wire:navigate />
                <flux:sidebar.collapse class="in-data-flux-sidebar-on-desktop:not-in-data-flux-sidebar-collapsed-desktop:-mr-2" />
            </flux:sidebar.header>

            <div class="solar-sidebar-intro">
                <p>Natal-IA</p>
                <p>Núcleo de Análisis Tecnológico para el Aprovechamiento de la Luz Solar mediante IA.</p>
            </div>

            <flux:sidebar.nav>
                <flux:sidebar.group :heading="__('Centro solar')" class="grid solar-nav-heading">
                    <flux:sidebar.item class="solar-nav-item" icon="sun" :href="route('solar-projects.index')" :current="request()->routeIs('solar-projects.*')" wire:navigate>
                        {{ __('Proyectos solares') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item class="solar-nav-item" icon="table-cells" :href="route('api-data.index')" :current="request()->routeIs('api-data.*')" wire:navigate>
                        {{ __('Datos APIs') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>
            </flux:sidebar.nav>

        </flux:sidebar>

        <main class="solar-shell-main">
            <div class="solar-main-shell">
                {{ $slot }}
            </div>
        </main>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
