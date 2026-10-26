# H5 — Recorte NC/ND aprobado; H5-A solo factura ficticia

Implementación y evidencias de H5-A: [vertical ficticia cerrada con observaciones](13_H5A_NOTAS_FACTURA_FICTICIAS.md). H5-B permanece sin autorización.

Corte: **26/10/2026, America/Lima**. H4 cerrado. Decisiones posteriores: **recorte NC 01/ND 02 aprobado; H5-A sobre facturas ficticias cerrado formalmente con observaciones y PR #3 fusionado por aprobación expresa**. H5 permanece incompleto. H5-B, H6 y comunicaciones fiscales reales no autorizados. SEE del contribuyente previsto, condicionado a elegibilidad/PSE. D-02 y AF-06 registran la aprobación del recorte; no acreditan validación fiscal de un emisor real.

**Recomendación:** comenzar con **NC tipo 07/motivo 01 (anulación de la operación)** y **ND tipo 08/motivo 02 (aumento en el valor)**, sobre una factura y después sobre una boleta, en entregas separadas. Un comprobante original local por nota, un ítem, PEN, contado e IGV común del ensayo. Son límites del producto aprobados, no una lista de todo lo permitido por SUNAT. Los motivos restantes quedan deshabilitados, sin motor genérico de ajustes.

## Casos y códigos

Los catálogos 09/10 del libro [R1] contienen los códigos siguientes. **Publicado no equivale a vigente:** ND 13 y la nueva denominación de NC 03 están diferidos a 2027; ver Control de Cambios, filas 517–518 y 562.

| Caso/código oficial | Recorte propuesto | Condición y fuente |
|---|---|---|
| NC 01 — Anulación de la operación | **Inicial**, factura y boleta, progresivamente | Operación realmente anulada y sustento; no usar para cambiar cliente ni como baja. Ensayo: reversión completa de importes/ítem del original, sin notas previas. [R1, R2, R3] |
| ND 02 — Aumento en el valor | **Inicial**, factura y boleta, progresivamente | Costo/gasto posterior sustentado y admisible; solo diferencia positiva, no repetir total original ni registrar una nueva venta. Casuística real requiere ratificación fiscal. [R1, R2, R3] |
| NC 06 — Devolución total; 07 — Devolución por ítem; 09 — Disminución en el valor | Diferidos del mínimo inicial | Admitidos en catálogo; requieren devolución/ajuste comprobable, trazabilidad de ítems y saldo disponible. 06 no sustituye automáticamente a 01. [R1, R2] |
| NC 04 — Descuento global; 05 — Descuento por ítem; 08 — Bonificación | Diferidos; **no habilitar sobre boleta de consumidor final** | RCP art. 10.1.4 exige que descuento/bonificación al consumidor final conste en el propio comprobante. El libro marca OBS 4367 para esos motivos sobre boleta; una observación técnica no habilita lo fiscalmente improcedente. [R1, R2] |
| NC 02 — Anulación por error en el RUC; 03 — Corrección por descripción/atención de reclamo | Excluidos inicialmente | Procedimientos excepcionales, identidad/casuística y, cuando corresponda, conformidad. No programar el plazo antiguo de orientación; NC 03 no incluye todavía el cambio de código de producto SUNAT diferido. [R1, R3, R4] |
| NC 10 — Otros conceptos; 13 — Corrección de saldo pendiente/fechas/cuotas | Excluidos | 10 exige fundamento tributario concreto, no es una salida libre. 13 corresponde a factura al crédito, ajena a contado; no equivale a devolver dinero. [R1, R5] |
| NC 11/12 y ND 11/12 — Ajustes de exportación/IVAP | Excluidos de V1 reducida | Operaciones especiales fuera del perfil nacional con IGV común. [R1] |
| ND 01 — Intereses por mora; 03 — Penalidades/otros conceptos | Excluidos inicialmente | Mora fuera del ensayo de contado; penalidades del adquirente por incumplimiento del proveedor constituyen un supuesto distinto. No inferir autorización del solo código. [R1, R2, R3] |
| ND 13 — Penalidades | **No vigente al corte; excluido** | Creación y controles diferidos al 01/01/2027; no confundir con NC 13. [R1, R8] |

## Condiciones y procedimientos separados

