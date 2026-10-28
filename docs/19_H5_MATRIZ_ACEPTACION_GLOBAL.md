# H5 — matriz de aceptación global propuesta, sin cierre global

28/10/2026 · America/Lima. Preparada **después** de fusionar el PR #6 mediante `affa679045833dda5ee12149bbfc813396639cdf`. H5-A, H5-B, H5-C1 y H5-C2 están cerrados con observaciones por decisiones expresas; **la aceptación global H5 sigue pendiente del propietario**. Base: Plan Maestro §5/H5 y RP-04/RP-05, precisados por D-02/AF-06 y los recortes aprobados. Matriz documental: no amplía funcionalidades, reabre código ni revalida normativa. No acredita producto listo para producción.

## Casos implementados y aceptación del ensayo

| Caso aprobado | Criterio y límite demostrado ficticiamente | Evidencia / estado |
|---|---|---|
| H5-A: NC 07/01 sobre factura 01 | Anulación completa; original propio aceptado, otorgamiento de ensayo, receptor/PEN e importes correlacionados; referencia congelada y numeración propia. | [H5-A](13_H5A_NOTAS_FACTURA_FICTICIAS.md), PR #3; cerrado con observaciones. |
| H5-A: ND 08/02 sobre factura 01 | Incremento positivo justificado, mismo original/receptor/moneda; reserva conservadora de una nota por original incluso ante rechazo o incertidumbre. | H5-A; positivos/negativos, concurrencia NC/ND e idempotencia aceptados. |
| H5-B: NC 07/01 y ND 08/02 sobre boleta 03 | Boleta propia otorgada ficticiamente e informada por RC aceptado correlacionado; notas con identidad/referencia propias; RC nuevos con miembros explícitos y fecha de las notas, ticket y 98/0/99 examinados. | [H5-B](14_H5B_NOTAS_BOLETA_FICTICIAS.md), PR #4; cerrado con observaciones. |
| H5-C1: baja RA de 01/07/08 F | Documentos propios aceptados simuladamente y acreditados como no otorgados; fechas/dependencias verificadas y miembros congelados. | [H5-C1](16_H5C1_BAJAS_FICTICIAS.md), PR #5; cerrado con observaciones, B-01/2957 pendiente. |
| H5-C1: baja RC operación 3 de 03/07/08 B | Solo previamente informados y aceptados, no otorgados acreditadamente; no se alteran RC ni documentos anteriores. | H5-C1; resultado correlacionado, sin baja por silencio o incertidumbre. |
| H5-C2: corrección técnica de RC/RA rechazados | Solo nuevos escenarios con causa ZIP `SIM-C2-ZIP-ENTRY` explícita/correlacionada; conjunto completo y campos fiscales iguales, una sucesora con artefactos/outbox/ticket propios, sin cadenas ni liberación de reservas. | [H5-C2](18_H5C2_VERTICAL_FICTICIA.md), PR #6; cerrado con observaciones. Incluye RC de boletas/notas B y bajas RA/RC3. |
| H5-C2: reemisión manual de factura 01 rechazada | Nueva identidad/correlativo, misma operación/economía y causa subsanada; fecha de operación de ensayo explícita, ventana original y vínculo inmutable. Nunca se recicla el número rechazado. | H5-C2; una sucesora; no reemisión individual de notas/boletas. |
| Negativos e integridad transversales | Bloquear ajenos/incompatibles, aceptados para corrección, pendientes, inciertos, ticket perdido, constancias incompletas y rechazo sin causa admisible; no nueva sucesora ante respuesta tardía descartada. Identidad/huellas/historiales preservados; concurrencia, idempotencia, SIGKILL, SQL adversarial y fallos de persistencia. | Regresión remota aceptada: **384 pruebas / 2.390 aserciones**, H1 26 y HTTP 71; [CI del HEAD revisado](https://github.com/KhalidSeraph07/NEW_GREENTER/actions/runs/40756449661). No equivale a CDR o validación SUNAT. |

## Exclusiones y diferimientos: no confundir su alcance

| Fuera del recorte aprobado | Tipo y consecuencia para la aceptación global propuesta |
|---|---|
| Motivos distintos de NC 01 / ND 02, parciales y saldos de múltiples notas | Exclusión inicial aprobada D-02/AF-06 y H5-A/B; no aceptar silenciosamente operaciones no soportadas. Una necesidad del emisor real exige decisión y validación fiscal previa. |
| Motor general, edición de históricos, rectificación general de RC aceptados, múltiples sucesoras/cadenas y liberación de reservas | Exclusiones expresas de H5-C2. Rechazo de la sucesora queda bloqueado; no constituye solución operativa universal ni autorización de anulación. |
| Corrección/reemisión individual de notas o boletas, otras causas de rechazo y reconstrucción de rechazos históricos | Fuera del ensayo C2 autorizado. Solo empaquetado ZIP demostrado, conservando economía y referencias; no se deduce que todo rechazo permita reenvío o documento nuevo. |
| Bajas de boletas/notas no informadas y procedimientos excepcionales | Fuera de C1 por decisión expresa. Su procedimiento fiscal/operativo permanece pendiente antes de atender un caso real; no se declara exclusión tributaria universal. |
| Envío individual de boletas/notas B | Alternativa conservada documentalmente, sin implementación V1 inicial conforme D-07. No ampliar ahora la ruta RC elegida. |
| Operación fiscal real, entrega/acreditación real del otorgamiento, recuperación oficial sin ticket y cobertura fiscal completa | **Puertas pendientes**, no funcionalidades declaradas resueltas o prescindibles por el cierre ficticio. Consulta por UUID del simulador no demuestra capacidad oficial. |
| Configuración/usuarios/permisos e interfaz operativa completa; seguridad/despliegue/restauración/piloto | Diferidos a hitos posteriores y sus autorizaciones. H6 no autorizado; cerrar H5 no los habilita. ERP, contabilidad, GRE y otras operaciones ajenas al recorte siguen fuera. |

## Registro final de riesgos, responsables y puertas

| Pendiente conservado | Responsable | Puerta de resolución |
|---|---|---|
| Elegibilidad, SEE/PSE, B-01/2957, causales/duplicados, contratos oficiales, plazos/fecha de operación y vigencia 2027 | Profesional fiscal + representante autorizado | Antes de cualquier actividad fiscal real o flujo afectado; no convertir las ventanas conservadoras de ensayo ni el ZIP `invoice.xml` heredado en conformidad real. |
| Otorgamiento/entrega reales y procedencia de sus evidencias | Representante + técnico | Antes de bajas reales; una afirmación ficticia no prueba entrega ni autoriza completar históricos. |
| 65 candidatos huérfanos y fixture terminal inválida del ensayo | Técnico + operador; supervisión del propietario | H7 y antes de operación real: mantenerlos conservados y señalados; diagnóstico/decisión autorizada, sin limpieza o reparación automática. No fueron corregidos por el cierre. |
| Omisión de 76 rutas en una enumeración; causa no establecida | Técnico + operador | H7/operación: investigar y acreditar el comportamiento del entorno. Lectura directa del baseline verifica hashes; no demuestra causa ni exhaustividad de toda enumeración. |
| Respaldo/recuperación integral y restauración verificable de BD, archivos y material necesario | Técnico + operador + propietario | H7 y antes de operación real: demostrar restauración coherente y recuperación; preservación, CI o detección de huérfanos no la sustituyen. |
| Acceso local/HTTP Basic, permisos/aislamiento, firma dentro de transacción, incidentes y operación segura | Técnico + propietario | H6/H7, previa autorización y antes de exposición/uso real. Sin rediseños o refactorizaciones preventivas durante este cierre. |
| Discrepancia/avisos de licencia XMldsig | Propietario + asesoría competente | Antes de distribución. El parche MIME ya aceptado y Composer Audit no resuelven esta cuestión. |
| Aceptación global H5 y posteriores autorizaciones | Propietario | Decisión expresa sobre esta matriz; mantener H5 global sin cerrar hasta recibirla. H6 y comunicaciones/datos/certificados/credenciales fiscales reales siguen prohibidos. |

**Decisión solicitada:** aprobar o no el cierre global de **H5 exclusivamente ficticio, con este recorte y observaciones**, sin declarar cobertura fiscal completa, producto listo para producción ni autorización de H6. Cualquier alcance adicional requiere decisión separada; esta matriz no inicia su implementación.
