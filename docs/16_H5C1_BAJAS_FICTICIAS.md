# H5-C1 — bajas ficticias (en implementación, sin cierre)

Autorización expresa del propietario: RA para 01/07/08 F y RC/3 para 03/07/08 B propios, aceptados ficticiamente y con no otorgamiento acreditado. H5-A/B siguen cerrados. H5-C2 (correcciones, reemisión, liberación de reservas), H6 y comunicaciones fiscales reales no autorizados. Rama `codex/h5c1-bajas-ficticias`; PR Draft, sin fusión automática.

## Regla temporal documentada antes de programar

Corte 26/10/2026, SEE-Del contribuyente previsto. El art. 14 de la RS 097-2012, sustituido por [RS 114-2019, art. 2.5, pp. 7–8](https://www.sunat.gob.pe/legislacion/superin/2019/114-2019.pdf) y modificado por [RS 206-2019, art. 3.7](https://www.sunat.gob.pe/legislacion/superin/2019/206-2019.pdf), permite dar de baja documentos **no otorgados**: RA para facturas/notas F previamente enviadas; RC de la fecha de emisión para boletas/notas B. Para los enviados/informados con CDR aceptada, el máximo es **siete días calendario contados desde el día siguiente de recibir esa CDR**: recepción D → último día D+7, inclusive, en fecha local Lima. La rama de no informados queda excluida.

La fecha de emisión del CPE, la fecha de generación del CPE, la generación de RA/RC y la recepción de CDR son hechos distintos. `ReferenceDate` identifica los documentos (RA: generación; RC: emisión); `IssueDate` es la generación de la comunicación. Ninguna es por sí misma recepción de CDR. La POC conserva las cuatro fechas y la prueba del resultado aceptado. Los eventos locales de aceptación correlacionados y su `created_at` acreditan **recepción simulada**, sin convertirlos en CDR oficial; `updated_at`, ticket, acuse de envío o silencio no la acreditan. Si falta el evento o no es consistente, se bloquea; no se completa ningún histórico.

[Libro oficial publicado 26/08/2026](https://cpe.sunat.gob.pe/guias-y-manuales), copia/huella S4 de [preparación](15_H5C_PREPARACION_FISCAL.md): RA fila 44 y RC fila 37, error 2957, contienen un control desde **emisión**. Cambios 394–399 incluye una indicación OSE. Pendiente B-01: fiscal + representante deben confirmar su aplicación al SEE previsto. **Política conservadora de esta POC:** exigir simultáneamente recepción simulada D+7 y emisión D+7; la segunda restricción es un bloqueo técnico propio, no una declaración de vencimiento legal. RA solo con generación y emisión coincidentes acreditadas; no inventar una equivalencia universal.

[RS 000048-2026, art. 9, p. 28](https://www.sunat.gob.pe/legislacion/superin/2026/000048-2026.pdf) cambia el cómputo a generación, pero [RS 000143-2026, art. 3.4, p. 3](https://www.sunat.gob.pe/legislacion/superin/2026/000143-2026.pdf) difiere su vigencia al **01/01/2027**. No anticiparla. La política del ensayo se limita al corte 2026; desde 2027 se bloquea la preparación/envío hasta revisión expresa. Las consultas de comunicaciones ya enviadas pueden continuar: el vencimiento no autoriza reenviar ni descartar respuestas tardías.

RC/3 utiliza identidad propia de cada CPE y operación 3, importes originales congelados; para notas, se conserva también la referencia 03 original. El libro RC filas 49–57 exceptúa operación 3 de determinadas obligatoriedades de `BillingReference`; conservar la referencia existente no significa copiar automáticamente el contrato de RC/1. RA UBL 2.0/1.0; RC UBL 2.0/1.1. XML firmado localmente no acredita aceptación SUNAT.

## Entregas y criterios

1. Preparación fiscal y preservación: regla anterior, decisiones separadas, snapshot de BD/archivos antes de migrar; ningún histórico reescrito.
2. Elegibilidad y preparación: afirmaciones de otorgamiento auditables, desconocido bloquea, fechas acreditadas, una reserva por documento, identidad/miembros/manifest inmutables, firma/ZIP privados y outbox en la misma transacción. Bloquear originales con notas y carreras baja/nota/otorgamiento.
3. Transporte exclusivamente local: un envío como máximo, ticket ficticio, consultas acotadas 98/0/99, constancia ficticia íntegra y correlacionada, incertidumbre sin reenvío; rechazo mantiene documento y reserva. Sin recuperación por UUID cuando falta ticket.
4. Verificación: negativos, firmas, concurrencia, idempotencia, SIGKILL antes/después del commit, plazos acreditados, preservación, regresión H1–H5-B y CI remoto. Resultados/commit/PR se añadirán con evidencia real.

Pendientes conservados: B-01 y elegibilidad/PSE/validación fiscal (profesional tributario + representante, antes de actividad real); seguridad/autenticación local, operación/respaldos en H6/H7 (técnico + propietario); licencia XMldsig antes de distribución. H5-C2 exige autorización posterior. No declarar H5 global completo.