**Confirmado:** factura con CDR aceptada y otorgada; boleta otorgada al mismo adquirente [R3, arts. 22.1/23.1]. RC exige referencia informada, no anulada/rechazada [R1, RC filas 49–57]. Correlacionar tipo 01/03, serie/número, emisor, destinatario, motivo y fechas; no editar XML original. Series F para notas de factura y B para notas de boleta, con identidad/correlativo propio por emisor/tipo/serie [R1].

**Controles propuestos:** original otorgado ficticiamente y aceptado simulado; boleta con miembro correlacionado en RC aceptado. Bloquear original ajeno, pendiente, incierto, rechazado, dado de baja o afectado por otra nota. Otorgamiento ficticio explícito: no inferir de firma/ticket/descarga ni alterar históricos. Reserva transaccional por original contra NC/ND incompatibles; replay de la misma nota conserva identidad. Una nota por original evita saldos acumulados inicialmente; no es prohibición SUNAT de emitir varias.

**Importes/ítems del ensayo:** NC 01 reproduce cantidad, unidad, descripción, base, IGV y total positivos del original; el tipo determina el efecto reductor. ND 02 conserva ítem/cantidad/afectación y expresa solo incremento positivo e impuesto. Mismo receptor y PEN. Ejemplo en originales distintos: base 100 + IGV 18 = 118; NC 01 por 118; ND 02 por incremento 20 + 3,60 = 23,60. Tolerancias técnicas no autorizan sobreacreditar. Parciales y acumulación quedan fuera; fiscal ratifica tratamiento real.

**Envío desde emisión de la nota**, no del original: factura D+3 calendario [R6]; boleta mediante RC hasta D+7, con miembros del mismo día de emisión de las notas, aunque los originales tengan otras fechas [R7]. No reabrir RC congelados. Individual B: alternativa D+5 [R9], excluida. Consulta tardía no amplía envío. Examinar constancia correlacionada; ticket/HTTP/98 no son aceptación. Incertidumbre: conservar identidad/bytes/ticket/historial; sin reenvío automático ni recuperación inventada [R10].

**Plazo excepcional:** el último párrafo del art. 22.3, que recogía quince días hábiles del mes siguiente, fue derogado por RS 165-2021 (solo ese párrafo, no todo el artículo 22.3), disposición derogatoria única, p. 13, vigente desde 17/12/2021 [R4]. No adoptar los diez/quince días aún citados en orientación ni trasladarlos a ND. La revisión no confirma otro plazo general sustitutivo ni habilita emisión ilimitada: motivos excepcionales bloqueados hasta validación profesional.

**Procedimientos distintos:** baja de numeración no otorgada, con plazos propios: RA para factura/notas y RC con estado anulado para boleta/notas [R7, art. 14]. NC 01 anula una operación otorgada. Corregir RC/información rechazada no se resuelve mediante nota, reconstrucción incierta o liberación de miembros. Baja y corrección de RC rechazados requieren etapa expresamente autorizada antes de esos flujos reales.

## Primera vertical y pruebas propuestas

1. **Factura + NC 01:** referencia, otorgamiento ficticio y reserva/correlativo; `CreditNote` firmado/ZIP privado, outbox y respuesta síncrona ficticia. Reusar cálculo puro H2 y patrones de identidad/evidencia/recuperación H3.
2. **Factura + ND 02:** original distinto, incremento y `DebitNote`, mismos controles; sin calculadora tributaria general.
3. **Boleta + ambos:** RC nuevos con referencias y miembros explícitos de notas; patrones H4 de congelación/ticket/98/0/99. Extensión acotada tras aprobación y regresión, sin alterar RC existentes.
4. **Pruebas propuestas, no ejecutadas:** firma independiente/alteración; motivos/estados/referencias/ítems/moneda/receptor/fecha/importes inválidos; carrera NC contra ND y replay; caídas/commit/timeout/constancia ajena, ticket perdido/respuesta tardía; D+3/D+4 y D+7/D+8; regresión H1–H4 y preservación. Sin nuevas dependencias/infraestructura ni refactorización preventiva.

## Evidencias y puertas pendientes

Revalidado el libro del 26/08/2026: **855.113 bytes**, SHA-256 `cb5e871cfe3b81abea7faf25b156e5d35b21e8c4837979350919926612da73b6`, idéntico a [la copia existente](evidencias/SUNAT_Reglas_validacion_2026-08-26.xlsx). Localizadores: `Catálogos` A133:B145/A150:B155; NC/ND filas 5–8 (UBL **2.1**, estructura **2.0**, aunque la hoja se llame `2_0`), referencias NC 89–141/ND 89–119; RC 49–57. Control de Cambios 531–539/562–563 contiene reglas futuras de referencia única y controles de montos: **no declararlas vigentes en 2026**. La propuesta de una referencia y límites exactos es un control propio. RC mantiene UBL 2.0/estructura 1.1. [R8] posterga los cambios generales al 01/01/2027; revalidar antes de implementar/liberar, sin adelantar obligaciones futuras.

