---
tipo: adr
descripcion: ADR-0019 — Al agregar un equipo al diario de consumo, se ve en 3D como lo dejan sus opciones; los equipos se modelan por grupos, empezando por el aire acondicionado
actualizado: 2026-10-02
---

# ADR-0019 · Equipos del diario en 3D

- **Estado:** 🟢 Aceptada · grupo 1 (aire acondicionado) implementado el 2026-10-02; los demás grupos, pendientes
- **Fecha:** 2026-10-02
- **Contexto del repo:**
  - `resources/js/appliance-scene/` (`index.js`, `scene.js` y un archivo por equipo, como `air-conditioner.js`);
  - la hoja "Agregar equipo" de `solar-projects/consumption.blade.php`;
  - `resources/css/appliance-model.css`.

## Contexto
- **En el diario de consumo** ([[adr-0013-creacion-guiada-y-diario-de-consumo]]) cada equipo tiene un ícono plano, y sus opciones son botones de texto: "12.000 BTU", "Inverter". El cliente no siempre sabe qué equipo es el suyo.
- **Las casas y las estaciones ya se ven en 3D** ([[adr-0012-ilustracion-3d-de-la-instalacion]], [[adr-0018-estaciones-de-datos-en-3d]]). Ver el equipo con sus opciones ayuda a reconocerlo y a entender por qué gasta lo que gasta.
- **Son 15 equipos con muchas variantes.** Hacerlos todos de una vez es demasiado: van por grupos.

## Decisión

### 1. Dónde y cuándo
- **Solo al agregar o editar un equipo,** en el paso de opciones de la hoja. No aparece en la lista del diario ni al elegir el equipo.
- **A la derecha de las opciones,** en un recuadro pequeño (17rem). Con modelo, la hoja se ensancha; en el celular el recuadro va arriba, bajo y ancho.
- **Cambia en vivo:** al tocar una opción, el equipo se rehace con un pequeño salto.
- **Debajo, una línea** con las opciones elegidas ("18.000 BTU · Inverter") y una frase que explica la diferencia que se ve.
- **Si el equipo no tiene modelo todavía,** o el navegador no tiene WebGL, la hoja queda como antes: sin recuadro y con su ancho normal.

### 2. Qué muestra el modelo
- **Las opciones cambian lo que se ve, con proporciones cercanas a las reales:** un aire de 24.000 BTU es más grande que uno de 9.000, y un televisor de 65" más grande que uno de 32".
- **La tecnología se ve en cómo funciona,** no solo en una etiqueta. Por ejemplo, el convencional arranca y se apaga, y el inverter mantiene un ritmo parejo.
- **Cada equipo va en su lugar habitual** (pared, piso, mesón), con el mismo encuadre para todas sus variantes, así los tamaños se comparan.
- **Son ilustraciones,** como las de ADR-0012: no representan una marca ni un modelo comercial.

### 3. Reglas técnicas
- **Un archivo por equipo** en `resources/js/appliance-scene/`. Exporta:
  - `frame`, el encuadre común a todas las variantes;
  - `build(materials, variant)`, que arma el equipo a partir de la clave de su variante (`'12000.inverter'`);
  - `note(variant)`, la frase de debajo.
- **Se registra con una línea** en `MODELS` (`index.js`), con la clave del catálogo (`ApplianceCatalog`). Sin esa línea, el equipo no muestra recuadro.
- **La escena solo encuadra, ilumina y anima;** el equipo no calcula nada. El consumo sigue saliendo del catálogo.
- **Carga:**
  - Three.js se carga la primera vez que se elige un equipo con modelo; en producción ya viene precargado (`Vite::prefetch`);
  - mientras carga, se ve el indicador común de las escenas 3D;
  - la animación solo corre con la hoja abierta.
- **Con *reducir movimiento*** queda quieta.

