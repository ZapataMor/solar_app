<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen overflow-hidden bg-[#2a1a10] font-sans text-[#F7EFE4] antialiased max-[980px]:overflow-auto">
        <div class="login-bg" style="background-image: url('{{ asset('images/login/fondo3.jpg') }}');" aria-hidden="true"></div>

        <div class="login-page">
            <header class="login-topbar">
                <a href="{{ route('home') }}" class="login-brand" wire:navigate>
                    <span class="login-brand-mark" aria-hidden="true">
                        <img src="{{ asset('images/logo.png') }}" alt="" />
                    </span>
                    <span class="login-brand-name">
                        Maicao Coders
                        <small>SolarApp Guajira</small>
                    </span>
                </a>

                <div class="login-topbar-meta" aria-hidden="true">
                    <span>Riohacha 11.39&deg; N - 72.24&deg; W</span>
                    <span>34&deg;C</span>
                    <span>Despejado</span>
                    <span>Irradiancia 952 W/m&sup2;</span>
                </div>
            </header>

            <main class="login-main">
                <div class="login-panel">
                    <section class="login-hero">
                        <span class="login-panel-logo" aria-hidden="true">
                            <img src="{{ asset('images/logo.png') }}" alt="" />
                        </span>

                        <div class="login-eyebrow">Energia - Clima - Territorio</div>
                        <h1>Datos del desierto, decisiones para tu comunidad.</h1>
                        <p>Plataforma de monitoreo solar y meteorol&oacute;gico para Riohacha y La Guajira. Genera, mide y comparte el pulso energ&eacute;tico de tu territorio.</p>

                        <div class="login-stats">
                            <div class="login-stat">
                                <div class="login-stat-value">187<span>MWh</span></div>
                                <div class="login-stat-label">Generaci&oacute;n / mes</div>
                            </div>
                            <div class="login-stat">
                                <div class="login-stat-value">42<span>est.</span></div>
                                <div class="login-stat-label">Estaciones activas</div>
                            </div>
                            <div class="login-stat">
                                <div class="login-stat-value">98.7<span>%</span></div>
                                <div class="login-stat-label">Uptime de red</div>
                            </div>
                        </div>
                    </section>

                    <section class="login-card-wrap">
                        {{ $slot }}
                    </section>
                </div>
            </main>

            <footer class="login-footer">
                <div>&copy; 2026 SolarApp Guajira</div>
                <ul>
                    <li>Riohacha</li>
                    <li><a href="#">Soporte</a></li>
                    <li><a href="#">Privacidad</a></li>
                    <li>v 2.4.1</li>
                </ul>
            </footer>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