- **Propietario:** aprobó motivos/exclusiones y posteriormente el cierre H5-A con observaciones y la fusión del PR #3. H5-B requiere nueva decisión expresa; H5 no está completo. AF-06 resuelve selección de motivos, no validación fiscal real ni entrega/custodia.
- **Profesional fiscal + representante:** ratificar causal, IGV/ajustes y elegibilidad/SEE/PSE del emisor real antes de actividad afectada. Excepciones, otros conceptos y operaciones al crédito requieren revisión propia si se amplía alcance.
- **Producto/técnico H6; operación/seguridad H7:** entrega/consulta del receptor, custodia/retención/restauración y acceso real siguen pendientes; no los resuelve el otorgamiento ficticio. Recuperación oficial sin ticket y corrección de RC rechazados conservan sus puertas previas al flujo real; licencia XMldsig antes de distribución.

Solo se consultaron documentos públicos, sin WSDL/SOAP, credenciales, certificados ni servicios SUNAT/Beta/producción. [PDF y metadatos de esta revisión](../storage/audit/h5-preparacion-20261026/fuentes.json), [RS 165 y huella](../storage/audit/h5-preparacion-20261026/165-2021.json). Graphify consultado para reutilización; sin cambios de código, dependencias, infraestructura, BD ni evidencias previas. Sin pruebas de aplicación/CI en esta preparación; implementación y evidencia H5-A se registran por separado.

## Fuentes oficiales

- **R1:** [Portal y libro CPE vigente publicado](https://cpe.sunat.gob.pe/guias-y-manuales), 26/08/2026, celdas y vigencias indicadas arriba.
- **R2:** [RCP, capítulo III](https://www.sunat.gob.pe/legislacion/comprob/regla/capituloIII.pdf), art. 10.1/10.2/10.4, pp. 31–34; distinguir texto vigente de «texto anterior».
- **R3:** [RS 357-2015](https://www.sunat.gob.pe/legislacion/superin/2015/357-2015.pdf), art. 2, pp. 9–11; arts. 22.1/22.3/23.1 de RS 097-2012, con modificaciones posteriores citadas.
- **R4:** [RS 165-2021](https://www.sunat.gob.pe/legislacion/superin/2021/165-2021.pdf), vigencia p. 9, modificatoria tercera y derogatoria única p. 13.
- **R5:** [Informe 000057-2025](https://www.sunat.gob.pe/legislacion/oficios/2025/informe-oficios/i000057-2025-7T0000.pdf), pp. 2–3; [RS 193-2020](https://www.sunat.gob.pe/legislacion/superin/2020/193-2020.pdf), art. 3.2/anexos; código NC 13 y condiciones en R1.
- **R6:** [RS 000003-2023](https://www.sunat.gob.pe/legislacion/superin/2023/000003-2023.pdf), art. 3, numeral 12.1.c, pp. 2–3.
- **R7:** [RS 114-2019](https://www.sunat.gob.pe/legislacion/superin/2019/114-2019.pdf), art. 2.5–2.7, pp. 7–10: baja, otorgamiento y RC.
- **R8:** [RS 000048-2026](https://www.sunat.gob.pe/legislacion/superin/2026/000048-2026.pdf), art. 9, pp. 27–29; [RS 000143-2026](https://www.sunat.gob.pe/legislacion/superin/2026/000143-2026.pdf), art. 3.4, p. 3: vigencia futura, no regla actual por anticipación.
- **R9:** [RS 206-2019](https://www.sunat.gob.pe/legislacion/superin/2019/206-2019.pdf), sexta modificatoria, pp. 41–43; alternativa individual documentada, no elegida.
- **R10:** [Manual del programador SUNAT, mayo 2021](https://cpe.sunat.gob.pe/sites/default/files/inline-files/manual_programador%20%281%29.pdf), §§2.4–2.7, pp. 15–22; [guías NC/ND 2.1](https://cpe.sunat.gob.pe/guias-y-manuales), ejemplos sujetos a R1 y normas: la guía ND contiene una referencia equivocada al catálogo 09; corresponde catálogo **10**.
