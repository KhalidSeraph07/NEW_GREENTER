# H2 — Primera factura ficticia, entrega progresiva

Autorización del propietario en este chat, 25/10/2026. H1 cerrado con observaciones; licencia XMldsig pendiente antes de distribución. CI remoto acreditado para el commit indicado abajo. **H2 cerrado con observaciones por decisión expresa del propietario del 25/10/2026. H3 autorizado por decisión separada exclusivamente ficticio; H4 y comunicaciones reales con SUNAT no autorizados.**

Primera vertical: una instalación, identidades `00000000000` fijas, serie F000, un concepto, contado, PEN y tasa de ejemplo 18%. Importes de entrada con hasta 2 decimales, cantidad con hasta 3; cálculo en enteros y redondeo half-up por línea al céntimo. Es contrato del ensayo, no regla fiscal ratificada para producción. Sin catálogos, CRUD fiscal real, PDF, boletas/notas ni receptor externo.

| Tarea | Depende | Criterio de aceptación | Estado |
|---|---|---|---|
| T01 Cierre H1 y límites H2 | Decisiones del propietario | Cierre y pendientes registrados; sin reinicializar proyecto | Registrado |
| T02 Acceso/configuración y migración | T01 | Operador local, CSRF, propiedad servidor; configuración solo sintética; sin puertos BD ni secretos públicos; migración aditiva | Implementada; ver pruebas de acceso/HTTP |
| T03 Entrada, cálculo y numeración | T02 | Validación servidor, enteros/redondeo, unicidad por RUC/tipo/serie/número; misma clave/contenido reproduce, contenido distinto 409 | Implementada; concurrencia real verificada |
| T04 XML/firma y evidencia | T03 | Greenter, certificado generado solo de prueba, XML/ZIP privados con huellas; fallo no confirma operación ni consume correlativo | Implementada; firma, rollback y fallo postcommit probados |
| T05 Outbox y simulación | T04 | Invoice/outbox en un commit; procesado sin duplicados y sin red; aceptada/rechazada/incierta siempre ficticias; incierta detenida | Implementada; tres escenarios y consumo concurrente probados |
| T06 Formulario e historial | T02–T05 | UI Blade mínima con aviso único; crear, consultar y descargar solo propios; nunca afirmar aceptación SUNAT | Implementada; recorrido HTTP real probado |
| T07 Pruebas y evidencia de revisión | T02–T06 | PostgreSQL real aislado para pruebas; procesos concurrentes, idempotencia, fallos/rollback, autorización, CSRF, integridad, regresión H1 | Aceptada en cierre H2 con observaciones |

Decisiones técnicas proporcionales: Laravel/Blade y un proceso HTTP local con la misma imagen PHP; PostgreSQL/outbox sin Redis ni scheduler. HTTP Basic con operador ficticio y contraseña aleatoria local, sin gestión comercial de usuarios. Historial limitado a propietario del documento. La creación bloquea la fila de serie dentro de una transacción; se prueba su coste y no se promete capacidad. Los archivos se escriben antes del commit: rollback controlado limpia solo sus propios archivos; una caída abrupta puede dejar archivos privados huérfanos, nunca un envío confirmado. La reconciliación de caídas y retención completa no se declara resuelta aquí.

Identidad idempotente por usuario + UUID de operación, conservada sin expiración en este ensayo; huella de entrada normalizada. Solo la configuración de servidor fija identidad/moneda/tasa/serie. Evidencia e identidad no tienen edición ni borrado ordinarios. No se mantiene transacción SQL esperando una respuesta de red: el transporte implementado es una función simulada local.

Las pruebas usan exclusivamente `emisor_h2_test`, con guardas antes de escribir y sin `migrate:fresh`, truncados ni borrado de volúmenes. La aplicación local usa la BD existente mediante migraciones aditivas. Cualquier uso real conserva las puertas regulatorias/operativas de H0. Las pruebas y diferencias fueron aceptadas por decisión expresa del propietario; este archivo registra ese cierre y no aprueba hitos posteriores automáticamente.

## Evidencia de esta entrega para revisión

