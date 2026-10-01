---
tipo: adr
descripcion: ADR-0012 — Ilustración 3D animada (Three.js) de la casa o el negocio con sus paneles, usando los números del proyecto
actualizado: 2026-10-01
---

# ADR-0012 · Ilustración 3D de la instalación

- **Estado:** 🟡 Propuesta (registrada; sin implementar)
- **Fecha:** 2026-10-01
- **Contexto del repo:** `solar-projects/show.blade.php` (panel), paso *Resumen* de `_form.blade.php` ([[adr-0007-formulario-de-proyecto-por-etapas]]), `landing`, `resources/js/app.js`, Vite

## Contexto
El resultado de un proyecto se expresa en cifras: "11 paneles, 6 kWp, 28 m²". Aun con las preguntas explicativas ([[adr-0011-preguntas-explicativas-del-proyecto]]), el cliente **no se imagina** cómo quedaría su casa o su negocio. Para el pitch ([[pitch-primera-etapa]]), ver la estructura con los paneles instalándose comunica la propuesta en segundos.

## Decisión
Una **ilustración 3D animada** construida con **Three.js**:

- **Escena procedimental de estilo *low-poly*:**
  - la casa y el local se arman con figuras simples, sin modelos descargados (sin archivos pesados ni licencias);
  - los colores salen de la paleta de Natalia (arena y dorado) y la escena se adapta al modo claro u oscuro.
- **Tipo de estructura:**
  - **casa** si el consumo es de hogar;
  - **local** si es de negocio;
  - se deduce de los segmentos de los equipos ([[adr-0002-consumo-por-electrodomesticos]]); sin equipos (modo recibo) se usa la casa.
- **Secuencia:**
  1. aparece la estructura;
  2. los paneles se instalan **uno por uno** con un contador ("Panel 7 de 11");
  3. un sol cruza el cielo y los paneles brillan con la generación del día ("≈ 25 kWh hoy").
- **Datos reales del proyecto:**
  - cantidad de paneles = `calculation_results.number_of_panels`;
  - tamaño del techo = área disponible × % útil;
  - inclinación de unos 11° (la latitud de La Guajira), orientada al sur.
- **Interacción:** girar con el mouse o el dedo (`OrbitControls`), con zoom limitado.
- **Rótulo fijo:** *"Ilustración: no es el plano de instalación"*.

### Dónde, en orden de prioridad
1. **Panel del proyecto:** bloque "Así se vería tu sistema". Es lo de mayor impacto para el pitch.
2. **Paso *Resumen* del formulario:** los paneles aparecen o desaparecen mientras cambia el área, conectado al simulador que ya calcula los paneles en vivo.
3. **Landing:** versión genérica en bucle.

### Reglas técnicas
- **Three.js por npm, importado con `import()` dinámico** solo donde hay escena (≈150 KB comprimido): no pesa en las demás páginas.
- **Un módulo propio**, por ejemplo `resources/js/solar-scene/`. La vista solo entrega los datos en atributos `data-*`; la escena no calcula nada del negocio.
- **Rendimiento:**
  - la animación se pausa cuando no está en pantalla (`IntersectionObserver`);
  - se limita el *pixel ratio* en el celular;
  - se liberan los recursos al navegar (`wire:navigate`).
- **Accesibilidad:**
  - con *reducir movimiento* se muestra el estado final, sin animación;
  - el lienzo lleva una descripción textual ("Casa con 11 paneles solares en el techo").
- **Sin WebGL:** se muestra una imagen fija de respaldo.

## Plan
1. **Prototipo suelto** (página de prueba) para validar el estilo visual antes de integrarlo.
2. **Versión mínima en el panel del proyecto:**
   - casa y local, paneles animados y rotación;
   - estimado: 1–2 días.
3. **Sol y brillo según la generación:** estimado: medio día.
4. **Paso *Resumen* del formulario y landing.**

## Consecuencias
- ➕ El cliente "ve" su sistema: es más persuasivo que las cifras y muy útil en el pitch.
- ➕ Sin dependencias externas en tiempo de ejecución ni modelos con licencia.
- ➖ Se agrega una biblioteca grande. Se mitiga con la carga diferida.
- ➖ Es código visual que hay que mantener; las pruebas son sobre todo manuales.
- ⚠️ No debe sugerir una distribución exacta de paneles: el rótulo y el estilo ilustrativo lo dejan claro.
- ⚠️ Compite en tiempo con [[adr-0003-separar-datos-cliente-e-instalador]] y la parte urgente de [[adr-0004-cotizacion-por-items-y-transporte-interno]] antes del 15 de octubre.

## Alternativas consideradas
- **Spline (editor visual 3D):** se ve muy bien, pero depende de su servicio y cuesta adaptar la escena a los datos de cada proyecto.
- **`<model-viewer>` con modelos GLB:** necesita modelos hechos aparte, y poner N paneles según el cálculo es difícil.
- **Isométrico en CSS o SVG:** más liviano, pero se ve plano y no permite girar.
- **React Three Fiber:** cómodo, pero el proyecto no usa React; agregarlo solo para esto no compensa.

## Por decidir
- ¿Un campo explícito de **tipo de inmueble** (casa, negocio, institución), en vez de deducirlo de los equipos? Encaja con [[adr-0003-separar-datos-cliente-e-instalador]].
- ¿Qué hacer cuando los paneles no caben en el techo dibujado? Por ejemplo, ponerlos en una pérgola o en el suelo.

## Relacionado
[[adr-0011-preguntas-explicativas-del-proyecto]] · [[pitch-primera-etapa]] · [[asesoria-felix-bada]]
