<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="solar-shell-body min-h-dvh antialiased">
        <flux:sidebar sticky collapsible persist class="solar-shell-sidebar">
            <flux:sidebar.header class="solar-sidebar-header">
                <x-app-logo :sidebar="true" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                @php($projectsLabel = auth()->user()->isAdmin() ? __('Todos los proyectos') : __('Mis proyectos'))

                <div class="solar-nav-label">{{ __('Centro solar') }}</div>
                <flux:sidebar.item class="solar-nav-item" icon="sun" :href="route('solar-projects.index')" :current="request()->routeIs('solar-projects.*') && ! request()->routeIs('solar-projects.create')" :tooltip="$projectsLabel" wire:navigate>
                    {{ $projectsLabel }}
                </flux:sidebar.item>
                <flux:sidebar.item class="solar-nav-item" icon="plus-circle" :href="route('solar-projects.create')" :current="request()->routeIs('solar-projects.create')" :tooltip="__('Nuevo proyecto')" wire:navigate>
                    {{ __('Nuevo proyecto') }}
                </flux:sidebar.item>

                <div class="solar-nav-label solar-nav-label--group">{{ __('Recursos') }}</div>
                <flux:sidebar.item class="solar-nav-item" icon="document-text" :href="route('guides.energy-bill')" :current="request()->routeIs('guides.energy-bill')" :tooltip="__('Guía del recibo')" wire:navigate>
                    {{ __('Guía del recibo') }}
                </flux:sidebar.item>
                <flux:sidebar.item class="solar-nav-item" icon="wrench-screwdriver" :href="route('installers.index')" :current="request()->routeIs('installers.*')" :tooltip="__('Instaladores')" badge="Pronto" wire:navigate>
                    {{ __('Instaladores') }}
                </flux:sidebar.item>

                @can('administer-platform')
                    <div class="solar-nav-label solar-nav-label--group">{{ __('Administración') }}</div>
                    {{-- The red badge says how many climate sources, or the scheduler, are not up to date (ADR-0016). --}}
                    @php($syncProblems = app(\App\Actions\Climate\DescribeSyncHealth::class)()['problems'])
                    <flux:sidebar.item class="solar-nav-item" icon="table-cells" :href="route('api-data.index')" :current="request()->routeIs('api-data.*')" :tooltip="__('Datos climáticos')" :badge="$syncProblems > 0 ? (string) $syncProblems : null" badge:color="red" wire:navigate>
                        {{ __('Datos climáticos') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item class="solar-nav-item" icon="adjustments-horizontal" :href="route('reference-values.index')" :current="request()->routeIs('reference-values.*')" :tooltip="__('Valores de referencia')" wire:navigate>
                        {{ __('Valores de referencia') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item class="solar-nav-item" icon="bolt" :href="route('appliance-catalog.index')" :current="request()->routeIs('appliance-catalog.*')" :tooltip="__('Catálogo de equipos')" wire:navigate>
                        {{ __('Catálogo de equipos') }}
                    </flux:sidebar.item>
                @endcan
            </flux:sidebar.nav>

            <flux:spacer />

            {{-- Visible collapse control (the logo also toggles, but nobody discovers that). Flux persists the state. --}}
            <flux:sidebar.item
                as="button"
                type="button"
                class="solar-nav-item solar-sidebar-collapse"
                icon="chevron-double-left"
                :tooltip="__('Expandir menú')"
                x-on:click="$dispatch('flux-sidebar-toggle')"
                data-test="sidebar-collapse-button"
            >
                {{ __('Recoger menú') }}
            </flux:sidebar.item>

            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="solar-shell-header lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar
                                    :name="auth()->user()->name"
                                    :initials="auth()->user()->initials()"
                                />

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                    <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                            {{ __('Settings') }}
                        </flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer"
                            data-test="logout-button"
                        >
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        {{-- Success messages are flashes: app.js shows them as a toast for a moment (not inline alerts).
             Only on app screens: the settings pages use "status" for internal keys (verification-link-sent…). --}}
        @if (session('status') && request()->routeIs('solar-projects.*', 'api-data.*'))
            <div hidden data-flash-toast data-variant="success">{{ session('status') }}</div>
        @endif

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
