---
tipo: moc
creado: 2026-10-10
tags: [moc, idea]
---

# Nuevas funcionalidades · candidatas

Lluvia de ideas del 2026-10-10, cruzada contra los ADR 0001–0030 para no repetir nada construido ni
nada que el asesor ya vetó. Cada idea tiene su nota con el *por qué* y su viabilidad.

El criterio con el que están ordenadas: **la cadena cliente → cálculo → cotización → comparación ya
está completa**. Lo que falta no son más pantallas, son las preguntas que la gente hace *después* de
ver el precio: ¿cómo lo pago?, ¿me salva del corte?, ¿alguien me va a contestar?

> Las notas de tipo `idea` llevan `estado` y `esfuerzo` en el frontmatter, para que esta tabla se arme
> sola. Cuando una se construye, se convierte en ADR y la nota queda apuntando a él.

```dataview
TABLE estado AS "Estado", esfuerzo AS "Esfuerzo", descripcion AS "Qué es"
FROM "05-ideas"
WHERE tipo = "idea"
SORT estado ASC
```

## 🟢 Para el pitch del 15 de octubre
Las tres suman ~1,5 días y convierten la demo de *"calculamos bien"* a *"te dice si puedes pagarlo, si
te salva del corte, y avisa al instalador"*.

- [[financiacion-cuota-vs-ahorro]] — la cuota del crédito contra el ahorro mensual. Es lo que
  destraba la conversación que hoy muere en *"cuesta 25 millones"*.
- [[autonomia-en-un-corte]] — cuántas horas aguanta la casa. El dolor real de La Guajira no es la
  tarifa, son los cortes.
- [[notificaciones-de-la-plataforma]] — sin avisos, el marketplace no funciona: nadie entra solo a
  ver si le llegó algo.

## 🟡 Candidatas (después del pitch)
- [[proyeccion-a-25-anos]] — flujo de caja con degradación y reemplazo del inversor. Credibilidad
  ante quien pone plata, no ante el cliente.
- [[escenarios-on-grid-hibrido-off-grid]] — la pregunta *"¿y con baterías?"*, que hoy no tiene
  respuesta en la app.
- [[polvo-del-sahara-como-perdida]] — el diferencial que nadie puede copiar, porque nadie más tiene
  la estación.
- [[verificar-el-cierre-con-el-cliente]] — el único arreglo honesto al agujero de atribución del
  [[adr-0005-marketplace-de-instaladores]].
- [[calculadora-para-el-instalador]] — rompe el huevo y la gallina del directorio: la suscripción se
  paga sola aunque no lleguen leads.
- [[estudio-imprimible-del-proyecto]] — la decisión no la toma quien usa la app.
- [[compartir-el-proyecto-por-enlace]] — lo mismo, por WhatsApp.
- [[recibo-antes-y-despues]] — el ahorro anual no se siente; el recibo sí.
- [[co2-evitado]] — una hora de trabajo, y es lo que piden instituciones y convocatorias.

## ❄️ Aplazadas, con lo que las desbloquea
- [[dibujar-el-techo-en-el-mapa]] — arregla el peor dato de entrada, pero las teselas actuales no
  traen foto satelital: es decisión de licencia y costo.
- [[tarifa-por-estrato-de-air-e]] — suena a precisión, pero es una tabla que hay que actualizar todos
  los meses y el cliente ya puede escribir la tarifa de su recibo.
- [[monitoreo-de-generacion-real]] — el producto de la segunda etapa y el gancho de retención, pero
  exige APIs de inversores e instalaciones ya cerradas.

## Ya decididas en otro lado (no duplicar aquí)
- **Cupos y planes de la suscripción** → [[adr-0030-anonimato-por-etapas-y-cupos]], con sus preguntas
  de negocio todavía abiertas.
- **Mensajería enmascarada dentro de la app** → aplazada en el mismo ADR-0030, con el argumento ya
  escrito.
- **Alternancia red / fotovoltaico** → [[ideas-aplazadas-asesoria]]; el asesor dijo *no te metas con
  eso todavía*.

## Preguntas abiertas
- ¿Las partículas de la estación de Maicao ya están entrando a la base? De eso depende
  [[polvo-del-sahara-como-perdida]].
- ¿Hay una tasa de crédito real que se pueda citar antes del pitch?
  ([[financiacion-cuota-vs-ahorro]])
- Después del pitch: ¿el siguiente bloque es el negocio
  ([[calculadora-para-el-instalador]], [[verificar-el-cierre-con-el-cliente]]) o el cálculo
  ([[escenarios-on-grid-hibrido-off-grid]], [[proyeccion-a-25-anos]])?

## Relacionado
[[pitch-primera-etapa]] · [[modelo-de-ingresos]] · [[problema-y-propuesta-de-valor]] ·
[[asesoria-felix-bada]] · [[pendientes-de-limpieza]]
