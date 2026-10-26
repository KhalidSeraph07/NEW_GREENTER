# H3 — Robustez exclusivamente ficticia

Autorización expresa del propietario del 25/10/2026, posterior al cierre H2 con observaciones. H3 en ejecución; cierre sujeto a aprobación expresa. H4 y comunicaciones SUNAT/Beta/producción no autorizados. Sin identidad, credenciales o certificados fiscales reales, nuevas dependencias, Redis o infraestructura adicional.

El cierre documental H2 fue publicado en `87ef13afa97e611d24f9bc55fdff3f638aea3220`: [CI 40255758746](https://github.com/KhalidSeraph07/NEW_GREENTER/actions/runs/40255758746) exitoso, 33 pruebas y 168 aserciones según log remoto. Solo cinco Markdown; responsables/condiciones H2 conservados.

| Tarea | Dependencia | Criterio de aceptación |
|---|---|---|
| H3-01 Estado e intento durable | H2 cerrado | Migración aditiva; claim confirmado antes del simulador, transición condicionada al intento vigente; regresión H2 íntegra. |
| H3-02 Fallos, reintentos y consulta simulada | H3-01 | Máximo tres intentos, espera técnica 5/10 segundos solo tras rechazo local previo a procesamiento; incertidumbre sin reenvío; consulta valida identidad y hash, nunca crea aceptación a partir de ausencia. |
| H3-03 Caídas y reconciliación | H3-02 | Procesos realmente interrumpidos, token vigente protegido, detección de huérfanos/integridad sin borrar ni sobrescribir; identidad, numeración y bytes conservados. |
| H3-04 Incidentes y evidencia | H3-01–03 | Registro transaccional con atención requerida y consulta CLI; suite H1/H2/H3, HTTP local y CI; diferencias revisables y riesgos declarados. |

Estados de preparación: no hay factura persistida hasta XML/firma/ZIP y commit; un fallo previo conserva la política H2 de rollback. Una factura persistida conserva su identidad y manifiesto. La preparación se comprueba por integridad de las cuatro evidencias; un fallo no consume intento ni se convierte en éxito.

| Outbox / transporte | Resultado fiscal ficticio | Transiciones permitidas |
|---|---|---|
| `pending` / `pending_simulation` | Sin resultado | `processing` tras verificar evidencia y reservar intento |
| `processing` / `simulation_processing` | Desconocido | `completed` solo con respuesta íntegra correlacionada; `retry_wait` solo si el simulador confirma que no procesó; `uncertain` ante timeout, respuesta inválida o interrupción |
| `retry_wait` / `simulation_retry_wait` | Sin resultado | `processing` después de espera y con menos de tres intentos |
| `exhausted` / `simulation_failed` | Sin resultado | Detenido; sin reinicio automático |
| `uncertain` / `simulation_uncertain` | Desconocido | Solo consulta del registro local del simulador; ausencia/error no permite reenviar |
| `completed` / `simulated_accepted` o `simulated_rejected` | Resultado expresamente simulado | Terminal; sin reenvío ni cambio del resultado |

La reserva expira a los cinco minutos (valor técnico del ensayo, no plazo SUNAT). Recuperar una reserva vencida la vuelve incierta antes de consultar; invalida al trabajador anterior. La consulta ficticia solo recupera un registro del simulador previamente conservado, ligado al UUID y hash ZIP. Esta capacidad local no acredita ninguna API, ticket, consulta, CDR ni recuperación oficial SUNAT. Las excepciones no clasificadas se tratan como incertidumbre, nunca como prueba de no recepción.

El bloqueo de serie durante firma/escritura continúa; medir contención y justificar cualquier cambio, sin prometer capacidad. Reconciliación de archivos solo de lectura y bajo el mismo bloqueo de serie para excluir creaciones en curso. Un huérfano es candidato a investigación, no autorización de borrado. Autenticación de producto H6 y controles de producción/retención/restauración H7 continúan pendientes, así como licencia XMldsig antes de distribución y puertas regulatorias H0 antes de actividad real/H8.

## Evidencia y estado

Pruebas locales: **62 pruebas, 372 aserciones, cero fallos/errores** en [JUnit combinado](../storage/audit/h3/phpunit.xml) y [log con nombres de casos](../storage/audit/h3/full-suite.log): regresión H2 33/168 sin modificar sus pruebas; H3 29/204. Incluye ocho procesos sincronizados por escenario de consulta/reintento, SIGKILL real antes del procesamiento, después del registro ficticio y antes del commit de emisión; respuesta tardía, fallo de persistencia final, límite de intentos, espera, hashes/identidad incongruentes, enlace simbólico, evidencia incompleta/alterada, consulta fallida y bloqueo activo de serie. Los JSON `storage/audit/h3/interruption-*.json` y `concurrency-*.json` registran PIDs, UUID, barreras y resultados sintéticos. No se simula un SIGKILL como si fuera pérdida física de energía.

Regresión H1: [26 comprobaciones aprobadas](../storage/audit/h3/h1-regression/result.json), incluida firma independiente y negativos; [13 controles HTTP satisfactorios](../storage/audit/h3/http-smoke.json). H1 se ejecutó con un montaje separado: su `storage/app/h1/result.json` original conservó SHA256 `BFCF2616CF7905D71E487EA079FBB816AB7278D4E25B6BCE941BF3D6351B04BD`. Las firmas cambiantes de nuevas fixtures son esperadas; las evidencias preexistentes no se regeneraron.

[Snapshot previo](../storage/audit/h3/preservation-before.json) y [comparación posterior](../storage/audit/h3/preservation-after.log): **60 facturas preexistentes** (1 en `emisor_h1`, 59 en `emisor_h2_test`) y **100 archivos preexistentes** conservaron valores/bytes. La suite agrega exclusivamente fixtures propias, sin fresh/truncate/borrado de volúmenes. La migración extiende restricciones de estados y añade columnas/tablas, sin borrar filas ni reinicializar; no se ejecutó reversión. Los snapshots prueban conservación del conjunto indicado, no backup/restauración integral.

Medición observacional local con ocho emisores concurrentes: duración por proceso entre **2,804 y 19,903 segundos**, incluida espera y generación/firma; identidad y correlativos correctos. No es benchmark de producción ni acredita una carga sostenible. Se conserva la observación del bloqueo durante firma; cualquier rediseño requiere necesidad demostrada y nueva revisión. Reconciliar no elimina los huérfanos de prueba ni resuelve retención H7.

Reproducción desde PowerShell, con imágenes/vendor existentes y el entorno local preparado (mismas versiones/lock H2, sin dependencias nuevas):

```powershell
New-Item -ItemType Directory -Force storage/audit/h3/h1-regression | Out-Null
docker compose run --rm php php artisan h2:setup
docker compose run --rm php php artisan h2:setup --testing
docker compose run --rm php vendor/bin/phpunit --testdox --log-junit storage/audit/h3/phpunit.xml
python tests/http-smoke.py --report storage/audit/h3/http-smoke.json
$h3RegressionPath = (Resolve-Path -LiteralPath 'storage/audit/h3/h1-regression').Path
docker compose run --rm --volume "${h3RegressionPath}:/workspace/storage/app/h1" php php artisan poc:invoice --database
```

Para una nueva comparación de preservación, ejecutar `docker compose run --rm php php tests/h3-preservation.php before` **antes** de pruebas y `... after` después. El modo before rechaza sobrescribir un snapshot existente; usar otra copia aislada para un ensayo nuevo y conservar los originales. Repetir pruebas genera nuevos datos y puede sustituir informes del directorio de destino: seleccionar destinos nuevos para conservar una corrida previa. No ejecutar consumo global de pendientes de la BD manual como prueba; las pruebas automatizadas seleccionan sus UUID propios.

Operación ficticia: `h2:dispatch --scenario=communication_error` solo confirma no procesamiento en este simulador; timeout real/desconexión no justifican ese diagnóstico. `timeout` e `incomplete` conservan un resultado en `simulation_receipts` que solo `h3:recover <UUID>` puede recuperar; `uncertain` y `unexpected_error` quedan inciertos sin registro recuperable. Tres intentos máximos y esperas 5/10 segundos son política técnica del ensayo. No se reintenta rechazo, resultado terminal, ausencia en consulta ni incertidumbre. Una reserva no vencida no se recupera; la vencida se cerca para impedir que la confirmación tardía reemplace el estado recuperado.

Registros: `invoice_transport_events` conserva UUID, token/intento, evento fijo, fecha UTC y atención requerida en la misma transacción del estado; nunca excepción cruda, claves ni credenciales. `h3:inspect` muestra hasta 100 inciertas/agotadas/reservas vencidas y su cantidad total; `--evidence` añade problemas de preparación y huérfanos. Los rechazos también quedan marcados con atención en el historial de eventos y en el historial H2. Salida 1 significa atención pendiente; salida 0 de una consulta no implica aceptación fiscal: un rechazo simulado resuelto también es terminal. No hay email, canales externos ni vigilancia automática autorizada.

La suite detectó y corrigió el uso de hora local en las nuevas reservas (UTC ahora) y el seguimiento de directorios de evidencia enlazados. La prueba CLI usa Artisan directamente: no se añadió Mockery por una necesidad exclusiva del harness. Comparaciones del manifiesto comprueban hashes/tamaños estrictos por campo porque PostgreSQL JSONB reordena claves. No se cambiaron cálculos, serializador XML, dependencias, Compose ni controles H2 para acomodar pruebas.

CI H3 y propuesta de cierre: pendientes de publicación/verificación de esta revisión. **H3 no se declara cerrado; H4 y servicios reales siguen sin autorización.**
