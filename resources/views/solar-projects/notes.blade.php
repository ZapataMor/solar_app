{{-- Free notes of a project (the former description, ADR-0013). --}}
<x-layouts::app :title="__('Notas').' · '.$solarProject->name">
    <div class="solar-project-detail">
        @include('solar-projects.partials.project-nav', ['solarProject' => $solarProject, 'active' => 'notes'])

        <div class="solar-page solar-page-narrow">
            @if (session('status'))
                <div class="solar-alert solar-alert-success" role="status">{{ session('status') }}</div>
            @endif

            <section class="solar-card">
                <div class="solar-page-header">
                    <div>
                        <p class="solar-kicker">Notas del proyecto</p>
                        <h1 class="text-2xl text-[color:var(--solar-text)]">Un lugar para anotar</h1>
                        <p class="solar-subtitle mt-2">Opcional. Úsalo para recordar detalles: el tipo de techo, si hay sombra en la tarde, lo que se habló en la visita técnica o los acuerdos con el instalador.</p>
                    </div>
                </div>

                <form method="POST" action="{{ route('solar-projects.notes.update', $solarProject) }}" class="mt-6 grid gap-4">
                    @csrf
                    @method('PUT')

                    <label class="solar-field">
                        <span class="solar-field-label">Notas</span>
                        <textarea name="description" rows="10" maxlength="5000" class="solar-textarea" placeholder="Techo de eternit con buena orientación; el vecino tiene un árbol que da sombra después de las 4 p. m.">{{ old('description', $solarProject->description) }}</textarea>
                        @error('description')
                            <span class="text-sm text-[color:var(--solar-danger)]">{{ $message }}</span>
                        @enderror
                    </label>

                    <div class="flex justify-end">
                        <button type="submit" class="solar-button">Guardar notas</button>
                    </div>
                </form>
            </section>
        </div>
    </div>
</x-layouts::app>
