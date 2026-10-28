# H5-C — preparación fiscal y decisiones pendientes

**Preparación aprobada con condiciones; H5-C1 cerrado con observaciones por decisión posterior del 28/10/2026.** Corte: 26/10/2026, America/Lima. Base `b434e80083f83e2ddbfec480ac0e53d2834545f6`. H5-A/B cerrados; H5 global incompleto. SEE-Del contribuyente previsto, condicionado a elegibilidad/PSE. Bajas ficticias en H5-C1; correcciones/reemisión en H5-C2 sin autorización. La aplicación fiscal real sigue pendiente.

| Procedimiento | Confirmado oficialmente | Recorte V1 propuesto / riesgo |
|---|---|---|
| NC 07/01 | Anulación de operación sobre original otorgado y elegible; factura aceptada. [S5] | Conservar H5-A/B. No sustituye baja, corrección de envío ni saneamiento de rechazo. |
| Baja factura 01 / notas 07–08 F | No otorgadas y previamente enviadas; RA. No registrado/rechazado/ya dado de baja tienen impedimentos técnicos. [S1, S2, S4] | Incluir solo aceptación correlacionada y no otorgamiento acreditado; bloquear dependencias incompatibles. No borrar originales. |
| Baja boleta 03 / notas B | No otorgadas; RC de su fecha de emisión, operación 3. La norma contempla no informadas, pero RC/2663 exige existencia. [S3, S4] | Incluir inicialmente solo registradas aceptadas, no otorgadas. No informadas: diferir hasta aclaración. |
| RC rechazado | Manual §4.1: rechazo íntegro, sin procesamiento parcial ni invalidación de numeraciones; permite repetir nombre del archivo. [S8] | Nuevo RC/1 por elección conservadora, solo error técnico subsanable y todos los miembros no informados previamente. Mismos CPE/fecha/XML; nuevo ID de RC y enlace local al anterior. Bloquear duplicados/estados contradictorios. |
| RC previamente aceptado | Otro RC sustituye/rectifica solo los documentos modificados; mismo día de emisión. Operación 2 exige existencia. [S1, S4] | Diferir rectificación de información aceptada y cambios fiscales; no confundir con corrección de sobre rechazado ni alterar XML históricos. |
| Factura con rechazo fiscal definitivo | Manual §4.1: numeración consumida, documento nuevo y nuevo número; una excepción de recepción es otro supuesto. [S8, S7] | Incluir reemisión manual acotada: operación subsiste, rechazo/subsanación comprobados, nueva numeración y trazabilidad local. No reiniciar obligaciones/plazos de la operación. |
| Notas rechazadas / boletas registradas como rechazadas / incertidumbre | El manual también exige nuevo número para notas de factura rechazadas; no autoriza automáticamente cualquier nueva nota o boleta. [S8, S4] | Diferir reemisión de notas/boletas: conservar reserva del original. Timeout, duplicado o ausencia de constancia/ticket no habilitan nuevo correlativo. Resolver caso fiscal antes de actividad real afectada. |

## Plazos, referencias y resultados

Al corte: RA hasta D+7 desde recepción de CDR aceptada; RC de baja informado, desde CDR aceptada; no informado, desde generación. Siempre documento no otorgado y agrupación por fecha. [S1, S3] RC ordinario: D+7 desde emisión. La rectificación tardía del art. 21.1.c no prorroga toda primera comunicación. [S1]

**B-01 — discrepancia pendiente:** libro RA fila 44/RC fila 37 controla D+7 desde emisión; Cambios 394–399 contiene reglas de 2021 y una indicación específica OSE. No asumir aplicación idéntica al SEE previsto. RS 000048-2026 art. 9 cambia el cómputo a generación, pero RS 000143-2026 art. 3.4 lo posterga al **01/01/2027**. No adelantar esa norma. [S4, S9, S10] Propuesta ficticia: positivos dentro de ambas ventanas; fuera de su intersección, bloqueo técnico/escalamiento, no declarar vencido el plazo legal por ese solo control. Fiscal + representante ratifican modalidad/vigencia antes del uso real.

RA: `VoidedDocuments`, UBL 2.0/estructura 1.0, tipo/serie/número y motivo. RC: UBL 2.0/1.1. Código 1 no admite ya informados (2282); 2/3 exige existencia (2663); rechazado/anulado tiene restricción 2987. Un RC rechazado no borra aceptaciones de otros envíos. No decidir por rango numérico del error: 1032/1033/2282/2987 pueden reflejar registro previo; examinar causa, documento y estado. En notas, `BillingReference` referencia el comprobante afectado, no el RC anterior; baja/3 tiene condiciones distintas (filas 49–57). [S4]

Ticket/98 no acredita aceptación. Para RA/RC, examinar constancia correlacionada al 0/99; rechazo de baja no da de baja el original. Recuperación oficial asíncrona documentada por ticket, sin inventar consulta por UUID. El manual permite reutilizar nombre, mientras el libro contiene controles de archivo repetido: no universalizar ese permiso; identidad nueva es nuestra propuesta. [S8, S4]

## Indispensables, pruebas y aprobación

