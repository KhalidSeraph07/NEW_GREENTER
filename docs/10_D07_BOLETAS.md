# D-07 — Ruta de boletas aprobada para el diseño V1

Corte: **25/10/2026, America/Lima**. Decisiones posteriores del propietario: D-07 aprobada para V1 y H4 autorizado exclusivamente como vertical ficticia, en rama/PR Draft sin fusión automática. H3 cerrado; H5 y comunicaciones SUNAT/Beta/producción no autorizados. Sin certificados ni credenciales fiscales reales.

**Recomendación:** **boleta → resumen diario RC → ticket → consulta → resultado correlacionado**, como única ruta V1 del **SEE del contribuyente directo previsto**, condicionado a D-03. Sin envío individual ni cambio automático de canal. Ruta única permite varios lotes inmutables del mismo día. Aprobada por el propietario para el diseño V1; ratificación fiscal/aplicabilidad real pendientes.

## Lo confirmado por documentación oficial

| Procedimiento | Aplicabilidad y límite | Fuente/localizador |
|---|---|---|
| Resumen diario | Boletas no remitidas individualmente; una misma fecha de emisión por resumen, uno o más resúmenes por fecha. Envío en D o hasta D+7 calendario. Cambios posteriores afectan solo documentos modificados, no sustituyen indiscriminadamente todo el día. | [RS 114-2019](https://www.sunat.gob.pe/legislacion/superin/2019/114-2019.pdf), art. 2.1 (7.3), pp. 4–5; art. 2.7 (21.1), pp. 9–10. |
| Ejemplar individual, alternativa no elegida | Es opción, no prohibición ni obligación general. Ventana D a D+5 calendario; después solo resumen en su propia ventana, sin sumarle otros siete días. La CDR individual tiene el régimen del art. 13.b; no se presupone idéntica al de factura. | [RS 206-2019](https://www.sunat.gob.pe/legislacion/superin/2019/206-2019.pdf), sexta modificatoria 6.1–6.2, pp. 41–43. |
| Otros supuestos | Baja de boleta no otorgada: RC, con condiciones/plazos propios; no RA de factura. Contingencia impresa BVE-NE: otro procedimiento. Ambos fuera de esta vertical. Si corresponde SEE-OSE, reabrir D-03/D-07. | RS 114-2019, arts. 1.2 y 2.5 (14.2), pp. 3–4 y 7–9; modalidad condicionada por adenda. |

D es la fecha de emisión del comprobante; ejemplo 25/10/2026: D+5 = 30/10 y D+7 = 01/11, incluidos fines de semana. Son ventanas de envío, no un plazo para obtener la CDR. El otorgamiento/entrega al receptor y la información a SUNAT son hechos distintos (RS 114-2019, art. 2.6).

Vigencia: [RS 000048-2026](https://www.sunat.gob.pe/legislacion/superin/2026/000048-2026.pdf), art. 9 y anexos, contiene cambios posteriores; [RS 000143-2026](https://www.sunat.gob.pe/legislacion/superin/2026/000143-2026.pdf), art. 3.4, posterga su vigencia general al **01/01/2027**, con excepciones allí indicadas. No anticipar esos cambios por figurar en un archivo publicado en 2026; revalidar antes de esa fecha o de uso real.

Contrato técnico: [Manual SUNAT, mayo 2021](https://cpe.sunat.gob.pe/sites/default/files/inline-files/manual_programador%20%281%29.pdf), §§2.5–2.7.1, pp. 17–22: `sendSummary` devuelve ticket; `getStatus(ticket)` devuelve proceso `98` pendiente, `0` procesado, `99` con errores y contenido cuando corresponda. Debe examinarse la constancia. `getStatusCdr` enumera 01/07/08; no acredita recuperar RC/ticket por UUID/RUC/fecha. Disponibilidad actual no probada. La [orientación SUNAT](https://orientacion.sunat.gob.pe/04-resumen-diario-boleta-de-venta-electronica) confirma constancia aceptada/rechazada con motivo.

Se revalidó el [libro oficial 26/08/2026](https://cpe.sunat.gob.pe/sites/default/files/2026-08/Reglas%20de%20validaci%C3%B3n%20-%20actualizado%20al%2026.08.2026.xlsx): idéntico a F23, SHA256 `cb5e871cfe3b81abea7faf25b156e5d35b21e8c4837979350919926612da73b6`. Localizadores: `Boleta2_0`!K18:L18, 1079; `Resumen Diario1_1`!G6:G8 y K11:L14, versiones/fechas/observación 4443; `CódigosRetorno`!A16:B18, A59:B59 y A362:B362, ticket/duplicación. Una observación por tardanza no amplía el plazo ni garantiza rechazo automático. [Guías publicadas](https://cpe.sunat.gob.pe/guias-y-manuales): boleta UBL 2.1, RC UBL 2.0/estructura 1.1. Sin ejecutar validador oficial.

## Tratamiento propuesto del producto — no reglas tributarias inventadas

| Evidencia recibida o fallo | Tratamiento conservador propuesto |
|---|---|
| Ticket correlacionado | Conservarlo transaccionalmente junto con identidad/hash del RC y miembros; estado pendiente, sin aceptación. |
| `98`, fallo de consulta o constancia incompleta/ajena | Mantener pendiente/incierto; consultar el mismo ticket con espera e intentos acotados. Escalar al agotar el presupuesto técnico; no rechazar por silencio ni reenviar RC. |
| Constancia aceptada y correlacionada | Marcar las boletas del manifiesto como informadas por ese RC; conservar advertencias y evidencia. No crear una CDR individual inexistente. |
| Rechazo confirmado | Conservar motivo/constancia; no marcar informadas ni renumerar/reemitir boletas automáticamente. Corrección de RC requiere revisión y nueva identidad/versión, conservando el original; nunca reenviar idéntico indefinidamente. |
| Ticket perdido/inexistente/ajeno, envío incierto o duplicación | Detener reenvíos y nuevos lotes solapados. Conservar evidencia, escalar a soporte/representante/fiscal. AF-04 abierto: no hay recuperación sin ticket oficialmente comprobada. |
| Ventana vencida | Alertar/escalar, sin falsear fechas ni equiparar intento local a cumplimiento. Conservar/consultar constancias posteriores. Separar resultado y cumplimiento temporal; rectificación tardía no prorroga la primera comunicación. |

## Primera vertical ficticia autorizada

Reutilizar cálculos de ensayo, acceso/propiedad, archivos privados y firma H2; reserva durable, idempotencia, eventos y concurrencia H3. Añadir boleta 03/serie B ficticia y RC con miembros explícitos, fechas diferenciadas, correlativo propio, ticket y outbox. Migraciones aditivas preservando F000/facturas/evidencias; sin Redis, nuevo scheduler ni rediseño UI. Perfil ficticio: nacional, PEN, contado, operación común de ejemplo.

Simular ticket, 98→aceptación/rechazo, consultas fallidas, respuestas incompletas/ajenas, duplicación, timeout y ticket perdido. **No reutilizar lookup H3 por UUID como recuperación oficial sin ticket.** Probar miembros únicos, lotes del mismo día, fechas mezcladas, D+7, interrupciones y concurrencia; regresiones H1–H3. Todo ficticio/sin CDR oficial. Solo altas: no notas, modificación/anulación ni H4 completo.

**Puertas pendientes:** propietario ya aprobó D-07 y autorizó la vertical ficticia H4; cierre H4 requiere aprobación posterior. Fiscal ratifica ruta/casuística/vigencia; representante acredita elegibilidad/modalidad y RUC/series. PSE/D-03 y AF-04 bloquean actividad real afectada, no por sí solos el futuro ensayo ficticio. Antes de operar: contrato/XSD/firma/reglas, entrega/consulta del receptor (H4/H6), custodia/restauración/retención (H7) e incidentes efectivos validados.

**Estado: D-07 aprobada para diseño V1; H4 ficticio autorizado, cierre pendiente. Alternativa individual documentada sin implementación. H5 y comunicaciones fiscales reales prohibidos.**
