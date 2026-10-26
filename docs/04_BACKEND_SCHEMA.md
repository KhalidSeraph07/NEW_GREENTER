# Modelo y contratos conceptuales — H0, versión 0.3

**24/10/2026 · Borrador, sin DDL, migraciones ni API implementada.** Fuente: Plan Maestro §§3–4 y 6; [flujos](02_APP_FLOW.md), [arquitectura](03_TRD_ARQUITECTURA.md) y [matriz](MATRIZ_FISCAL.md). Los nombres siguientes describen información necesaria y no fijan una tabla física por cada fila.

## Información y relaciones mínimas

Modelo vigente por la [adenda confirmada](../DOCS_GREENTER/ADENDA_H0_MODELO_OPERATIVO.md): **una instalación independiente por cliente y un RUC emisor inicial**, con PostgreSQL, usuarios, configuración, secretos y archivos propios. No se requiere `tenant_id` ni relaciones/índices para una BD compartida por clientes; conservar identidad fiscal, pertenencia de documentos y auditoría local. No existe esquema implementado que migrar o eliminar.

| Concepto | Información necesaria | Integridad y decisión pendiente |
|---|---|---|
| Empresa/emisor | Identidad fiscal única inicial de la instalación, configuración y ambiente efectivo | D-01 aprobada; D-03 condicionada a elegibilidad/dictamen. Impedir duplicar autoridad de numeración del RUC. |
| Usuario/pertenencia | Usuario y permiso sobre empresa | Toda acción y recurso se autorizan en servidor. Roles mínimos pendientes. |
| Serie | Empresa, tipo, serie y asignación de correlativo | Configuración de inicio basada en historial real; no inferir número libre desde una sola consulta. |
| Documento y detalle | Identidad, fecha fiscal, receptor, líneas, moneda, tributos/totales y relación con original | Snapshot fiscal al preparar; no depender de un cliente/producto editable para reconstruir un documento histórico. |
| Operación idempotente | Empresa, identificador de operación, huella de entrada y resultado | Igual clave/igual contenido → misma operación; igual clave/diferente contenido → conflicto. Alcance y duración se especifican antes de H2. |
| Comunicación | Tipo factura/resumen/baja según ruta, identidad propia cuando aplique, fecha referida y contenido | Documento, resumen y baja no comparten necesariamente identidad ni CDR. |
| Miembro de resumen | Documento referido, información comunicada y acción/versionado aplicable | Mismo día de emisión; no sobrescribir un resumen anterior al corregirlo. |
| Trabajo pendiente e intento | Acción pendiente, correlación, inicio/fin, resultado técnico y error saneado | Persistencia durable; separar intención de cada ejecución. Modelo físico mínimo según comportamiento. |
| Respuesta/evidencia | Ambiente, identidad asociada, ticket si existe, recepción de CDR, resultado y artefacto original | Verificar pertenencia y coincidencia; ausencia de CDR no equivale a rechazo. |
| Archivo | Propietario, objeto privado, clase, huella, tamaño y vínculo | Original íntegro; nunca URL pública permanente; almacenamiento pendiente D-08. |
| Secreto/configuración protegida | Material cifrado o referencia externa, versión y vigencia de configuración | Claves fuera de la base; rotación y recuperación D-06. No almacenar secreto en auditoría. |
| Otorgamiento e incidencia | Medio/fecha/evidencia de entrega; motivo, responsable y resolución de incidente | No confundir envío SUNAT con entrega al adquirente; registrar actuación humana relevante. |

No se requiere catálogo maestro de clientes, productos, contabilidad, cartera o pagos. Una representación PDF puede regenerarse si preserva el contenido fiscal y su trazabilidad; no puede usarse para sustituir el XML firmado o aparentar una CDR inexistente. La política de versiones del PDF se concretará con las reglas de representación.

## Claves y restricciones que el diseño debe demostrar

