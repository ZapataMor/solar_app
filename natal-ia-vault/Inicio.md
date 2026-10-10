---
tipo: moc
creado: 2026-09-30
tags: [moc]
---

# Natal IA · Inicio

Cerebro del aplicativo solar y de la idea de negocio: qué es, hacia dónde crece y por qué se decide lo que se decide.

## Por revisar
- [ ] [[06-negocio/idea-deducida|Idea deducida]]
- [ ] [[02-producto/mapa-de-modulos|Mapa de módulos]]
- [ ] [[00-fuentes/asesoria-felix-bada|Asesoría con Félix Badá]] → ideas por categoría

## Secciones
- [[06-negocio/idea-de-negocio|Idea de negocio]]
- Estado actual → `01-estado-actual/`
- Producto → `02-producto/`
- Decisiones (ADRs) → `03-diseno/decisiones-adr/`
- Ideas → [[05-ideas/nuevas-funcionalidades|Nuevas funcionalidades]] · [[05-ideas/pendientes-de-limpieza|Pendientes de limpieza]]
- Planes → `08-planes/`

## Decisiones (ADRs)
```dataview
TABLE estado AS "Estado", descripcion AS "Decisión", actualizado AS "Fecha"
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
