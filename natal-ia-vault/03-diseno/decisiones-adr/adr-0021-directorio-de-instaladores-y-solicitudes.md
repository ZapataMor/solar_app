---
tipo: adr
descripcion: ADR-0021 — Primera etapa del marketplace: directorio de instaladores y solicitudes de cotización
estado: 🟢 Aceptada
actualizado: 2026-10-04
---

# ADR-0021 · Directorio de instaladores y solicitudes de cotización

- **Estado:** 🟢 Aceptada
- **Fecha:** 2026-10-04
- **Contexto del repo:** `app/Domain/Installers`, `app/Actions/Installers`, `InstallerController`,
  `installers/index.blade.php`, tablas `installers`, `installer_municipality`, `quote_requests`

## Contexto
[[adr-0005-marketplace-de-instaladores]] describe el marketplace completo: perfil, recomendación, lead,
negocio cerrado y comisión. Es demasiado para el pitch del 15 de octubre de 2026
([[pitch-primera-etapa]]), y la mitad depende de decisiones de negocio sin cerrar: el porcentaje de
comisión y el esquema de cobro siguen abiertos en [[modelo-de-ingresos]].

Hoy `/instaladores` es una página de "Próximamente" que promete tres pasos: calculas, ves quién cubre
tu municipio y pides cotización sobre la misma estimación. Esa promesa se puede cumplir sin cobros,
sin rol nuevo y sin que el instalador entre a la plataforma.

## Decisión
Esta ADR **acota la primera etapa** del ADR-0005. No lo reemplaza: lo que no está aquí sigue
pendiente allá.

- **El instalador es un registro, no un usuario.** Lo crea y lo edita un administrador desde
  `/instaladores` (gate `administer-platform`), igual que el catálogo de equipos
  ([[adr-0017-catalogo-de-equipos-administrable]]). Todavía **no** existe el rol `installer` de
  [[adr-0003-separar-datos-cliente-e-instalador]] ni un panel para él.
- **Cobertura por municipio**, en la tabla `installer_municipality`. El directorio del cliente muestra
  solo los instaladores activos que cubren el municipio del proyecto elegido.
- **Solicitud de cotización = el lead del ADR-0005.** Es una fila `quote_requests` (proyecto ↔
  instalador, con fecha y estado). El estado nace en `sent`; `contacted`, `won` y `lost` quedan
  definidos en el dominio para la etapa siguiente, pero nada los escribe aún.
- **Los datos de contacto se revelan al solicitar**, no antes. Así la solicitud es el momento real del
  contacto y la atribución no depende de la honestidad de nadie.
- **Un proyecto sin consumo no cotiza.** El dominio lanza `QuoteNotPossible`; la tarjeta lo explica y
  enlaza a la pestaña Consumo.
- **La cobertura se elige en el mapa.** El formulario reusa el mapa del formulario de proyecto, pero
  en vez de escoger un municipio, cada clic lo cubre o lo descubre, y cada forma lleva su nombre.
  Las casillas siguen siendo el campo real: sin JavaScript, o si el mapa falla, la lista basta.
- **Los instaladores de la primera etapa son de ejemplo** (`InstallerSeeder`) y la página lo dice con
  un aviso visible. Nadie debe creer que son una red real.
- **Fuera de alcance:** suscripción, comisión, cierre del negocio, notificaciones al instalador y
  cualquier cobro en línea.

## Consecuencias
- ➕ La demo recorre el flujo completo del pitch con datos reales del proyecto del cliente.
- ➕ La atribución (proyecto ↔ instalador ↔ fecha) queda guardada desde el primer día: cuando se
  defina la comisión, los leads ya existen.
- ➖ El instalador no se entera por la app; alguien le pasa la solicitud por fuera.
- ⚠️ El aviso de "datos de ejemplo" tiene que salir en cuanto entren instaladores reales.
- ⚠️ Un proyecto sin municipio (los más viejos) ve todos los instaladores activos, sin filtrar.
- ⚠️ El mapa usa `la_guajira_municipios_simple.geojson` (26 KB). El original del IGAC (4 MB) congela
  la pestaña cada vez que se repinta: si cambia, hay que volver a correr `scripts/maps/simplify-geojson.py`.

## Alternativas consideradas
- **Construir el ADR-0005 completo** — el cobro y la comisión no están definidos; se construiría sobre
  supuestos.
- **Dejar la página de "Próximamente" hasta tener instaladores reales** — el pitch pierde su cierre:
  el cliente calcula y no hay a quién llevarle el resultado.
- **Crear ya el rol `installer`** — obliga a decidir autorización, vistas y registro antes de saber si
  los instaladores usarán la plataforma.

## Relacionado
[[adr-0005-marketplace-de-instaladores]] · [[adr-0003-separar-datos-cliente-e-instalador]] ·
[[modelo-de-ingresos]] · [[clientes-y-usuarios]] · [[pitch-primera-etapa]]
