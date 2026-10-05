---
tipo: adr
descripcion: ADR-0021 — Diseñador 3D solo para desarrollo y su primer modelo: un día completo de un sistema solar híbrido, con hora, nubes, red y baterías que el usuario controla
estado: 🟡 En curso
actualizado: 2026-10-03
---

# ADR-0021 · Diseñador 3D y animación del sistema

- **Estado:** 🟡 En curso · primer modelo hecho el 2026-10-03
- **Contexto del repo:** `resources/js/system-scene/`, `resources/views/designer/index.blade.php`, gate `design-3d` en `AppServiceProvider`.

## Contexto
Las escenas 3D existentes ([[adr-0012-ilustracion-3d-de-la-instalacion]], [[adr-0018-estaciones-de-datos-en-3d]], [[adr-0019-equipos-del-diario-en-3d]]) muestran lugares y equipos, pero ninguna explica **cómo funciona** un sistema solar. Hace falta un banco de pruebas para diseñar modelos sin tocar las pantallas de los clientes.

## Decisión
1. **Diseñador 3D:** pantalla en el grupo *Desarrollo* del sidebar. Se ve solo con el gate `design-3d` (administrador y `APP_ENV=local`); fuera de local la ruta responde 403.
2. **Primer modelo, la animación del sistema:** una casa de concreto de una planta, **cortada como en un plano de arquitectura** (los cortes de muros y losa en gris carbón). A la izquierda el **cuarto técnico**: medidor bidireccional, tablero, inversor y regulador MPPT sobre un tablero de madera, y las baterías en su rack; a la derecha la **sala con cocina**: televisor, sofá sobre una alfombra con figuras wayuu, nevera, lavaplatos bajo la ventana y el foco. **Techo plano con pretil**, tanque de agua y los paneles sobre una estructura inclinada 20° al sur. El poste de la red queda a la izquierda, junto al cuarto técnico.
3. **Colores de la corriente:** ámbar = continua (CC), cian = alterna (CA), verde = excedentes a la red. La luz del sol se ve como haces paralelos con motas de polvo que caen sobre los paneles; **el sol no se dibuja** (se veía de juguete).
4. **Realismo y lectura:** materiales con textura dibujada por código (repello, concreto, madera, contrachapado, arena, celdas, la alfombra), reflejos de un entorno, sombras suaves y tono de cine (ACES), cielo con horizonte y niebla. **Las paredes no son blancas** (al sol aplanaban la imagen): fachada terracota, sala verde salvia y cuarto técnico gris azulado. El desierto, sobrio: dos cardones y dos piedras. Sin archivos de imagen.
5. **Lo que el corte deja ver.** El foco cuelga de la losa. La losa tapa lo alto de la pared del fondo desde una cámara alta, así que los equipos y los cables van a menos de 2,5 m y la cámara se queda baja (entre 4° bajo el horizonte y 9° sobre él). El muro entre los cuartos va completo atrás y **cortado a la altura de la rodilla hacia el frente** (como en los dibujos en corte): entero, tapaba el regulador desde la cámara. Los circuitos de la sala suben del tablero y **cruzan por dentro de la losa**, como en una casa de concreto, así ningún cable cruza a otro.
6. **Un día completo, no una foto.** `simulation.js` (funciones puras, sin DOM ni Three.js) da el sol según la hora, lo que producen los paneles (con su inclinación y las nubes) y a dónde va la energía: **paneles → casa → baterías → red**; lo que no alcanzan los paneles lo dan las baterías hasta su reserva (10 %, y vuelven a usarse al pasar de 15 %), y lo que falta, la red. **Sin red y sin energía, la casa se queda a oscuras.** El día dura un minuto (0,4 h por segundo). Con los equipos de ejemplo las baterías (48 V × 40 Ah) se acaban de madrugada y entra la red: así se ve la respuesta a "¿qué pasa si se descargan?" sin tocar nada.
7. **Lo que controla el usuario** (`controls.js`): la hora (al moverla se pausa el día), "Ver un día completo", escenas rápidas (mediodía, atardecer, noche, baterías vacías, apagón), nublado, red caída, carga de las baterías y qué equipos están encendidos. **La página es compacta:** la imagen a la izquierda, con los cuatro números pequeños arriba a la izquierda (paneles, casa, baterías, red), el reloj arriba a la derecha y la frase de lo que pasa abajo; la hora corre en una barra bajo la imagen, y las escenas, las condiciones, los equipos y la leyenda van en una columna a la derecha (debajo, en pantallas angostas).
8. **El cielo sigue a la hora** (`atmosphere.js`): sol que sale por el este y se pone por el oeste con sus haces de luz, amanecer y atardecer naranjas, noche con estrellas y luz de luna, nubes y niebla. La corriente se ve al revés cuando entra de la red (violeta). Ninguna luz se apaga de verdad (su intensidad baja a 0): quitar o poner una luz hace que todos los materiales reconstruyan su shader.
9. **Medidas y etiquetas.** Pantallas con tensión, corriente, potencia y carga en cada equipo, que cuentan lo que la simulación dice. Cada parte lleva `userData.info = {title, text, live(readout)}`: el texto explica qué es, en palabras de cliente (p. ej. la nevera: "Es un equipo que gasta energía las 24 horas"), y `live` dice qué está haciendo **ahora** ("Ahora: entrega 152 W de corriente alterna").

## Pendiente
- Elegir los electrodomésticos del catálogo (hoy son tres fijos: foco, televisor y nevera, que se encienden o apagan).
- Usar los datos de un proyecto (paneles, consumo, baterías) en lugar de los números de ejemplo.
- Una curva de 24 h (sol, consumo y baterías) bajo la escena.
- Decidir si pasa a la landing o a *Mi sistema*; hoy vive solo en el diseñador.
