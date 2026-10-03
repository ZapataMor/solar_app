---
tipo: adr
descripcion: ADR-0018 — En Datos climáticos, la estación de la pestaña elegida se ve en 3D (mástil Ambient Weather, centro meteorológico, satélite de NASA POWER) en lugar de tarjetas que repetían los conteos
estado: ✅ Implementada
actualizado: 2026-10-03
---

# ADR-0018 · Estaciones de datos en 3D

- **Estado:** 🟢 Aceptada · implementada el 2026-10-02
- **Fecha:** 2026-10-02
- **Contexto del repo:**
  - `resources/js/station-scene/` (`index.js`, `scene.js`, `stations.js`, `earth.js`);
  - `api-data/partials/station-figure.blade.php`, `resources/css/data-stations.css`;
  - `App\Actions\Climate\DescribeDataStations`, `App\Domain\Climate\Wind`.

## Contexto
- **El encabezado de *Datos climáticos* tenía cuatro tarjetas:** total, Ambient Weather, estación local y NASA POWER. Repetían los conteos que ya están en cada pestaña ([[adr-0008-datos-climaticos-en-pestanas]]) y en la etiqueta de cada sección.
- **La página se usa en el pitch** ([[pitch-primera-etapa]]) para mostrar de dónde salen los datos, pero ninguna fuente tenía una imagen.
- **No hay modelos 3D** de ninguna de las tres estaciones.

## Decisión

### 1. La estación en lugar de las tarjetas
- **El encabezado queda en dos columnas:** a la izquierda, el título y el total de registros; a la derecha, la figura (máximo 25rem de ancho, proporción 16:10).
- **Ocupa el lugar de las tarjetas,** así que el encabezado no crece. En el celular, la figura va debajo del título.
- **La figura sigue la pestaña:** al cambiar, la estación que se ve se encoge, la siguiente crece (≈0,8 s) y la cámara se acomoda.
- **El pie de figura** dice qué estación es y qué mide.

### 2. Las tres estaciones
Son figuras simples (*low-poly*), con el estilo de [[adr-0012-ilustracion-3d-de-la-instalacion]]. **Ilustran lo que mide cada fuente; no son un modelo comercial exacto.**

| Pestaña | Estación | Qué se ve | Qué se mueve |
|---|---|---|---|
| Ambient Weather | Sensor todo en uno sobre un mástil | Embudo de lluvia, panel solar, platos que dan sombra al termómetro, cazoletas y veleta | Cazoletas y veleta con el **viento de la última lectura**; anillos dorados y un LED cuando envía |
| Estación local | Centro meteorológico | Abrigo con persianas (temperatura y humedad); mástil con sensores UVA y UVB, medidor de CO₂ y partículas con ventilador, gabinete del registrador, panel solar y antena | Sensores UV con brillo violeta, ventilador, polvo que sube junto al medidor, anillos desde la antena |
| NASA POWER | Satélite en órbita casi polar | La Tierra con los continentes y, en dorado, **el punto que la app le consulta a NASA** (La Guajira) | El satélite pasa sobre el punto: el haz y el punto se iluminan y se abre un anillo |

- **Las estaciones en tierra** están sobre un disco de arena con un cardón y piedras, y giran despacio; arrastrarlas las detiene un momento.
- **El globo no gira solo:** se orienta para que el punto mire a la cámara, y se puede mover un poco.

### 3. Datos reales, pocos y sin cálculos
- **Viento:** `DescribeDataStations` toma la última lectura de Ambient Weather que tiene viento. `Wind` la traduce: "13 km/h del noreste" o "en calma" (menos de 1 km/h).
  - **La veleta apunta de dónde viene el viento.** Las cazoletas giran más rápido con más viento y se quedan quietas en calma.
  - **La sincronización automática** (cada 5 minutos) envía el viento nuevo, y la veleta gira sin recargar la página.
- **Punto de NASA:** latitud y longitud de `services.nasa_power` (por defecto, Riohacha).
- **La escena solo dibuja** lo que le entregan los `data-*` de la figura; no calcula nada del negocio.

### 4. Reglas técnicas
- **Un solo lienzo WebGL** para las tres estaciones. Cada una se construye la primera vez que se muestra.
- **Three.js se carga con `import()` solo en esta página.** Comparte el archivo con la escena de "Mi sistema".
- **El mapa de la Tierra se dibuja en el navegador:** contornos aproximados de los continentes en un lienzo de 1024 × 512. No se descarga ninguna imagen.
- **Rendimiento y accesibilidad,** como en ADR-0012:
  - se pausa fuera de pantalla y con la pestaña oculta;
  - limita la densidad de píxeles en el celular;
  - libera el contexto al navegar;
  - con *reducir movimiento* queda quieta;
  - sin WebGL quedan bocetos planos (SVG) de cada estación.
- **Sin destello del boceto al cargar:**
  - con WebGL, la figura espera el 3D con un indicador ("Preparando la estación en 3D…");
  - el archivo de la escena se pide desde el `<head>` de la página;
  - es el mismo mecanismo de [[adr-0012-ilustracion-3d-de-la-instalacion]] (*Carga sin destello*).

### 5. Los conteos, en las pestañas
- El número de cada pestaña lleva ahora el atributo que actualiza la sincronización. Antes se quedaba con el valor de cuando cargó la página.
- El total del encabezado suma esos números.

## Consecuencias
- ➕ Cada fuente tiene una imagen propia: en el pitch se entiende de dónde sale cada dato.
- ➕ El encabezado no crece y ya no repite información.
- ➕ La veleta muestra el viento real de la estación, y la pestaña, el conteo al día.
- ➖ Más código visual sin pruebas automáticas. Se prueban los datos: `Wind`, `DescribeDataStations` y la vista.
- ➖ El mapa es aproximado: es una ilustración, no cartografía.
- ⚠️ Los modelos no copian las estaciones reales. Si el equipo consigue fotos del mástil de Ambient o de la estación del centro meteorológico, conviene ajustarlos.

## Alternativas consideradas
- **Un lienzo por pestaña:** triplica los contextos WebGL y la memoria.
- **Modelos GLB descargados:** exigen licencias, pesan más y no muestran el viento real.
- **Tarjetas con otra información (por ejemplo, la hora de la última lectura):** encaja mejor con la salud de la sincronización ([[adr-0016-alertas-de-sincronizacion-de-datos]]).
- **Una foto satelital de la Tierra como textura:** pesa varios MB y necesita atribución; el mapa dibujado ocupa unos KB.

## En la landing (2026-10-03)
La figura también está en la sección "Datos reales" de la landing, en modo compacto (`$compact`: sin los textos de cada estación, solo el viento en vivo). Las tarjetas de las fuentes cambian la estación con `[data-station-choice]`; ver [[adr-0012-ilustracion-3d-de-la-instalacion]].

## Por decidir
- ¿Mostrar en la figura la hora de la última lectura de cada fuente, junto con el aviso de [[adr-0016-alertas-de-sincronizacion-de-datos]]?
- ¿Conseguir fotos de las estaciones reales para ajustar los modelos?

## Relacionado
[[adr-0008-datos-climaticos-en-pestanas]] · [[adr-0009-nasa-power-diario-con-datos-reales]] · [[adr-0012-ilustracion-3d-de-la-instalacion]] · [[adr-0016-alertas-de-sincronizacion-de-datos]] · [[pitch-primera-etapa]]