- [Suite PHPUnit](../storage/audit/h2/tests.log) y [JUnit](../storage/audit/h2/phpunit.xml): PostgreSQL real, numeración/replays con ocho procesos sincronizados, disputa de contenido, consumo único de outbox, validación/redondeo, propiedad, CSRF, integridad, rollback y preservación tras fallo posterior al commit.
- [HTTP real](../storage/audit/h2/http-smoke.json): operador, formulario, CSRF 419, creación/replay, conflicto 409, sobreescritura de total 422, XML/ZIP privados, .env/storage 404, Host ajeno 400 y simulación explícita.
- [Regresión H1](../storage/audit/h2/h1-regression/result.json), [Composer audit](../storage/audit/h2/composer-audit.json) y [resumen verificable](../storage/audit/h2/summary.json). Versiones runtime sin cambios; PHPunit solo dev.
- Se corrigió la ruta automática /storage que Laravel registraba por defecto: no se usa ningún alias público para los archivos privados. Se probó que una colisión UUID no borra evidencia y que una excepción después del commit conserva la identidad/archivos y permite replay seguro. No se declaran probadas caídas arbitrarias, durabilidad física del disco, backups ni aceptación fiscal.
- CI ampliado con pruebas H1/H2 y HTTP local, ejecutado también en GitHub Actions con éxito para el commit indicado abajo. Los artefactos locales anteriores, incluido `summary.json` con `HostedCI: No ejecutado`, conservan el estado previo al primer push; no son el registro del resultado remoto. No hubo liberación ni aceptación fiscal.

## Cierre formal de H2 con observaciones — 25/10/2026

Verificados mediante API de GitHub y logs remotos: [run 40252416751, intento 1](https://github.com/KhalidSeraph07/NEW_GREENTER/actions/runs/40252416751), workflow `H1 y H2 ficticios sin SUNAT`, job `poc`, estado `completed`, conclusión `success`. Commit ejecutado: [`2c46f4834e75faa5e128f97ef3f8244b5295deaf`](https://github.com/KhalidSeraph07/NEW_GREENTER/commit/2c46f4834e75faa5e128f97ef3f8244b5295deaf). Job finalizado el 25/10/2026 a las 20:06:22 America/Lima (26/10/2026 01:06:22 UTC). El log registra `OK (33 tests, 168 assertions)` y los 13 controles HTTP en `true`. La evidencia remota corresponde a ese commit, no acredita revisiones posteriores ni sustituye la validación SUNAT, el escaneo Trivy o las puertas de producción.

Consulta reproducible, con GitHub CLI autenticado:

```powershell
gh run view 40252416751 --repo KhalidSeraph07/NEW_GREENTER
gh run view 40252416751 --repo KhalidSeraph07/NEW_GREENTER --log
```

El propietario aprobó expresamente el cierre de la primera vertical H2 **con las siguientes observaciones abiertas**, aceptando implementación, pruebas y limitaciones. Se conservan responsables y condiciones conforme al Plan Maestro. La autorización separada de H3 solo permite ensayos ficticios; las asignaciones a H6/H7 no autorizan iniciarlos ni exigen refactorizar preventivamente H2.

| Observación conservada | Responsable / hito | Condición de resolución |
|---|---|---|
| Firma y escritura de archivos dentro de la transacción que bloquea la serie; sin capacidad operativa demostrada | Responsable técnico y revisor, H3; validación de capacidad en H7 | Medir contención y fallos concurrentes antes de comprometer capacidad. Cambiar el diseño solo si la evidencia lo exige, preservando unicidad, idempotencia y atomicidad de factura/outbox. |
| Posibles archivos privados huérfanos por caída abrupta y falta de atomicidad entre BD y archivos | Responsable técnico, H3; operación/seguridad, H7 | Probar recuperación/reconciliación tras caída sin borrar evidencias referenciadas o de commit incierto ni regenerar identidades. En H7 demostrar coherencia en respaldo/restauración y definir retención antes de uso real. |
| HTTP Basic, operador ficticio y servidor PHP aptos exclusivamente para desarrollo local; rol SQL con privilegios de inicialización | Responsable de producto/técnico, H6; seguridad/operación, H7 | Completar usuarios/permisos en H6 y verificar autenticación, TLS, secretos y privilegios mínimos en H7, antes de cualquier exposición no local. Los controles actuales de propiedad y CSRF permanecen exigibles; no se declara autenticación de producción resuelta. |
| Discrepancia de etiquetado y conservación de avisos XMldsig | Responsable de distribución, antes de distribución comercial; revisión en H7 | Aclarar el etiquetado y comprobar la inclusión íntegra de avisos/condiciones en lo distribuido, según el pendiente H1. |

La observación H1 de **ejecución remota de CI queda acreditada para el commit verificado**. La licencia y las puertas H0 de PSE, elegibilidad, credenciales/modalidad y recuperación fiscal efectiva siguen pendientes para sus actividades reales y antes de H8; el éxito del ensayo no las resuelve. No se incorporan llamadas SUNAT/Beta, credenciales fiscales ni despliegues reales.

**Estado: H2 cerrado formalmente con observaciones por aprobación expresa del propietario en este chat, Decisión 1 del 25/10/2026. Decisión 2: H3 autorizado exclusivamente para pruebas ficticias, con preservación de BD/evidencias y compatibilidad H1/H2; cierre H3 sujeto a aprobación expresa. H4 y las comunicaciones reales con SUNAT permanecen sin autorización.**
