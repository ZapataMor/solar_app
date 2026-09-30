---
tipo: moc
creado: 2026-09-30
tags: [moc]
---

# Natal IA · Inicio

Cerebro del aplicativo solar y de la idea de negocio: qué es, hacia dónde crece y por qué se decide lo que se decide.

## Secciones
- [[06-negocio/idea-de-negocio|Idea de negocio]]
- Estado actual → `01-estado-actual/`
- Producto → `02-producto/`
- Decisiones (ADRs) → `03-diseno/decisiones-adr/`
- Ideas → [[05-ideas/pendientes-de-limpieza|Pendientes de limpieza]]
- Planes → `08-planes/`

## Decisiones (ADRs)
```dataview
TABLE descripcion AS "Decisión", actualizado AS "Fecha"
FROM "03-diseno/decisiones-adr"
SORT file.name DESC
```

## Pendiente por decidir
```dataview
TASK
WHERE !completed
GROUP BY file.link
```

## Últimas notas
```dataview
TABLE tipo, creado
FROM "" AND -"99-plantillas"
SORT file.mtime DESC
LIMIT 10
```