1. En el espacio fiscal real, identidad única por **RUC emisor + tipo + serie + número**. La instalación conserva esa restricción y el contexto del emisor; separar VPS no resuelve por sí solo la numeración concurrente del mismo RUC en otros sistemas.
2. Ambientes de prueba y producción segregados. Los namespaces técnicos no habilitan duplicados del mismo documento real en instalaciones distintas.
3. Notas, miembros de resumen, archivos, trabajos y respuestas pertenecen al emisor local correcto; mantener integridad referencial y autorización por usuario/receptor. No introducir claves compuestas de multitenencia sin una necesidad real.
4. Numeración bajo transacción y restricción única; una pérdida de conexión no se resuelve aumentando correlativo y repitiendo la operación.
5. Estado preparado exige archivos e identidad recuperables; un fallo de persistencia no devuelve éxito. No asumir atomicidad entre sistemas de almacenamiento.
6. Historial de evidencia y contenido preparado no admite actualización destructiva por operaciones ordinarias. Las correcciones crean relaciones/artefactos nuevos.
7. CDR y ticket pueden no existir durante incertidumbre. El esquema no obliga a inventar valores para completar campos.

Precisión/escala, máximos de correlativo, formatos de serie, fechas de retención y códigos fiscales están pendientes de reglas oficiales aplicables. Importes usarán representación decimal exacta adecuada; no se usarán flotantes binarios para calcular dinero. Escala interna y redondeo de salida se decidirán con MF-16, sin imponer ahora dos decimales a cada operación intermedia.

## Transacciones y puntos de recuperación

| Unidad lógica | Lo que debe preservarse | Falla a diseñar y probar en H2/H3 |
|---|---|---|
| Confirmación de emisión | Operación idempotente, identidad y estado consistente | Solicitud concurrente/repetida, fallo de firma y correlativo reservado. |
| Preparación durable | Snapshot y referencias a artefactos íntegros, trabajo pendiente | Archivo guardado sin commit; commit sin archivo utilizable; caída entre pasos. |
| Inicio/fin de intento | Correlación y evidencia de que se intentó comunicar | Proceso muere después de SUNAT y antes de persistir respuesta. |
| Aplicación de respuesta | CDR verificada, vínculo correcto y resultado derivado | Respuesta duplicada, ajena, tardía o contradictoria. |
| Resumen/corrección/baja | Comunicación inmutable y miembros coherentes | Ticket perdido, corrección posterior y solapamiento de información. |

No se mantiene una transacción SQL abierta esperando una respuesta de red. Debe existir reconciliación cuando se restaure un respaldo anterior a una respuesta ya recibida por SUNAT. La duración de bloqueos, leases y reintentos queda para el diseño verificable del hito correspondiente.

## Contratos de operación, sin fijar rutas HTTP

| Operación lógica | Entrada y precondiciones | Salida observable / errores |
|---|---|---|
| Guardar borrador | Empresa autorizada y datos editables | Borrador o errores de campos; no reserva definitiva por cada edición. |
| Confirmar emisión | Datos validados, identidad de operación y contexto fiscal permitido | Identificador local y estado real; conflicto idempotente, fuera de alcance o persistencia fallida explícitos. |
| Consultar documento | Propiedad/permisos | Estado documental, transporte y resultado fiscal separados; evidencia accesible según permiso. |
| Conciliar envío | Documento/comunicación e intento identificados | Resultado sustentado o incertidumbre persistente; no aceptación inventada. |
| Solicitar nota/baja | Original, motivo y elegibilidad aprobados | Documento/comunicación relacionados o explicación de no elegibilidad. |
| Descargar/consultar como receptor | Autenticación de receptor y recurso autorizado | Archivo permitido o denegación; no filtrar recursos mediante ID o enlace adivinable. |

No se promete API pública, OAuth de integradores ni rutas/versionado externo. H2 definirá contrato interno mínimo y manejo uniforme de validación, autorización, conflicto y fallo de dependencia.

## Migraciones futuras

H1 establecerá una base reproducible solo con autorización. H2 y siguientes incorporarán restricciones junto al comportamiento que protegen. Revisar cambios compatibles, respaldo, ejecución en despliegue y rollback de aplicación; no asumir que revertir una migración puede borrar evidencias fiscales. Ninguna migración se creó ni ejecutó en H0.
