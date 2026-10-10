---
tipo: idea
descripcion: Usar las partículas que mide la estación propia como factor de suciedad y recomendar cada cuánto limpiar
estado: 🟡 Candidata — depende de que el dato de partículas ya esté entrando
esfuerzo: ~1-2 días si el dato llega; integración primero si no
creado: 2026-10-10
tags: [idea, clima, diferencial]
---

# El polvo del Sahara como pérdida

**Qué es:** el polvo que cruza el Atlántico se deposita en los paneles y baja la generación. La
estación propia de Maicao mide **partículas** ([[ideas-aplazadas-asesoria]]): con eso la app puede
aplicar un **factor de suciedad** al cálculo y recomendar **cada cuánto limpiar**.

## Por qué
**Es el único diferencial que la competencia no puede copiar.** Cualquiera puede bajar NASA POWER y
multiplicar; nadie más tiene una estación en Maicao midiendo el polvo. Un calculador genérico asume
una pérdida por suciedad fija del 2-5 % y se olvida; aquí la pérdida es local, estacional y medible, y
en temporada de polvo es bastante mayor que ese supuesto.

Y la recomendación de limpieza es **accionable**, que es lo que le falta a la mayoría de los números
de la app: no le dice al cliente cuánto dejó de ganar, le dice qué hacer para no dejar de ganarlo.

## Viabilidad
- **Primero hay que confirmar un dato:** ¿las partículas de la estación de Maicao ya están entrando a
  las lecturas, o la estación mide pero el dato no llega? No está verificado. **Si llega**, son ~1-2
  días: un factor en el cálculo y una recomendación. **Si no llega**, antes hay integración, y eso ya
  es otro tamaño.
- **Dónde va:** el factor es dominio (`SolarCalculator`), leído de
  [[adr-0015-valores-de-referencia|valores de referencia]] y modulado por la lectura. La fuente, si
  hace falta, es un `ClimateSource` nuevo en `app/Infrastructure/Climate`.
- **Riesgo:** afirmar una pérdida sin respaldo. Pasar de partículas en el aire a pérdida de generación
  en el panel **no es una regla de tres**, y no hay que inventar la correlación: o se consigue de
  literatura, o se declara como estimación gruesa, o se muestra la medición sin convertirla en kWh.

## 💬 Sirve en el pitch aunque no esté construido
La frase *"medimos el polvo del Sahara, que es lo que de verdad baja la generación aquí"* ya es
diferencial, y es verdad: la estación existe. No hace falta tener el factor en el cálculo para
contarlo como lo que hace única a la plataforma en La Guajira.

## Por decidir
- [ ] ¿El dato de partículas ya está entrando a la base? (verificar antes de estimar nada más)
- [ ] De dónde sale la relación partículas → pérdida, o si solo se muestra la medición.
- [ ] Si la recomendación de limpieza es un número fijo de días o depende de la temporada.

## Relacionado
[[ideas-aplazadas-asesoria]] · [[adr-0008-datos-climaticos-en-pestanas]] ·
[[adr-0009-nasa-power-diario-con-datos-reales]] · [[adr-0015-valores-de-referencia]] ·
[[monitoreo-de-generacion-real]] · [[problema-y-propuesta-de-valor]] · [[nuevas-funcionalidades]]
