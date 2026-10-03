---
tipo: adr
descripcion: ADR-0012 — Ilustración 3D animada (Three.js) de la casa o el negocio con sus paneles, usando los números del proyecto
estado: ✅ Implementada
actualizado: 2026-10-03
---

# ADR-0012 · Ilustración 3D de la instalación

- **Estado:** 🟢 Aceptada · implementada: "Mi sistema" el 2026-10-01 ([[adr-0014-dimensionar-por-necesidad-y-mi-sistema]]), y el formulario y la landing el 2026-10-03. Ver *Cómo quedó*.
- **Fecha:** 2026-10-01
- **Contexto del repo:**
  - `resources/js/solar-scene/` (`index.js`, `scene.js`, `buildings.js`), `solar-projects/system.blade.php`, `DescribeProjectSystem`, `resources/css/system-panel.css`;
  - para el formulario y la landing: `components/solar-scene.blade.php`, `resources/css/scene-showcase.css`, el último paso de `_form.blade.php` ([[adr-0007-formulario-de-proyecto-por-etapas]]) y `landing.blade.php`.

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
1. ~~**Prototipo suelto**~~: no hizo falta; se construyó directamente en el espacio reservado de "Mi sistema".
2. ✅ **Versión mínima en el panel del proyecto:** casa, local e institución, paneles animados y rotación.
3. ✅ **Sol y brillo según la generación.**
4. ✅ **Paso *Resumen* del formulario y landing** (2026-10-03).

## Cómo quedó (2026-10-01)
**Dónde y con qué datos.**
- La escena está en la pestaña **"Mi sistema"** ([[adr-0014-dimensionar-por-necesidad-y-mi-sistema]]), no en el panel Técnico.
- `figure[data-solar-scene]` entrega:
  - tipo de inmueble;
  - paneles instalados, paneles que caben y paneles que faltan, según el dimensionamiento por consumo y no `number_of_panels` de techo lleno;
  - área del techo y del panel;
  - kWh de un día promedio.
- La escena no calcula nada del negocio.

**Estructuras,** según el campo real `property_type` ([[adr-0013-creacion-guiada-y-diario-de-consumo]]):

| Inmueble | Techo | Paneles | Detalles |
|---|---|---|---|
| Casa | A dos aguas | Sobre la cara sur | Puerta y ventanas |
| Local | Plano con muro perimetral | En soportes a 11° | Vitrina, toldo a rayas y aviso |
| Institución | Edificio largo con una sola caída hacia el sur | Sobre esa caída | Ventanas de aulas y asta con bandera |

- El tamaño del edificio sale del área del techo.
- El suelo es un disco de arena con cardones y piedras.

**Paneles.**
- Cada lugar donde cabe un panel se dibuja con un contorno.
- Los instalados caen uno a uno en esos lugares ("Panel 7 de 11").
- Los lugares que sobran quedan como contorno.
- **Los que no caben aparecen como contornos rojos en el suelo, junto al edificio** (como máximo 30; el texto dice el número real).
- Los paneles conservan su tamaño real, salvo que el techo dibujado sea muy pequeño.

**Un día de sol.**
- Después de instalar los paneles, la luz y el color del cielo siguen la hora del día.
- Las celdas brillan más al mediodía. De noche se encienden las ventanas.
- Una franja bajo la escena muestra la hora, un arco con el sol y lo producido en el día ("≈ 16,8 kWh hoy"). Con el selector de unidad, lo muestra en pesos.
- El sol no se dibuja en el cielo: la cámara mira la cara sur desde el sur, así que el sol real quedaría detrás de ella. Por eso va en la franja.
- Un botón repite la animación.

**Interacción.**
- Se gira con el mouse o con el dedo.
- El zoom solo se activa después de tocar la escena, para que la rueda siga desplazando la página.
- En el celular, deslizar en vertical desplaza la página.

**Rótulo:** "Ilustración: no es el plano de instalación · arrastra para girarla", debajo de la escena.

**Reglas técnicas cumplidas.**
- Three.js se carga con `import()` solo en "Mi sistema": ≈155 KB comprimido, en un archivo aparte.
- La animación se pausa fuera de pantalla y con la pestaña oculta.
- La densidad de píxeles se limita a 1,5 en pantallas táctiles.
- El contexto WebGL se libera al navegar con `wire:navigate`.
- Con *reducir movimiento* se muestra el estado final (11 a. m. y "≈ X kWh al día").
- Sin WebGL se queda el boceto plano que dibuja el servidor.

**Carga sin destello (2026-10-02).** Al recargar o al abrir un proyecto se veía un instante el boceto plano antes del 3D.
- **Con WebGL ya no se ve el boceto:**
  - un script en el `<head>` marca la página (`solar-can-3d`) antes del primer pintado;
  - el escenario ocupa su lugar desde el principio, así que nada se mueve cuando llega el 3D;
  - mientras carga se ve un barrido de luz, y el texto "Preparando la ilustración 3D…" solo si tarda más de un tercio de segundo;
  - el 3D entra con un fundido corto.
