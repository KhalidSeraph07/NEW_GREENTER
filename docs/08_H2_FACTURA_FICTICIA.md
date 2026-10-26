# H2 — Primera factura ficticia, entrega progresiva

Autorización del propietario en este chat, 25/10/2026. H1 cerrado con observaciones; licencia XMldsig pendiente antes de distribución y CI remoto antes de liberación. **H2 en curso y pendiente de aprobación; H3 no autorizado.**

Primera vertical: una instalación, identidades `00000000000` fijas, serie F000, un concepto, contado, PEN y tasa de ejemplo 18%. Importes de entrada con hasta 2 decimales, cantidad con hasta 3; cálculo en enteros y redondeo half-up por línea al céntimo. Es contrato del ensayo, no regla fiscal ratificada para producción. Sin catálogos, CRUD fiscal real, PDF, boletas/notas ni receptor externo.

| Tarea | Depende | Criterio de aceptación | Estado |
|---|---|---|---|
| T01 Cierre H1 y límites H2 | Decisiones del propietario | Cierre y pendientes registrados; sin reinicializar proyecto | Registrado |
| T02 Acceso/configuración y migración | T01 | Operador local, CSRF, propiedad servidor; configuración solo sintética; sin puertos BD ni secretos públicos; migración aditiva | Implementada; ver pruebas de acceso/HTTP |
| T03 Entrada, cálculo y numeración | T02 | Validación servidor, enteros/redondeo, unicidad por RUC/tipo/serie/número; misma clave/contenido reproduce, contenido distinto 409 | Implementada; concurrencia real verificada |
| T04 XML/firma y evidencia | T03 | Greenter, certificado generado solo de prueba, XML/ZIP privados con huellas; fallo no confirma operación ni consume correlativo | Implementada; firma, rollback y fallo postcommit probados |
| T05 Outbox y simulación | T04 | Invoice/outbox en un commit; procesado sin duplicados y sin red; aceptada/rechazada/incierta siempre ficticias; incierta detenida | Implementada; tres escenarios y consumo concurrente probados |
| T06 Formulario e historial | T02–T05 | UI Blade mínima con aviso único; crear, consultar y descargar solo propios; nunca afirmar aceptación SUNAT | Implementada; recorrido HTTP real probado |
| T07 Pruebas y evidencia de revisión | T02–T06 | PostgreSQL real aislado para pruebas; procesos concurrentes, idempotencia, fallos/rollback, autorización, CSRF, integridad, regresión H1 | Evidencia disponible; aprobación H2 pendiente |

Decisiones técnicas proporcionales: Laravel/Blade y un proceso HTTP local con la misma imagen PHP; PostgreSQL/outbox sin Redis ni scheduler. HTTP Basic con operador ficticio y contraseña aleatoria local, sin gestión comercial de usuarios. Historial limitado a propietario del documento. La creación bloquea la fila de serie dentro de una transacción; se prueba su coste y no se promete capacidad. Los archivos se escriben antes del commit: rollback controlado limpia solo sus propios archivos; una caída abrupta puede dejar archivos privados huérfanos, nunca un envío confirmado. La reconciliación de caídas y retención completa no se declara resuelta aquí.

Identidad idempotente por usuario + UUID de operación, conservada sin expiración en este ensayo; huella de entrada normalizada. Solo la configuración de servidor fija identidad/moneda/tasa/serie. Evidencia e identidad no tienen edición ni borrado ordinarios. No se mantiene transacción SQL esperando una respuesta de red: el transporte implementado es una función simulada local.

Las pruebas usan exclusivamente `emisor_h2_test`, con guardas antes de escribir y sin `migrate:fresh`, truncados ni borrado de volúmenes. La aplicación local usa la BD existente mediante migraciones aditivas. Cualquier uso real conserva las puertas regulatorias/operativas de H0. Se entregarán pruebas y diferencias; este archivo no cierra H2 automáticamente.

## Evidencia de esta entrega para revisión

- [Suite PHPUnit](../storage/audit/h2/tests.log) y [JUnit](../storage/audit/h2/phpunit.xml): PostgreSQL real, numeración/replays con ocho procesos sincronizados, disputa de contenido, consumo único de outbox, validación/redondeo, propiedad, CSRF, integridad, rollback y preservación tras fallo posterior al commit.
- [HTTP real](../storage/audit/h2/http-smoke.json): operador, formulario, CSRF 419, creación/replay, conflicto 409, sobreescritura de total 422, XML/ZIP privados, .env/storage 404, Host ajeno 400 y simulación explícita.
- [Regresión H1](../storage/audit/h2/h1-regression/result.json), [Composer audit](../storage/audit/h2/composer-audit.json) y [resumen verificable](../storage/audit/h2/summary.json). Versiones runtime sin cambios; PHPunit solo dev.
- Se corrigió la ruta automática /storage que Laravel registraba por defecto: no se usa ningún alias público para los archivos privados. Se probó que una colisión UUID no borra evidencia y que una excepción después del commit conserva la identidad/archivos y permite replay seguro. No se declaran probadas caídas arbitrarias, durabilidad física del disco, backups ni aceptación fiscal.
- CI ampliado con pruebas H1/H2 y HTTP local; YAML/comandos verificados localmente. No hay ejecución remota ni liberación. H1 sigue cerrado con sus condiciones de licencia/CI.

**Estado: primera vertical implementada, pruebas entregadas y H2 abierto para revisión del propietario. H3 no autorizado.**
