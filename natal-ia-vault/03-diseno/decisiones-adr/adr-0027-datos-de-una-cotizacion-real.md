---
tipo: adr
descripcion: ADR-0027 — La cotización del instalador pide los datos de una cotización real
estado: 🟢 Aceptada
actualizado: 2026-10-07
---

# ADR-0027 · Datos de una cotización real

- **Estado:** 🟢 Aceptada
- **Fecha:** 2026-10-07
- **Contexto del repo:** `App\Domain\Installers\QuoteInclusions`, `App\Domain\Installers\QuoteSystem`,
  `SendInstallerQuote`, `InstallerQuoteRequest`, tabla `installer_quotes`,
  `installers/quote-request.blade.php`, `installers/quote.blade.php`

## Contexto
El [[adr-0026-cotizacion-del-instalador]] dejó al instalador mandar **un total, una potencia, si
lleva baterías y una fecha**. Eso cierra el círculo, pero no sirve para lo único que el cliente hará
con dos cotizaciones: compararlas. Dos números no se comparan si no se sabe qué hay dentro de cada
uno.

Se revisaron dos fuentes para no inventar los campos:

1. **La ficha técnica de la GIZ "Cotización para Sistemas Fotovoltaicos"** (Santiago de Chile, 2017),
   un modelo referencial dirigido a instaladores para instalaciones de hasta 10 kW. Sus secciones
   son: *datos generales* (empresa, cliente, fecha, número de cotización, responsable), *contenido
   de la oferta* (potencia nominal del generador en kWp, tipo de sistema, tipo de montaje, lugar),
   *texto de la cotización* (incluye el **pronóstico de producción en kWh/año** y el **período de
   amortización**, si se solicitan), *especificaciones de servicio* en cinco áreas —despacho,
   componentes, instalación, otros y opcionales— con una tabla de **código, cantidad, unidad,
   descripción, precio unitario y valor total**, donde cada componente lleva **fabricante, modelo,
   potencia y garantía del fabricante**; *sección final* (subtotales con y sin IVA, **validez**,
   **forma de pago y descuentos**, firma) y *anexos* (fichas técnicas y certificaciones).
2. **La práctica colombiana.** Una cotización seria aquí se divide en equipos, diseño, instalación,
   **legalización y conexión** (trámite ante el operador de red, inspección, certificación RETIE,
   medidor bidireccional), monitoreo, **garantías por escrito** (25–30 años en paneles, 5–10 en el
   inversor, 1–2 en la obra), mantenimiento, **forma de pago** (30–50 % de anticipo, el resto contra
   avance y contra energización) y **vigencia de 15 a 30 días**, con ajuste por TRM. El trámite ante
   el operador de red cuesta entre $2 y $5 millones y el medidor bidireccional entre $1,5 y $3: una
   cotización que los deja afuera **no es más barata, es más corta**.

## Decisión
- **La cotización pide lo que una cotización real dice**, en cinco secciones que son las de la ficha
  de la GIZ: el precio, el sistema, qué cubre el precio, garantías y condiciones.
  - *El precio:* total, **IVA** (incluido, aparte o sin decir) y hasta cuándo vale.
  - *El sistema:* cuántos paneles, de cuántos W, potencia total, **marca y referencia de paneles e
    inversor**, capacidad de baterías y **producción prometida en kWh/mes**.
  - *Qué cubre el precio:* RETIE, trámite con el operador de red, medidor bidireccional, baterías y
    mantenimiento del primer año.
  - *Garantías:* años de paneles, de inversor y de obra.
  - *Condiciones:* anticipo en %, plazo hasta energizar, qué incluye y **qué no incluye**.
- **Solo el total y la validez son obligatorios.** Todo lo demás es opcional: el instalador contesta
  desde el celular y debe poder mandar el precio ya y completar después. Un campo vacío **borra** lo
  que había, porque corregir una cotización también es quitar algo.