- **El boceto queda de respaldo:** sin WebGL, si la escena falla o si tarda más de 8 s (`resources/js/scene-loader.js`). Si el 3D llega después, lo reemplaza.
- **La escena baja antes:**
  - "Mi sistema" la pide desde su `<head>`, al mismo tiempo que `app.js`;
  - las demás páginas, al terminar de cargar, la bajan en segundo plano y con baja prioridad (`Vite::prefetch`). Así, el primer proyecto que se abre desde el portafolio ya la tiene.
- **Con `wire:navigate`,** Livewire copia los atributos del `<html>` de la página nueva y borraría la marca. Se repone en el mismo cambio (`onSwap`), antes de pintar.

## Cómo quedó en el formulario y la landing (2026-10-03)
Un mismo componente, `<x-solar-scene>`, dibuja la figura en los dos lugares; la escena es la misma de "Mi sistema" y cambia solo por el modo (`data-scene-mode`).

**Landing (modo `showcase`).**
- **Dónde:** a la derecha del encabezado, como una tarjeta de vidrio oscuro sobre la foto. En pantallas angostas baja debajo del texto.
- **Qué muestra:** una casa de ejemplo con 8 paneles. Tres botones (*Casa*, *Negocio*, *Institución*) la cambian por un local con 14 o un edificio de 20; cada cambio levanta la estructura de nuevo.
- **Gira sola,** despacio. Si alguien la toma, se detiene, y vuelve a girar 4 s después de soltarla. Con *reducir movimiento* queda quieta.
- **Sin cifras inventadas:** no tiene la franja de energía del día. El texto dice "Ejemplo: 8 paneles en el techo", y el rótulo "Ilustración de ejemplo".
- **Carga:** la pide desde el `<head>`, con el resto de la página, y deja el indicador común mientras llega. Sin WebGL queda el boceto plano de cada tipo de lugar y los botones se ocultan.
- **De paso,** el paso 2 de "Cómo funciona" ya no dice "ingresa tu consumo": ahora se agregan los equipos ([[adr-0013-creacion-guiada-y-diario-de-consumo]]).

**Estaciones en la landing.** La sección "Datos reales" muestra las estaciones en 3D de [[adr-0018-estaciones-de-datos-en-3d]]:
- **Una sola figura** (la del encabezado de *Datos climáticos*, en modo compacto) a la izquierda, y las tres fuentes como tarjetas a la derecha.
- **Elegir una tarjeta** cambia la estación: el mástil de Ambient Weather (Riohacha), la estación propia de Maicao o el satélite de NASA POWER sobre el globo. La figura sigue `data-station`, igual que las pestañas de *Datos climáticos*.
- **Datos reales:** la veleta gira con el viento de la última lectura y debajo se lee "Viento de la última lectura: 13 km/h del noreste".
- **Las tarjetas describen** cada fuente; la figura no repite el texto.
- **Sin WebGL** quedan los bocetos planos de cada estación.

**Formulario (modo `preview`).**
- **Dónde:** en el último paso, junto al resumen del lugar, la ubicación y el techo.
- **Qué muestra:** el techo con **los paneles que caben**, con la misma cuenta que la pista del paso del techo: `área × % útil ÷ área del panel`. Si no cabe ninguno, lo dice.
- **Lo que nunca muestra:** cuántos paneles se necesitan. Todavía no hay equipos; eso lo dice "Mi sistema".
- **Sigue al formulario:** el tipo de lugar, el área, el % útil y el panel. Cada cambio espera 350 ms y la escena reinstala los paneles sin rehacer el edificio; solo un tipo de lugar distinto lo levanta de nuevo.
- **Al editar,** el tipo de lugar es el del proyecto, que no se cambia.

**En la escena.**
- `scene.js` separa lo que depende de los números (`createWorld`: edificio, paneles, suelo y decoración) del resto (cámara, luz, controles). `refresh()` lo rehace conservando el ángulo que el usuario eligió.
- La figura expone la escena en `figure.solarScene`; las páginas escriben los `data-*` y la llaman. Montada o no, la escena lee los datos más recientes.
- Pesa lo mismo que antes: Three.js sigue siendo un solo archivo compartido.

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
- ~~¿Un campo explícito de tipo de inmueble?~~ Resuelto: `property_type` ([[adr-0013-creacion-guiada-y-diario-de-consumo]]).
- ~~¿Qué hacer cuando los paneles no caben?~~ Resuelto en la ilustración: contornos rojos en el suelo. Falta decidir qué se le propone al cliente ([[adr-0014-dimensionar-por-necesidad-y-mi-sistema]]).
- ~~¿Llevar la escena también al paso *Resumen* del formulario y a la landing?~~ Resuelto el 2026-10-03.
- ¿Mostrar en la landing un proyecto real de demostración, con sus números, en lugar del ejemplo genérico?

## Relacionado
[[adr-0011-preguntas-explicativas-del-proyecto]] · [[adr-0014-dimensionar-por-necesidad-y-mi-sistema]] · [[pitch-primera-etapa]] · [[asesoria-felix-bada]]