**Recomendación mínima:** bajas RA/RC elegibles, corrección técnica de RC y reemisión manual de factura en los términos de la tabla. RP-05/Plan H5 incluyen bajas/correcciones: excluirlas totalmente requiere cambiar expresamente el alcance aprobado. El manejo seguro de rechazos es indispensable. Pueden diferirse rectificaciones aceptadas, reemisión de notas/boletas y supuestos no informados, solo con exclusión aprobada y procedimiento fiscal/operativo resuelto antes del caso real; de lo contrario V1 no está lista para ese uso. No presentar el recorte como cobertura fiscal completa.

**B-02:** acreditar otorgamiento del documento objeto de baja; el del original no prueba el de la nota y dato ausente significa desconocido. **B-03:** clasificar causa/constancia y registro previo de cada miembro; el registro ficticio no demuestra consulta oficial disponible. Propietario aprueba causas/exclusiones; fiscal + representante validan B-01/B-03 para actividad real; técnico diseña los bloqueos.

Otra comunicación controlada requiere ampliar expresamente las reservas H5-A/B, no eliminar unicidades ni borrar membresías históricas. Mantener XML/ZIP/huellas/tickets y cadena de intentos; referencia de reemplazo local, sin inventar un campo UBL. Reutilizar PostgreSQL/outbox/firmas; sin infraestructura ni motor genérico.

Pruebas futuras: otorgado/no otorgado/desconocido; D+7/D+8 y vigencia 2026/2027; RC con miembros ausentes/informados/rechazados; 1/2/3 incompatibles; 0/98/99/constancia inválida; carreras baja/nota/otorgamiento y reemisión; idempotencia, SIGKILL, respuesta tardía/ticket perdido, rollback y preservación; regresión H1–H5-B/CI. Cierre: evidencias reproducibles, exclusiones/pendientes aprobados y confirmación expresa. Sin pruebas/CI nuevos en esta preparación.

## Fuentes oficiales verificadas

- **S1:** [RS 114-2019](https://www.sunat.gob.pe/legislacion/superin/2019/114-2019.pdf), art. 2.5–2.7, pp. 7–10: arts. 14/15/21 del SEE-Del contribuyente.
- **S2:** [RS 206-2019](https://www.sunat.gob.pe/legislacion/superin/2019/206-2019.pdf), art. 3.7, pp. 23–24: baja de factura/notas y prohibición si no fueron enviadas.
- **S3:** [SUNAT, boleta electrónica](https://cpe.sunat.gob.pe/tipos_de_comprobantes/boleta), comunicación de baja; distinguir la orientación de la norma/vigencia aplicable.
- **S4:** [Portal y libro de validaciones publicado 26/08/2026](https://cpe.sunat.gob.pe/guias-y-manuales), revalidado idéntico a [copia existente](evidencias/SUNAT_Reglas_validacion_2026-08-26.xlsx), SHA-256 `cb5e871cfe3b81abea7faf25b156e5d35b21e8c4837979350919926612da73b6`, 855.113 bytes. Localizadores: RA 5–12/24–52; RC 9–14/28–39/49–57/76–77; Factura/NC/ND 13–14; Cambios 394–399/562–563.
- **S5:** [RS 357-2015](https://www.sunat.gob.pe/legislacion/superin/2015/357-2015.pdf), art. 2, arts. 22/23; alcance NC 01/ND 02 y reservas conservados en [preparación H5](12_H5_PREPARACION_FISCAL.md).
- **S6:** [SUNAT, operatividad](https://orientacion.sunat.gob.pe/3529-operatividad), apartados CDR y RC rechazado. No adoptar sus plazos antiguos de envío individual; contrastar con [RS 000003-2023](https://www.sunat.gob.pe/legislacion/superin/2023/000003-2023.pdf), art. 3 (D+3).
- **S7:** [RS 000302-2024](https://www.sunat.gob.pe/legislacion/superin/2024/000302-2024.pdf), art. 4.2, p. 7: recepción previa de igual numeración. No es una autorización universal de reemisión.
- **S8:** [Manual del programador SUNAT](https://cpe.sunat.gob.pe/sites/default/files/inline-files/manual_programador%20%281%29.pdf), §§2.4–2.7 y 4.1, pp. 15–24 y 28–29 (revisión y reenvío según tipo de respuesta).
- **S9/S10:** [RS 000048-2026](https://www.sunat.gob.pe/legislacion/superin/2026/000048-2026.pdf), art. 9, p. 28; [RS 000143-2026](https://www.sunat.gob.pe/legislacion/superin/2026/000143-2026.pdf), art. 3.4, p. 3: vigencia diferida.

Copias/huellas de esta revisión en `storage/audit/h5c-preparacion/fuentes.json`. Solo se descargaron documentos públicos; sin WSDL/SOAP, credenciales ni llamadas fiscales. Pendientes H6/H7, elegibilidad/PSE y licencia XMldsig permanecen con sus responsables y puertas anteriores. **El propietario aprobó el recorte condicionado: H5-C1 cerrado con observaciones y PR #5 fusionado por aprobación expresa según [regla previa, entregas y registro de cierre](16_H5C1_BAJAS_FICTICIAS.md); H5-C2 no autorizado. H5 global no cerrado.**
