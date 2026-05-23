<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen overflow-hidden bg-[#2a1a10] font-sans text-[#F7EFE4] antialiased max-[980px]:overflow-auto">
        <div class="login-bg" aria-hidden="true"></div>
        <div class="login-sunglow" aria-hidden="true"></div>

        <div class="login-page">
            <header class="login-topbar">
                <a href="{{ route('home') }}" class="login-brand" wire:navigate>
                    <span class="login-brand-mark" aria-hidden="true">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#F7EFE4" stroke-width="1.8" stroke-linecap="round">
                            <circle cx="12" cy="12" r="3.6" fill="#F7EFE4" stroke="none" />
                            <path d="M12 3v2.4M12 18.6V21M3 12h2.4M18.6 12H21M5.6 5.6l1.7 1.7M16.7 16.7l1.7 1.7M5.6 18.4l1.7-1.7M16.7 7.3l1.7-1.7" />
                        </svg>
                    </span>
                    <span class="login-brand-name">
                        Solaria
                        <small>Guajira · Plataforma</small>
                    </span>
                </a>

                <div class="login-topbar-meta" aria-hidden="true">
                    <span><span class="login-dot"></span>Maicao 11.39° N · 72.24° W</span>
                    <span>34°C · Despejado</span>
                    <span>Irradiancia 952 W/m²</span>
                </div>
            </header>

            <main class="login-main">
                <section class="login-hero">
                    <div class="login-eyebrow">Energía · Clima · Territorio</div>
                    <h1>Datos del <em>desierto</em>, decisiones para tu comunidad.</h1>
                    <p>Plataforma de monitoreo solar y meteorológico para Maicao y La Guajira. Genera, mide y comparte el pulso energético de tu territorio.</p>

                    <div class="login-stats">
                        <div class="login-stat">
                            <div class="login-stat-value">187<span>MWh</span></div>
                            <div class="login-stat-label">Generación / mes</div>
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
            </main>

            <footer class="login-footer">
                <div>© 2026 Solaria Guajira · Maicao</div>
                <ul>
                    <li><a href="#">Soporte</a></li>
                    <li><a href="#">Privacidad</a></li>
                    <li><a href="#">v 2.4.1</a></li>
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