## Lista de equipos por grupos
Ordenados por cuánto pesan en el recibo y cuánto aparecen en la demo ([[pitch-primera-etapa]]).

| Grupo | Equipo | Opciones que debe mostrar | Estado |
|---|---|---|---|
| **1 · Clima** | Aire acondicionado | Capacidad: 9.000, 12.000, 18.000 y 24.000 BTU · Tecnología: convencional o inverter | ✅ 2026-10-02 |
| **2 · Frío** | Nevera | Pequeña, mediana, grande o dos puertas · convencional o inverter | Pendiente |
| | Congelador | Horizontal pequeño, horizontal grande o vertical | Pendiente |
| | Enfriador de bebidas | 1 o 2 puertas | Pendiente |
| | Vitrina refrigerada | Sin opciones | Pendiente |
| **3 · Sala y oficina** | Televisor | 32", 43", 55" o 65" | Pendiente |
| | Abanico | De mesa, de pie o de techo | Pendiente |
| | Bombillos | LED, ahorrador o incandescente | Pendiente |
| | Computador | Portátil o de escritorio | Pendiente |
| | Internet (módem) | Sin opciones | Pendiente |
| **4 · Cocina y patio** | Lavadora | Hasta 12 kg o más de 12 kg | Pendiente |
| | Bomba de agua | ½ HP o 1 HP | Pendiente |
| | Microondas | Sin opciones | Pendiente |
| | Licuadora | Sin opciones | Pendiente |
| | Plancha | Sin opciones | Pendiente |

**Los equipos que agrega el administrador** ([[adr-0017-catalogo-de-equipos-administrable]]) no tienen modelo propio: se quedan con su ícono. Después de los cuatro grupos se puede decidir un modelo genérico por dibujo (tostadora, enchufe…).

## Cómo quedó el grupo 1 (2026-10-02)
**Aire acondicionado tipo *split*:**
- la unidad interior va alta en la pared y la condensadora en el patio, sobre una base de concreto;
- las une la tubería con aislante.

**Capacidad:** cambia el tamaño de las dos unidades. Medidas aproximadas:

| Capacidad | Unidad interior (ancho) | Condensadora (ancho × alto) |
|---|---|---|
| 9.000 BTU | 78 cm | 70 × 50 cm |
| 12.000 BTU | 84 cm | 76 × 54 cm |
| 18.000 BTU | 97 cm | 84 × 60 cm |
| 24.000 BTU | 108 cm | 90 × 70 cm |

**Tecnología:**
- **Convencional:** el compresor trabaja a toda potencia y se apaga, una y otra vez. El ventilador de la condensadora se detiene, el aire frío se corta y la luz del equipo se atenúa. El ciclo está acortado para que se vea.
- **Inverter:** el flujo es parejo y más suave. Las dos unidades llevan la etiqueta *INVERTER*.

**La frase de debajo** explica esa diferencia: el inverter "casi nunca se apaga, por eso gasta menos". Coincide con el catálogo, donde gasta un tercio menos.

## Consecuencias
- ➕ El cliente reconoce su equipo y entiende cómo las opciones cambian su consumo.
- ➕ Agregar un equipo es un archivo y una línea, sin tocar la hoja ni la escena.
- ➖ Es más código visual sin pruebas automáticas; se revisa en el navegador.
- ⚠️ Las medidas son aproximadas: un instalador puede ajustarlas.

## Por decidir
- ¿Un modelo genérico para los equipos que agrega el administrador?
- ¿Mostrar la cantidad (por ejemplo, 6 bombillos) en el modelo o solo en la línea de debajo?

## Relacionado
[[adr-0002-consumo-por-electrodomesticos]] · [[adr-0012-ilustracion-3d-de-la-instalacion]] · [[adr-0013-creacion-guiada-y-diario-de-consumo]] · [[adr-0017-catalogo-de-equipos-administrable]] · [[adr-0018-estaciones-de-datos-en-3d]]
