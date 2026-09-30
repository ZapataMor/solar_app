---
tipo: nota
creado: 2026-09-30
tags: [pendiente, limpieza]
---

# Pendientes de limpieza

Salió del escaneo del 2026-09-30. Nada de esto se referencia en el código (verificado con `git grep`).

## Eliminar
- [ ] `resources/views/flux/icon/` → `book-open-text`, `folder-git-2`, `layout-grid`: íconos del starter kit sin uso
- [ ] `resources/views/components/placeholder-pattern.blade.php`: componente sin uso
- [ ] `tests/Feature/ExampleTest.php` y `tests/Unit/ExampleTest.php`: tests de ejemplo
- [ ] Comando `inspire` en `routes/console.php`: demo de Laravel

## Mover (conservar)
- [ ] `app/reference/1. Calculo On Grid.xlsx` → `docs/` o este vault: referencia del cálculo, no es código
- [ ] `AMBIENT_WEATHER_INTEGRATION.md` → `docs/` o este vault

## Hecho
- [x] `getMessage().PHP_EOL`, `Dashboard Solar Inteligente/`, layouts `header`/`card`/`split`
