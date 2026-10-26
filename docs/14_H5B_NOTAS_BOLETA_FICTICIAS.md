# H5-B — notas sobre boletas y RC ficticios

Estado: autorizado para implementación y revisión; no cerrado. Base `1d8ac51acc9f6e334aba129acb3f41b626e48ecd`. H5-A cerrado con observaciones, sin reapertura. H6 y toda comunicación/identidad fiscal real continúan prohibidos.

## Comprobación fiscal previa al código RC (26/10/2026)

Se consultó el [portal SUNAT](https://cpe.sunat.gob.pe/guias-y-manuales) y se descargó nuevamente el [libro publicado el 26/08/2026](https://cpe.sunat.gob.pe/sites/default/files/2026-08/Reglas%20de%20validaci%C3%B3n%20-%20actualizado%20al%2026.08.2026.xlsx): 855.113 bytes, SHA-256 `cb5e871cfe3b81abea7faf25b156e5d35b21e8c4837979350919926612da73b6`, idéntico a la copia conservada. Hoja `Resumen Diario1_1`, filas 6–14, 28–39, 49–57, 76–80. Metadatos locales: `storage/audit/h5b/fuentes.json`.

La [guía RC versión 2, enero 2018, publicada actualmente](https://cpe.sunat.gob.pe/sites/default/files/inline-files/GUIA_Resumen_de_Boletas_11-01-2018%20%282%29_2_0%20%281%29_0.pdf), pp. 5, 9, 11–13, distingue la identidad de la nota (tipo 07/08, serie B y número) de la boleta afectada: `SummaryDocumentsLine/BillingReference/InvoiceDocumentReference/{ID,DocumentTypeCode}`. Se usará tipo afectado 03, no se deducen campos de la línea 03. UBL 2.0 / CustomizationID 1.1; ReferenceDate es la fecha de las notas y IssueDate la generación del nuevo RC. ConditionCode 1 adiciona la nota; NC 01 no equivale a ConditionCode 3 ni modifica el RC histórico.

El [plazo publicado](https://orientacion.sunat.gob.pe/04-resumen-diario-boleta-de-venta-electronica) es D+7 desde la emisión de las notas. Se controla en preparación y envío ficticio; no se atribuye a SUNAT nuestro bloqueo conservador al vencerlo (la fila 14 del libro señala observación 4443). El [Manual del Programador](https://cpe.sunat.gob.pe/sites/default/files/inline-files/manual_programador%20%281%29.pdf), pp. 17–21, describe ticket asíncrono y consulta 98/0/99; 0/99 requieren examinar el contenido. No demuestra recuperación de ticket perdido por UUID.

La aplicabilidad al emisor, tratamiento fiscal/IGV, SEE/PSE y vigencias futuras siguen condicionados a fiscal + representante antes de actividad real. Las reglas futuras del libro no se adelantan. Se conservan D-02/AF-06 y sus pendientes; RS 165-2021 solo derogó el último párrafo del artículo 22.3.

## Entregas y aceptación

1. Original y nota: boleta propia, otorgamiento declarado ficticio, RC aceptado correlacionado, referencia congelada, NC completa/ND incremento positivo, una nota permanente por original, correlativo independiente, XML/firma/ZIP privados. Sin envío individual.
2. RC nuevo de notas: miembros propios explícitos, misma fecha de nota, contenido/identidad/manifiesto congelados y outbox en un commit. Numeración RC comparte el contador existente para evitar colisiones, sin alterar documentos históricos.
3. Transporte: simulador local, ticket ficticio persistido, 98 acotado, 0/99 solo tras correlación íntegra; incertidumbre bloquea reenvío. Recuperación solo de consulta con ticket conservado. Historial/alertas mínimos y evidencia sin borrado.
4. Pruebas: NC contra ND, claves repetidas, referencias/fechas/propiedad incorrectas, firmas y alteraciones, rechazo/incertidumbre, SIGKILL, commit fallido, preservación, regresión H1–H5-A y CI remoto. Rama y PR Draft; sin fusión automática.

## Puertas conservadas

- Técnico/orquestador + fiscal: rechazo/incertidumbre no libera original ni miembro; corrección/reemisión y acumulación requieren autorización posterior. Sin bajas, corrección RC, parciales ni otros motivos.
- Fiscal + representante: validación real antes de actividad fiscal. La constancia simulada no es CDR ni prueba aceptación o recuperación oficial; no se acredita validación fiscal completa.
- Producto/técnico H6: acceso, entrega y consulta reales; autenticación local sigue siendo una limitación.
- Operación/seguridad H7: bloqueo transaccional durante firma, capacidad, retención/huérfanos, custodia, respaldo/restauración y roles SQL.
- Distribución: discrepancia/avisos XMldsig antes de distribución comercial.

Resultados y propuesta de cierre se añadirán tras ejecutar las pruebas. H5-B y H5 no se declaran cerrados.
