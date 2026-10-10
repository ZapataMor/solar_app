---
tipo: idea
descripcion: Una hoja imprimible o PDF con el estudio del proyecto, para mostrárselo a alguien más
estado: 🟡 Candidata
esfuerzo: ~medio día
creado: 2026-10-10
tags: [idea, producto]
---

# Estudio imprimible del proyecto

**Qué es:** el proyecto completo —consumo, sistema recomendado, generación, presupuesto y retorno— en
una hoja que se imprime o se guarda como PDF.

## Por qué
**La decisión no la toma quien usa la app.** Un sistema de 25 millones se decide con la pareja, con
los hijos, con el socio o con el banco, y ninguno de ellos va a crear una cuenta para mirar una
pantalla. Hoy lo único que el cliente puede hacer es contar de memoria lo que vio, que es exactamente
lo que la app vino a evitar.

El banco lo necesita en papel, además, si la idea es que
[[financiacion-cuota-vs-ahorro|haya crédito]].

De paso sirve para el pitch: un estudio impreso es el volante que queda en la mano del jurado.

## Viabilidad
- **Dónde va:** una `Action` tipo `DescribeProjectReport` y una vista. **Sin dependencia nueva**: con
  una hoja de estilos de impresión (`@media print`) el navegador ya exporta a PDF, y eso evita meter
  dompdf o un Chrome headless, que en Windows es justo el tipo de cosa que después no corre en el
  servidor.
- **Esfuerzo:** ~medio día.
- **Riesgo:** bajo. Lo único delicado es que los dibujos 3D no imprimen; para la hoja sirve el boceto
  plano del servidor que ya existe como respaldo sin WebGL
  ([[adr-0012-ilustracion-3d-de-la-instalacion]]).

## Por decidir
- [ ] ¿Lleva las cotizaciones recibidas o solo la estimación de la app?
- [ ] ¿Va con la marca de la plataforma, o neutral para que el instalador lo use?

## Relacionado
[[adr-0012-ilustracion-3d-de-la-instalacion]] · [[adr-0014-dimensionar-por-necesidad-y-mi-sistema]] ·
[[compartir-el-proyecto-por-enlace]] · [[financiacion-cuota-vs-ahorro]] ·
[[cotizacion-y-costos-ocultos]] · [[nuevas-funcionalidades]]
