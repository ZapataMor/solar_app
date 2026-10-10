---
tipo: idea
descripcion: Convertir los kWh generados en CO₂ evitado, con el factor de emisión del SIN colombiano
estado: 🟡 Candidata
esfuerzo: ~1 hora
creado: 2026-10-10
tags: [idea, producto]
---

# CO₂ evitado

**Qué es:** los kWh que genera el sistema, multiplicados por el factor de emisión de la red
colombiana, dan las toneladas de CO₂ que el proyecto evita al año.

## Por qué
A una casa le interesa poco; **a una institución y a una empresa les interesa mucho**, y son dos de
los tres tipos de inmueble que la app ya maneja. Un colegio, una alcaldía o un hotel necesitan esa
cifra para su informe de sostenibilidad, y hoy tienen que calcularla aparte.

Es también la cifra que piden las convocatorias y los jurados de impacto, y es la más barata de todo
este listado.

## Viabilidad
- **Dónde va:** una multiplicación en dominio sobre la generación que ya se calcula. **El factor de
  emisión es un [[adr-0015-valores-de-referencia|valor de referencia]]**, porque cambia cada año
  según cuánto hidro y cuánto térmico tuvo el país —en año de El Niño sube bastante— y porque la
  fuente oficial lo publica con vigencia, que es exactamente lo que esa tabla modela.
- **Esfuerzo:** ~1 hora.
- **Riesgo:** casi ninguno, salvo clavar el factor como constante. Si se clava, en un año queda
  desactualizado y nadie se acuerda de dónde salió.

## Por decidir
- [ ] Qué factor de emisión y de qué fuente oficial (la UPME publica el del SIN).
- [ ] Si se muestra a todos o solo a empresa e institución.

## Relacionado
[[adr-0015-valores-de-referencia]] · [[adr-0014-dimensionar-por-necesidad-y-mi-sistema]] ·
[[clientes-y-usuarios]] · [[valores-de-referencia-candidatos]] ·
[[estudio-imprimible-del-proyecto]] · [[nuevas-funcionalidades]]
