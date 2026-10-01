<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head', ['title' => 'Calcula tu sistema solar en La Guajira'])
        <meta name="description" content="Natal-IA estima cuánto cuesta un sistema de paneles solares para tu casa o negocio en La Guajira y en cuánto tiempo recuperas la inversión, con datos climáticos reales de tu municipio.">
    </head>
    <body class="landing">
        <a class="landing-skip" href="#contenido">Saltar al contenido</a>

        <header class="landing-nav">
            <a href="{{ route('home') }}" class="landing-nav__brand" aria-label="Natal-IA, inicio">
                <img src="{{ asset('images/natalia-logo.png') }}" alt="Natal-IA">
            </a>

            <nav class="landing-nav__links" aria-label="Secciones">
                <a href="#como-funciona">Cómo funciona</a>
                <a href="#datos">Datos reales</a>
                <a href="#instaladores">Instaladores</a>
                <a href="#preguntas">Preguntas</a>
            </nav>

            <div class="landing-nav__actions">
                <a href="{{ route('login') }}" class="landing-link">Iniciar sesión</a>
                @if (Route::has('register'))
                    <a href="{{ route('register') }}" class="landing-btn landing-btn--primary landing-btn--sm">Crear cuenta</a>
                @endif
            </div>
        </header>

        <main id="contenido">
            {{-- Hero --}}
            <section class="landing-hero" style="--landing-hero-image: url('{{ asset('images/login/fondo3.jpg') }}')">
                <div class="landing-hero__inner">
                    <p class="landing-eyebrow">Energía solar · La Guajira</p>
                    <h1>Sabe cuánto cuesta tu sistema solar <em>antes</em> de pedir una cotización.</h1>
                    <p class="landing-lead">
                        A unos les dicen que vale 100 y a otros que vale 500. Natal-IA estima el sistema que
                        necesita tu casa o tu negocio, cuánto cuesta y en cuántos años recuperas la inversión,
                        con datos climáticos reales de tu municipio.
                    </p>

                    <div class="landing-cta">
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="landing-btn landing-btn--primary">Crea tu cuenta y empieza tu proyecto</a>
                        @endif
                        <a href="#como-funciona" class="landing-btn landing-btn--ghost">Ver cómo funciona</a>
                    </div>

                    <ul class="landing-hero__facts" aria-label="En resumen">
                        <li><strong>3</strong> fuentes de datos climáticos</li>
                        <li><strong>{{ $municipalities->count() ?: 15 }}</strong> municipios con precio local</li>
                        <li><strong>Gratis</strong> estimar tu proyecto</li>
                    </ul>
                </div>
            </section>

            {{-- Problema --}}
            <section class="landing-section" aria-labelledby="problema-titulo">
                <div class="landing-container">
                    <p class="landing-eyebrow">El problema</p>
                    <h2 id="problema-titulo">Tenemos el sol. Lo que falta es información clara.</h2>

                    <div class="landing-grid landing-grid--3">
                        <article class="landing-card">
                            <span class="landing-card__icon" aria-hidden="true">⚡</span>
                            <h3>Tarifas altas</h3>
                            <p>La energía en La Guajira llega cara, mientras recibimos uno de los niveles de radiación solar más altos del país.</p>
                        </article>
                        <article class="landing-card">
                            <span class="landing-card__icon" aria-hidden="true">🔌</span>
                            <h3>Cortes de luz</h3>
                            <p>Cuando se va la luz se daña la comida de la nevera, se para el negocio y se pasa calor. Un sistema solar puede respaldar lo esencial.</p>
                        </article>
                        <article class="landing-card">
                            <span class="landing-card__icon" aria-hidden="true">❓</span>
                            <h3>Precios que nadie explica</h3>
                            <p>Sin saber cuánto consumes ni cuánto debería costar, es fácil pagar de más o quedarse con un sistema que no alcanza.</p>
                        </article>
                    </div>
                </div>
            </section>

            {{-- Cómo funciona --}}
            <section id="como-funciona" class="landing-section landing-section--alt" aria-labelledby="como-titulo">
                <div class="landing-container">
                    <p class="landing-eyebrow">Cómo funciona</p>
                    <h2 id="como-titulo">De tu recibo de luz a un plan solar, en cuatro pasos.</h2>

                    <ol class="landing-steps">
                        <li>
                            <span class="landing-steps__n">1</span>
                            <h3>Crea tu cuenta</h3>
                            <p>Es gratis y solo necesitas un correo.</p>
                        </li>
                        <li>
                            <span class="landing-steps__n">2</span>
                            <h3>Cuéntanos de tu lugar</h3>
                            <p>Elige tu municipio en el mapa e ingresa tu consumo y tu tarifa. Te mostramos dónde encontrarlos en el recibo.</p>
                        </li>
                        <li>
                            <span class="landing-steps__n">3</span>
                            <h3>Calculamos con datos reales</h3>
                            <p>Cruzamos tu información con la radiación solar medida en la región, día por día.</p>
                        </li>
                        <li>
                            <span class="landing-steps__n">4</span>
                            <h3>Recibe tu estimación</h3>
                            <p>Ves el sistema que necesitas, cuánto cuesta y cuándo se paga solo.</p>
                        </li>
                    </ol>
                </div>
            </section>

            {{-- Qué obtienes --}}
            <section class="landing-section" aria-labelledby="resultado-titulo">
                <div class="landing-container landing-split">
                    <div>
                        <p class="landing-eyebrow">Lo que obtienes</p>
                        <h2 id="resultado-titulo">Números claros para decidir con calma.</h2>
                        <p class="landing-muted">
                            Llegas a hablar con un instalador sabiendo qué necesitas y cuánto debería costar.
                            Y puedes volver a calcular cuando quieras con datos más recientes.
                        </p>
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="landing-btn landing-btn--primary">Empezar mi proyecto</a>
                        @endif
                    </div>

                    <dl class="landing-metrics">
                        <div><dt>Capacidad instalada</dt><dd>kWp que necesitas</dd></div>
                        <div><dt>Generación mensual</dt><dd>kWh que producirían tus paneles</dd></div>
                        <div><dt>Cobertura</dt><dd>% de tu consumo cubierto por el sol</dd></div>
                        <div><dt>Inversión estimada</dt><dd>con precios de tu municipio</dd></div>
                        <div><dt>Ahorro mensual</dt><dd>en pesos, según tu tarifa</dd></div>
                        <div><dt>Retorno de la inversión</dt><dd>en cuántos años se paga</dd></div>
                    </dl>
                </div>
            </section>

            {{-- Datos reales --}}
            <section id="datos" class="landing-section landing-section--alt" aria-labelledby="datos-titulo">
                <div class="landing-container">
                    <p class="landing-eyebrow">Datos reales, no promedios genéricos</p>
                    <h2 id="datos-titulo">Medimos el sol de La Guajira.</h2>
                    <p class="landing-muted landing-narrow">
                        Si una fuente no tiene datos recientes, usamos la siguiente. Siempre ves qué fuente se usó en tu cálculo.
                    </p>

                    <div class="landing-grid landing-grid--3">
                        <article class="landing-card">
                            <p class="landing-tag">Riohacha</p>
                            <h3>Estación meteorológica universitaria</h3>
                            <p>Mide irradiancia solar por metro cuadrado, temperatura, humedad y viento en tiempo real.</p>
                        </article>
                        <article class="landing-card">
                            <p class="landing-tag">Maicao</p>
                            <h3>Estación propia</h3>
                            <p>Desarrollada por el equipo. Registra radiación UV, UVA y UVB, temperatura y partículas en el aire.</p>
                        </article>
                        <article class="landing-card">
                            <p class="landing-tag">Satelital</p>
                            <h3>NASA POWER</h3>
                            <p>Históricos satelitales de radiación y clima para toda la región. Es el respaldo cuando no hay datos locales.</p>
                        </article>
                    </div>

                    @if ($municipalities->isNotEmpty())
                        <div class="landing-coverage">
                            <h3>Precios de instalación para cada municipio</h3>
                            <ul>
                                @foreach ($municipalities as $municipality)
                                    <li>{{ $municipality }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            </section>

            {{-- Proveedores (próximamente) --}}
            <section class="landing-section" aria-labelledby="proveedores-titulo">
                <div class="landing-container">
                    <div class="landing-soon">
                        <span class="landing-badge">Próximamente</span>
                        <h2 id="proveedores-titulo">Tu cotización, directo con instaladores de tu zona.</h2>
                        <p>
                            Muy pronto, con tu estimación lista podrás pedir cotización a instaladores aliados que cubren
                            tu municipio. Crea tu cuenta hoy: tu proyecto quedará guardado para cuando abramos la red.
                        </p>
                    </div>
                </div>
            </section>

            {{-- Instaladores --}}
            <section id="instaladores" class="landing-section landing-section--alt" aria-labelledby="instaladores-titulo">
                <div class="landing-container landing-split">
                    <div>
                        <p class="landing-eyebrow">¿Instalas paneles solares?</p>
                        <h2 id="instaladores-titulo">Clientes que ya saben lo que necesitan.</h2>
                        <p class="landing-muted">
                            Estamos armando la red de instaladores aliados de La Guajira. Los clientes llegan con su
                            consumo, su ubicación y un presupuesto de referencia, así dedicas menos tiempo a explicar
                            y más a instalar.
                        </p>
                    </div>
                    <ul class="landing-checklist">
                        <li>Solicitudes de clientes de los municipios que cubres</li>
                        <li>Proyectos con consumo, área disponible y ubicación ya definidos</li>
                        <li>Una herramienta para mostrarle al cliente generación y retorno</li>
                        <li>
                            @if (Route::has('register'))
                                <a href="{{ route('register') }}">Crea tu cuenta</a> para conocer la plataforma antes del lanzamiento.
                            @endif
                        </li>
                    </ul>
                </div>
            </section>

            {{-- Preguntas --}}
            <section id="preguntas" class="landing-section" aria-labelledby="preguntas-titulo">
                <div class="landing-container landing-narrow">
                    <p class="landing-eyebrow">Preguntas frecuentes</p>
                    <h2 id="preguntas-titulo">Antes de empezar</h2>

                    <div class="landing-faq">
                        <details>
                            <summary>¿La estimación es una cotización final?</summary>
                            <p>No. Es una estimación preliminar con precios de referencia de tu municipio. El valor final depende de la visita técnica: tipo de techo, estructura, baterías, protecciones eléctricas y condiciones del sitio.</p>
                        </details>
                        <details>
                            <summary>¿Necesito saber de electricidad?</summary>
                            <p>No. Te pedimos datos de tu recibo de luz y te mostramos con una guía dónde encontrar cada uno.</p>
                        </details>
                        <details>
                            <summary>¿Sirve para negocios?</summary>
                            <p>Sí. Tiendas, restaurantes y empresas con refrigeración o aire acondicionado son los que más pueden ahorrar.</p>
                        </details>
                        <details>
                            <summary>¿Funciona fuera de Riohacha?</summary>
                            <p>Sí, en todos los municipios de La Guajira. El precio de instalación se ajusta a cada uno.</p>
                        </details>
                    </div>
                </div>
            </section>

            {{-- CTA final --}}
            <section class="landing-final" aria-labelledby="final-titulo">
                <div class="landing-container">
                    <h2 id="final-titulo">El sol ya está. Empieza tu proyecto.</h2>
                    <p>Crea tu cuenta gratis y descubre cuánto puedes ahorrar.</p>
                    <div class="landing-cta landing-cta--center">
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="landing-btn landing-btn--primary">Crear mi cuenta</a>
                        @endif
                        <a href="{{ route('login') }}" class="landing-btn landing-btn--ghost">Ya tengo cuenta</a>
                    </div>
                </div>
            </section>
        </main>

        <footer class="landing-footer">
            <div class="landing-container landing-footer__inner">
                <img src="{{ asset('images/natalia-logo.png') }}" alt="Natal-IA" class="landing-footer__logo">
                <p>Energía solar con datos del territorio · Riohacha, La Guajira</p>
                <p>&copy; {{ now()->year }} Natal-IA</p>
            </div>
        </footer>
    </body>
</html>