- **Campos fijos, no ítems.** La ficha de la GIZ cotiza por ítems con precio unitario, y eso es el
  [[adr-0004-cotizacion-por-items-y-transporte-interno]]. Dos instaladores nombran sus ítems distinto,
  así que una tabla libre no se compara; un campo fijo sí. Los ítems quedan para cuando cada empresa
  cargue su propio catálogo.
- **Los paneles y la potencia son el mismo número dicho dos veces** (`QuoteSystem::powerKw`): el
  instalador escribe el que tenga a mano y la app completa el otro. Lo que él escribió gana.
- **Una sola lista de inclusiones** (`QuoteInclusions`) con tres textos por ítem: cómo lo marca el
  instalador, por qué importa, y **qué significa para el cliente que no esté**. Así las dos pantallas
  no se separan nunca.
- **Sin RETIE o sin trámite, la página lo advierte** antes de que el cliente compare el total con
  otro (`missesLegalization`). La app no dice que la cotización sea mala: dice que no está completa.
- **Nada de esto es obligatorio hacia atrás.** Las columnas son nulables y las cotizaciones
  anteriores siguen valiendo: dicen menos, y la página pregunta lo que falta en *Qué preguntar antes
  de firmar*, que se arma con los campos vacíos.

## Consecuencias
- ➕ Dos cotizaciones del mismo techo por fin se pueden poner al lado
  ([[adr-0028-comparador-de-cotizaciones]] lo hará).
- ➕ El cliente ve dónde está la diferencia real: casi nunca es el total, es el trámite, el medidor o
  los años de garantía.
- ➕ El instalador que cotiza bien se nota, que es el incentivo que el [[adr-0005-marketplace-de-instaladores]]
  necesita.
- ➖ El formulario pasó de cinco campos a veinte. Se mitiga con secciones y con que casi todo sea
  opcional, pero sigue siendo más largo de llenar.
- ⚠️ La app **no verifica nada**: si el instalador marca RETIE y no lo hace, la app lo repite. Es una
  declaración suya, no un certificado.
- ⚠️ Los textos de la ficha (*cuesta entre $1,5 y $3 millones*) envejecen. Están en un solo sitio,
  `QuoteInclusions`, para que se actualicen en un solo sitio.

## Alternativas consideradas
- **Dejar solo el total y un texto libre** — es lo que había. Se lee bien y no se compara con nada:
  cada instalador escribe lo que quiere y el cliente no sabe qué falta en cuál.
- **Copiar la tabla de ítems de la GIZ** — el detalle completo, pero obliga a cada instalador a
  desglosar precios unitarios para responder una solicitud, y nombra los ítems a su manera. Es el
  ADR-0004 y sigue pendiente.
- **Adjuntar el PDF de la cotización** — lo más fiel a lo que el instalador ya tiene hecho, pero un
  PDF no se compara, no se recalcula y no se puede mostrar en la página; además abre subida de
  archivos. Puede sumarse después como anexo, nunca como reemplazo de los campos.

## Relacionado
[[adr-0026-cotizacion-del-instalador]] · [[adr-0028-comparador-de-cotizaciones]] ·
[[adr-0004-cotizacion-por-items-y-transporte-interno]] · [[adr-0023-bandeja-de-solicitudes-del-instalador]] ·
[[adr-0024-precios-por-municipio-administrables]]

## Fuentes
- GIZ, *Cotización para Sistemas Fotovoltaicos · Ficha técnica*, Santiago de Chile, enero 2017 —
  https://energypedia.info/images/1/1e/Ficha_Cotizaci%C3%B3n_para_sistemas_fotovoltaicos.pdf
- Evalgroup, *Mejor empresa de paneles solares en Colombia: 12 preguntas clave* —
  https://www.evalgroup.com.co/blog/mejor-empresa-paneles-solares-colombia/
- OPS Colombia, *¿Cuánto cuesta realmente un sistema solar en Colombia?* —
  https://www.opscolombia.com/blog/cuanto-cuesta-realmente-un-sistema-solar-en-colombia
