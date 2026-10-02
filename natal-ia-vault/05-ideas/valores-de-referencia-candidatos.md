---
tipo: nota
creado: 2026-10-02
tags: [idea, por-decidir, valores-de-referencia]
---

# Valores de referencia candidatos

Números que hoy están fijos en el código o que el sistema todavía no tiene, y que podrían
administrarse en **Administración → Valores de referencia** ([[adr-0015-valores-de-referencia]]).
Allí ya están la tarifa del kWh ($890) y la contribución de los negocios (20 %).

**Pendiente:** preguntarle al equipo cuáles se agregan. Marca la casilla de los que se aprueben.

## En el código hoy (se volverían editables)
- [ ] **Costo de instalación por kWp:** $5.000.000 en `SolarCalculator::INSTALLATION_COST_PER_KWP_COP`.
  - Desde el 2026-10-02 solo se usa si el proyecto no tiene cotización por municipio.
  - Ver [[adr-0004-cotizacion-por-items-y-transporte-interno]].
- [ ] **Sol de referencia de La Guajira:** 5,8 horas de sol pico al día (`RequiredPower::REFERENCE_DAILY_HSP`).
  - Se usa para la potencia sugerida y las estimaciones antes de tener datos climáticos.
- [ ] **Panel y techo por defecto** (`SystemSpecification::DEFAULT_*`):
  - potencia del panel: 550 W;
  - área del panel: 2,6 m²;
  - pérdidas del sistema: 14 %;
  - porcentaje útil del techo.
  - Son los valores que el formulario propone en "avanzado".
- [ ] **Antigüedad máxima de la lectura "Ahora mismo":** 180 minutos (`DescribeProjectSystem`).

## Nuevos (el sistema todavía no los usa)
- [ ] **Consumo de subsistencia y subsidios de los estratos 1 a 3.**
  - Permitirían una tarifa por estrato para las casas, que hoy pagan la tarifa completa.
  - Subsistencia en zonas por debajo de 1.000 m: 173 kWh/mes. Confirmar el dato vigente.
- [ ] **Aumento anual de la tarifa:** para proyectar el ahorro de los años siguientes.
- [ ] **Vida útil del sistema** (unos 25 años) **y desgaste anual de los paneles** (cerca de 0,5 % al año).
  - Permitirían mostrar el ahorro en toda la vida del sistema, no solo el tiempo de retorno.
- [ ] **Factor de emisión de CO₂ de la red** (kg CO₂ por kWh, el que publica la UPME).
  - Daría la cifra "evitas X toneladas de CO₂ al año", útil en el pitch.
- [ ] **Precio de venta de excedentes,** si se habilita la autogeneración a pequeña escala.
  - Ver [[adr-0014-dimensionar-por-necesidad-y-mi-sistema]].

## Enlaces
- Relacionada con [[adr-0015-valores-de-referencia]] · [[pitch-primera-etapa]]
