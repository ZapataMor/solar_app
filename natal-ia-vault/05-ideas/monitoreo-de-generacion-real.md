---
tipo: idea
descripcion: Leer la generación real del inversor ya instalado y compararla con lo que la app estimó
estado: ❄️ Aplazada — es el producto de la segunda etapa
esfuerzo: meses
creado: 2026-10-10
tags: [idea, aplazada, clima, negocio]
---

# Monitoreo de generación real

**Qué es:** una vez instalado el sistema, la app lee del inversor lo que **de verdad** generó y lo
compara con lo que había estimado.

## Por qué importa más de lo que parece
Es el **gancho de retención** que a la plataforma le falta por completo. Hoy el recorrido termina
cuando el cliente elige una cotización: después de eso, la app no tiene ninguna razón para que alguien
vuelva a abrirla. Con monitoreo, el cliente entra todos los meses durante 25 años.

Y es lo que **cierra el círculo del cálculo**: cada instalación monitoreada es una validación de los
supuestos —el factor de suciedad, las pérdidas, las horas de sol— y con eso la estimación de los
proyectos nuevos mejora sola. Ninguna competencia que solo calcula puede hacer eso.

Ya estaba anotada por el equipo en [[ideas-aplazadas-asesoria]], con un giro comercial: ofrecerlo
**como servicio a los instaladores** para los clientes que ellos instalaron, usando la irradiancia de
las estaciones como referencia de si un sistema está rindiendo poco.

## Por qué está aplazada
**Son meses, no días.** Exige integrar las APIs de los fabricantes de inversores —Growatt, Huawei
FusionSolar, SolarEdge, Deye— y cada una es un adaptador distinto, con sus credenciales, sus límites y
su documentación. Además supone algo que todavía no existe: **instalaciones hechas a través de la
plataforma**. Sin una sola instalación cerrada, no hay nada que monitorear.

El orden correcto es: primero que el marketplace cierre negocios
([[verificar-el-cierre-con-el-cliente]]), después monitorear lo que se cerró.

## Qué la desbloquearía
- Instalaciones reales cerradas en la plataforma.
- Un fabricante dominante entre los instaladores aliados: integrar **uno** es viable, integrar cuatro
  no.
- Decidir si lo paga el cliente, el instalador, o es parte de la suscripción.

## Lo que sí cabe ahora
En el pitch va **como hoja de ruta**, no como función: *"la segunda etapa mide lo que el sistema de
verdad produce"*. Es la respuesta natural a *"¿y cómo sé que su cálculo es bueno?"*.

## Relacionado
[[ideas-aplazadas-asesoria]] · [[polvo-del-sahara-como-perdida]] ·
[[verificar-el-cierre-con-el-cliente]] · [[calculadora-para-el-instalador]] ·
[[adr-0008-datos-climaticos-en-pestanas]] · [[modelo-de-ingresos]] · [[nuevas-funcionalidades]]
