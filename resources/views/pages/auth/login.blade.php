<x-layouts::auth :title="__('Iniciar sesion')">
    <div class="login-card" role="region" aria-label="Iniciar sesion">
        <div class="login-card-head">
            <div class="login-badge">
                <svg width="10" height="10" viewBox="0 0 10 10" fill="none" aria-hidden="true">
                    <circle cx="5" cy="5" r="4" stroke="#C9823B" stroke-width="1" />
                    <circle cx="5" cy="5" r="1.6" fill="#C9823B" />
                </svg>
                Acceso seguro
            </div>
            <h2>Iniciar sesi&oacute;n</h2>
            <p>Accede a tu panel de monitoreo energ&eacute;tico y meteorol&oacute;gico.</p>
        </div>

        <x-auth-session-status class="mb-4 text-center text-sm text-[#D9A059]" :status="session('status')" />

        <form method="POST" action="{{ route('login.store') }}" class="login-form">
            @csrf

            <div class="login-field">
                <label for="email">Correo electr&oacute;nico</label>
                <div class="login-input @error('email') login-input-error @enderror">
                    <span aria-hidden="true">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="5" width="18" height="14" rx="2.5" />
                            <path d="m4 7 8 6 8-6" />
                        </svg>
                    </span>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="email" placeholder="usuario@dominio.com" />
                </div>
                @error('email')
                    <p class="login-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="login-field" x-data="{ show: false }">
                <label for="password">Contrase&ntilde;a</label>
                <div class="login-input @error('password') login-input-error @enderror">
                    <span aria-hidden="true">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="4" y="11" width="16" height="9" rx="2.2" />
                            <path d="M8 11V7a4 4 0 0 1 8 0v4" />
                        </svg>
                    </span>
                    <input id="password" name="password" x-bind:type="show ? 'text' : 'password'" required autocomplete="current-password" placeholder="Ingresa tu contrase&ntilde;a" />
                    <button type="button" class="login-toggle" x-on:click="show = ! show" aria-label="Mostrar u ocultar contrasena">
                        <svg x-show="! show" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z" />
                            <circle cx="12" cy="12" r="3" />
                        </svg>
                        <svg x-show="show" x-cloak width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 3l18 18" />
                            <path d="M10.6 6.1A9.7 9.7 0 0 1 12 6c6.5 0 10 7 10 7a16.2 16.2 0 0 1-3.2 4.2" />
                            <path d="M6.6 6.6A16.6 16.6 0 0 0 2 12s3.5 7 10 7c1.6 0 3.1-.4 4.4-1" />
                            <path d="M9.9 9.9a3 3 0 0 0 4.2 4.2" />
                        </svg>
                    </button>
                </div>
                @error('password')
                    <p class="login-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="login-row">
                <label class="login-check">
                    <input name="remember" type="checkbox" @checked(old('remember'))>
                    <span aria-hidden="true">
                        <svg width="11" height="11" viewBox="0 0 12 12" fill="none" stroke="#F7EFE4" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="m2.5 6.5 2.4 2.4L10 3.6" />
                        </svg>
                    </span>
                    Recordarme
                </label>

                @if (Route::has('password.request'))
                    <a class="login-forgot" href="{{ route('password.request') }}" wire:navigate>Olvid&eacute; mi contrase&ntilde;a</a>
                @endif
            </div>

            <button type="submit" class="login-primary" data-test="login-button">
                Acceder con correo
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M5 12h14M13 6l6 6-6 6" />
                </svg>
            </button>

            <div class="login-divider"><span>o</span></div>
        </form>

        @if (Route::has('register'))
            <p class="login-signup">&iquest;No tienes una cuenta? <a href="{{ route('register') }}" wire:navigate>Reg&iacute;strate</a></p>
        @endif
    </div>
</x-layouts::auth>
