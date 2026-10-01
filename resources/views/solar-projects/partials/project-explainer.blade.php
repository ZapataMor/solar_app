{{--
    Plain-language questions about the project (ADR-0011): a rail of dots on the right edge
    (hover shows the question) that opens a side panel taking a third of the screen.
    Params: $questions (list<App\Domain\Explanation\ExplainedQuestion>), $solarProject.
--}}
@php
    $explainerTotal = count($questions);
@endphp

<div class="solar-explainer" data-explainer>
    <nav class="solar-explainer-rail" aria-label="Preguntas sobre tu proyecto">
        <span class="solar-explainer-rail__label" aria-hidden="true">?</span>
        @foreach ($questions as $index => $item)
            <button
                type="button"
                class="solar-explainer-dot solar-explainer-dot--{{ $item->tone }}"
                data-explainer-open="{{ $item->key }}"
                aria-controls="solar-explainer-panel"
                aria-label="{{ $item->question }}"
            >
                <span class="solar-explainer-dot__tip" aria-hidden="true">{{ $item->question }}</span>
            </button>
        @endforeach
    </nav>

    <button type="button" class="solar-explainer-mobile" data-explainer-open="{{ $questions[0]->key }}" aria-controls="solar-explainer-panel">
        <span aria-hidden="true">?</span> ¿Qué significan estos números?
    </button>

    <aside
        id="solar-explainer-panel"
        class="solar-explainer-panel"
        role="dialog"
        aria-modal="false"
        aria-labelledby="solar-explainer-title"
        data-explainer-panel
        hidden
    >
        <header class="solar-explainer-panel__bar">
            <p class="solar-explainer-panel__progress" data-explainer-progress>Pregunta 1 de {{ $explainerTotal }}</p>
            <button type="button" class="solar-explainer-panel__close" data-explainer-close aria-label="Cerrar preguntas">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
            </button>
        </header>

        <div class="solar-explainer-panel__body">
            @foreach ($questions as $index => $item)
                <section class="solar-explainer-answer" data-explainer-answer="{{ $item->key }}" data-index="{{ $index }}" hidden>
                    <h2 class="solar-explainer-answer__question" @if ($index === 0) id="solar-explainer-title" @endif tabindex="-1">{{ $item->question }}</h2>

                    <div class="solar-explainer-answer__headline solar-explainer-answer__headline--{{ $item->tone }}">
                        <strong>{{ $item->headline }}</strong>
                        <span>{{ $item->caption }}</span>
                    </div>

                    @foreach ($item->paragraphs as $paragraph)
                        <p>{{ $paragraph }}</p>
                    @endforeach

                    @if ($item->key === 'not-calculated')
                        <form method="POST" action="{{ route('solar-projects.calculate', $solarProject) }}">
                            @csrf
                            <button type="submit" class="solar-button">Calcular ahora</button>
                        </form>
                    @elseif ($item->key === 'no-appliances')
                        <a href="{{ route('solar-projects.consumption', $solarProject) }}" class="solar-button" wire:navigate>Agregar mis equipos</a>
                    @endif
                </section>
            @endforeach
        </div>

        @if ($explainerTotal > 1)
            <footer class="solar-explainer-panel__nav">
                <button type="button" class="solar-button-ghost" data-explainer-step="-1">← Anterior</button>
                <button type="button" class="solar-button" data-explainer-step="1">Siguiente →</button>
            </footer>
        @endif
    </aside>
</div>

<script>
(() => {
    const root = document.querySelector('[data-explainer]');

    if (!root || root.dataset.ready) {
        return;
    }

    root.dataset.ready = 'true';

    const panel = root.querySelector('[data-explainer-panel]');
    const answers = Array.from(root.querySelectorAll('[data-explainer-answer]'));
    const dots = Array.from(root.querySelectorAll('.solar-explainer-dot'));
    const progress = root.querySelector('[data-explainer-progress]');
    let current = 0;
    let opener = null;

    const show = (index) => {
        current = (index + answers.length) % answers.length;
        answers.forEach((answer, position) => {
            answer.hidden = position !== current;
        });
        dots.forEach((dot, position) => dot.setAttribute('aria-current', position === current ? 'true' : 'false'));
        progress.textContent = `Pregunta ${current + 1} de ${answers.length}`;
        // The dialog is labelled by the question on screen.
        const headings = answers.map((answer) => answer.querySelector('.solar-explainer-answer__question'));
        headings.forEach((heading) => heading.removeAttribute('id'));
        headings[current].id = 'solar-explainer-title';
        headings[current].focus({ preventScroll: true });
    };

    const open = (key, trigger) => {
        opener = trigger ?? opener;
        panel.hidden = false;
        root.classList.add('is-open');
        show(Math.max(0, answers.findIndex((answer) => answer.dataset.explainerAnswer === key)));
    };

    const close = () => {
        panel.hidden = true;
        root.classList.remove('is-open');
        dots.forEach((dot) => dot.setAttribute('aria-current', 'false'));
        opener?.focus({ preventScroll: true });
    };

    root.querySelectorAll('[data-explainer-open]').forEach((trigger) => {
        trigger.addEventListener('click', () => {
            const key = trigger.dataset.explainerOpen;
            const alreadyShown = !panel.hidden && answers[current].dataset.explainerAnswer === key;
            alreadyShown ? close() : open(key, trigger);
        });
    });

    root.querySelector('[data-explainer-close]').addEventListener('click', close);
    root.querySelectorAll('[data-explainer-step]').forEach((button) => {
        button.addEventListener('click', () => show(current + Number(button.dataset.explainerStep)));
    });

    document.addEventListener('keydown', (event) => {
        if (panel.hidden) {
            return;
        }

        if (event.key === 'Escape') {
            close();
        } else if (event.key === 'ArrowDown' && event.altKey) {
            show(current + 1);
        } else if (event.key === 'ArrowUp' && event.altKey) {
            show(current - 1);
        }
    });
})();
</script>
