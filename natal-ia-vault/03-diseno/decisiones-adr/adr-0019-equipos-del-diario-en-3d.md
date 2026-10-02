---
tipo: adr
descripcion: ADR-0019 — Al agregar un equipo al diario de consumo, se ve en 3D como lo dejan sus opciones; los 15 equipos del catálogo, hechos por grupos
actualizado: 2026-10-02
---

# ADR-0019 · Equipos del diario en 3D

- **Estado:** 🟢 Aceptada · los cuatro grupos (15 equipos) implementados el 2026-10-02
- **Fecha:** 2026-10-02
- **Contexto del repo:**
  - `resources/js/appliance-scene/` (`index.js`, `scene.js`, las piezas comunes en `parts.js` y un archivo por equipo, como `air-conditioner.js`);
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
- **Un archivo por equipo** en `resources/js/appliance-scene/`.
- **Las piezas que se repiten están en `parts.js`:** el pedazo de habitación, el mesón, la mesa, la puerta con bisagra, la pastilla *INVERTER*, las partículas (aire, frío, vapor) y el ciclo del compresor.
- **Cada archivo exporta:**
  - `frame`, el encuadre. Es el mismo para las variantes que solo cambian de tamaño, para que se comparen. Puede depender de la variante cuando son tipos distintos (abanico de mesa o de techo, portátil o de escritorio), y puede pedir su ángulo de cámara;
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
| **2 · Frío** | Nevera | Pequeña, mediana, grande o dos puertas · convencional o inverter | ✅ 2026-10-02 |
| | Congelador | Horizontal pequeño, horizontal grande o vertical | ✅ 2026-10-02 |
| | Enfriador de bebidas | 1 o 2 puertas | ✅ 2026-10-02 |
| | Vitrina refrigerada | Sin opciones | ✅ 2026-10-02 |
| **3 · Sala y oficina** | Televisor | 32", 43", 55" o 65" | ✅ 2026-10-02 |
| | Abanico | De mesa, de pie o de techo | ✅ 2026-10-02 |
| | Bombillos | LED, ahorrador o incandescente | ✅ 2026-10-02 |
| | Computador | Portátil o de escritorio | ✅ 2026-10-02 |
| | Internet (módem) | Sin opciones | ✅ 2026-10-02 |
| **4 · Cocina y patio** | Lavadora | Hasta 12 kg o más de 12 kg | ✅ 2026-10-02 |
| | Bomba de agua | ½ HP o 1 HP | ✅ 2026-10-02 |
| | Microondas | Sin opciones | ✅ 2026-10-02 |
| | Licuadora | Sin opciones | ✅ 2026-10-02 |
| | Plancha | Sin opciones | ✅ 2026-10-02 |

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

## Cómo quedaron los grupos 2 a 4 (2026-10-02)
Cada equipo muestra lo que cambian sus opciones, con una frase que lo explica.

**Grupo 2 · Frío**
- **Nevera:**
  - el tamaño cambia sus medidas, de 55 × 125 cm a la de dos puertas de 91 × 178 cm; las grandes son de acero;
  - la puerta se abre de vez en cuando: se ven la luz, las repisas con comida y el frío que sale;
  - la tecnología se ve en el calor que el compresor bota por detrás, a ráfagas en la convencional y parejo en la inverter, que lleva la etiqueta.
- **Congelador:**
  - el horizontal sube la tapa y el frío se queda adentro, porque pesa;
  - el vertical abre la puerta: se ven los cajones y el frío cae al piso;
  - la frase explica por qué el horizontal pierde menos.
- **Enfriador de bebidas:** de tienda, con un aviso iluminado ("BEBIDAS FRÍAS"), puertas de vidrio y repisas llenas de botellas y latas. Una puerta se abre de vez en cuando.
- **Vitrina refrigerada:** mostrador con vidrio inclinado, bandejas de queso, jamón, tortas y salchichas, y frío suave detrás del vidrio.

**Grupo 3 · Sala y oficina**
- **Televisor:**
  - la pantalla 16:9 tiene su tamaño real por pulgadas;
  - el mueble no cambia, y una planta al lado sirve de referencia;
  - muestra un paisaje de La Guajira animado.
- **Abanico:**
  - el de mesa y el de pie giran de lado a lado y muestran el aire que mueven;
  - el de techo se ve desde abajo, con sus cinco aspas.
- **Bombillos:**
  - LED: una cúpula blanca de luz fría;
  - ahorrador: una espiral que tarda en encender del todo;
  - incandescente: el filamento al rojo, luz cálida y el calor que sube.
- **Computador:**
  - el portátil, de cerca, con la pantalla dibujando una gráfica;
  - el de escritorio, con monitor, teclado, mouse y la torre en el piso con su luz.
- **Internet (módem):** en una repisa, con antenas, luces que parpadean y ondas de Wi-Fi.

**Grupo 4 · Cocina y patio**
- **Lavadora:**
  - de carga frontal: por el vidrio se ve el tambor girando con la ropa y el agua;
  - cada cierto tiempo centrifuga y vibra;
  - la de más de 12 kg es más grande.
- **Bomba de agua:**
  - saca agua del tanque y la sube por el tubo; se ve el agua corriendo y un manómetro;
  - la de 1 HP tiene el motor más grande, más flujo y más presión.
- **Microondas:** en el mesón. Por la ventana se ve el plato girando con una taza y la luz encendida mientras calienta, y la pantalla lleva la cuenta regresiva.
- **Licuadora:** en el mesón. En ratos cortos giran las cuchillas, la fruta da vueltas en el batido y se abre un remolino.
- **Plancha:** sobre su tabla con una camisa. Va y viene, con la luz de calentado y vapor por delante.

**Una prueba (`ApplianceConsumptionTest`) falla** si algún equipo del catálogo queda sin modelo registrado.

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
