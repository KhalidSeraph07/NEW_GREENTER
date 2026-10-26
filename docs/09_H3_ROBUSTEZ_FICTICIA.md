# H3 — Robustez exclusivamente ficticia

Autorización expresa del propietario del 25/10/2026, posterior al cierre H2 con observaciones. H3 cerrado formalmente con observaciones por aprobación expresa posterior del propietario sobre el commit revisado `96b17e6c581098534716526a86af19d822cda787`. H4 y comunicaciones SUNAT/Beta/producción no autorizados. Sin identidad, credenciales o certificados fiscales reales, nuevas dependencias, Redis o infraestructura adicional.

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

El bloqueo de serie durante firma/escritura continúa; las mediciones locales indicadas no acreditan capacidad de producción. Su validación queda en H7, a cargo de técnico/revisor; cualquier cambio requiere necesidad demostrada, sin promesas de capacidad. Reconciliación de archivos solo de lectura y bajo el mismo bloqueo de serie para excluir creaciones en curso. Un huérfano es candidato a investigación, no autorización de borrado. Autenticación de producto H6 y controles de producción/retención/restauración H7 continúan pendientes, así como licencia XMldsig antes de distribución y puertas regulatorias H0 antes de actividad real/H8.

## Evidencia y estado

Pruebas locales: **63 pruebas, 386 aserciones, cero fallos/errores** en [JUnit combinado final](../storage/audit/h3/phpunit-final.xml) y [log final con nombres de casos](../storage/audit/h3/full-suite-final.log): regresión H2 33/168 sin modificar sus pruebas; H3 30/218. La corrida anterior 62/372 y los ensayos iniciales se conservan por separado; la prueba adicional cubre las etiquetas de los estados nuevos. Incluye ocho procesos sincronizados por escenario de consulta/reintento, SIGKILL real antes del procesamiento, después del registro ficticio y antes del commit de emisión; respuesta tardía, fallo de persistencia final, límite de intentos, espera, hashes/identidad incongruentes, enlace simbólico, evidencia incompleta/alterada, consulta fallida y bloqueo activo de serie. Los JSON `storage/audit/h3/interruption-*.json` y `concurrency-*.json` registran PIDs, UUID, barreras y resultados sintéticos. No se simula un SIGKILL como si fuera pérdida física de energía.

Regresión H1: [26 comprobaciones aprobadas](../storage/audit/h3/h1-regression/result.json), incluida firma independiente y negativos; [13 controles HTTP satisfactorios](../storage/audit/h3/http-smoke.json). H1 se ejecutó con un montaje separado: su `storage/app/h1/result.json` original conservó SHA256 `BFCF2616CF7905D71E487EA079FBB816AB7278D4E25B6BCE941BF3D6351B04BD`. Las firmas cambiantes de nuevas fixtures son esperadas; las evidencias preexistentes no se regeneraron.

[Snapshot previo](../storage/audit/h3/preservation-before.json) y [comparación final](../storage/audit/h3/preservation-final.log): **60 facturas preexistentes** (1 en `emisor_h1`, 59 en `emisor_h2_test`) y **100 archivos preexistentes** conservaron valores/bytes. La suite agrega exclusivamente fixtures propias, sin fresh/truncate/borrado de volúmenes. La migración extiende restricciones de estados y añade columnas/tablas, sin borrar filas ni reinicializar; no se ejecutó reversión. Los snapshots prueban conservación del conjunto indicado, no backup/restauración integral.

Medición observacional local con ocho emisores concurrentes: duración por proceso entre **2,804 y 19,903 segundos**, incluida espera y generación/firma; identidad y correlativos correctos. No es benchmark de producción ni acredita una carga sostenible. Se conserva la observación del bloqueo durante firma; cualquier rediseño requiere necesidad demostrada y nueva revisión. Reconciliar no elimina los huérfanos de prueba ni resuelve retención H7.

Reproducción desde PowerShell, con imágenes/vendor existentes y el entorno local preparado (mismas versiones/lock H2, sin dependencias nuevas):

```powershell
New-Item -ItemType Directory -Force storage/audit/h3/nueva-h1-regression | Out-Null
docker compose run --rm php php artisan h2:setup
docker compose run --rm php php artisan h2:setup --testing
docker compose run --rm php vendor/bin/phpunit --testdox --log-junit storage/audit/h3/nueva-corrida.xml
python tests/http-smoke.py --report storage/audit/h3/http-nueva-corrida.json
$h3RegressionPath = (Resolve-Path -LiteralPath 'storage/audit/h3/nueva-h1-regression').Path
docker compose run --rm --volume "${h3RegressionPath}:/workspace/storage/app/h1" php php artisan poc:invoice --database
```

Para una nueva comparación de preservación, ejecutar `docker compose run --rm php php tests/h3-preservation.php before` **antes** de pruebas y `... after` después. El modo before rechaza sobrescribir un snapshot existente; usar otra copia aislada para un ensayo nuevo y conservar los originales. Repetir pruebas genera nuevos datos y puede sustituir informes del directorio de destino: seleccionar destinos nuevos para conservar una corrida previa. No ejecutar consumo global de pendientes de la BD manual como prueba; las pruebas automatizadas seleccionan sus UUID propios.

Operación ficticia: `h2:dispatch --scenario=communication_error` solo confirma no procesamiento en este simulador; timeout real/desconexión no justifican ese diagnóstico. `timeout` e `incomplete` conservan un resultado en `simulation_receipts` que solo `h3:recover <UUID>` puede recuperar; `uncertain` y `unexpected_error` quedan inciertos sin registro recuperable. Tres intentos máximos y esperas 5/10 segundos son política técnica del ensayo. No se reintenta rechazo, resultado terminal, ausencia en consulta ni incertidumbre. Una reserva no vencida no se recupera; la vencida se cerca para impedir que la confirmación tardía reemplace el estado recuperado.

Registros: `invoice_transport_events` conserva UUID, token/intento, evento fijo, fecha UTC y atención requerida en la misma transacción del estado; nunca excepción cruda, claves ni credenciales. `h3:inspect` muestra hasta 100 inciertas/agotadas/reservas vencidas y su cantidad total; `--evidence` añade problemas de preparación y huérfanos. Los rechazos también quedan marcados con atención en el historial de eventos y en el historial H2. Salida 1 significa atención pendiente; salida 0 de una consulta no implica aceptación fiscal: un rechazo simulado resuelto también es terminal. No hay email, canales externos ni vigilancia automática autorizada.

La suite detectó y corrigió el uso de hora local en las nuevas reservas (UTC ahora) y el seguimiento de directorios de evidencia enlazados. La revisión detectó que el fallback de las vistas H2 etiquetaba los nuevos estados H3 de reintento/en curso/agotado como inciertos; se corrigieron solo esas etiquetas con prueba de comportamiento, sin rediseño. La prueba CLI usa Artisan directamente: no se añadió Mockery por una necesidad exclusiva del harness. Comparaciones del manifiesto comprueban hashes/tamaños estrictos por campo porque PostgreSQL JSONB reordena claves. No se cambiaron cálculos, serializador XML, dependencias, Compose ni controles H2 para acomodar pruebas.

CI remoto verificado: commit [`26179fcc454008d7f887b598b89f98dcfeb3d8f5`](https://github.com/KhalidSeraph07/NEW_GREENTER/commit/26179fcc454008d7f887b598b89f98dcfeb3d8f5), [run push 40257658906](https://github.com/KhalidSeraph07/NEW_GREENTER/actions/runs/40257658906) y [run PR 40257690011](https://github.com/KhalidSeraph07/NEW_GREENTER/actions/runs/40257690011), ambos `success`. El log remoto del push registra `OK (63 tests, 386 assertions)`; instalación/auditoría Composer, H1 y HTTP también concluyeron correctamente. Esta prueba corresponde a esa revisión; este registro documental posterior no implica otra ejecución ni un escaneo Trivy nuevo. Las dependencias/locks e infraestructura permanecieron iguales a H2.

Diferencias aprobadas en [PR #1](https://github.com/KhalidSeraph07/NEW_GREENTER/pull/1), marcado Ready for Review y fusionado mediante [merge commit `9f0aa4f744c0daccb3f01b9f6a03a5fb0d695f65`](https://github.com/KhalidSeraph07/NEW_GREENTER/commit/9f0aa4f744c0daccb3f01b9f6a03a5fb0d695f65), sin rebase ni squash: intento durable y consulta/reintento seguro; reconciliación y SIGKILL; concurrencia/alertas/CI; corrección puntual de etiquetas. Graphify se actualizó con AST, sin modelos/API; persiste el aviso previo de skill 0.9.95 frente a CLI 0.9.57, sin actualizar herramientas globales.

**Cierre formal H3 ficticio con observaciones, aprobado expresamente por el propietario en este chat, Decisión 1 del 25/10/2026.** Los pendientes siguientes no bloquean este cierre ficticio y conservan su asignación y condiciones. El ciclo ensayado preserva identidad confirmada/evidencias y evita reenvío incierto y aceptación falsa; la consulta solo demuestra recuperación del registro local del simulador. Continúan los responsables/condiciones H2: técnico/revisor para bloqueo y capacidad (validación H7, medición observacional, sin rediseño preventivo); operación/seguridad para retención, tratamiento de huérfanos, pérdida de energía y respaldo/restauración H7; producto/técnico para acceso H6 y seguridad de producción H7; distribución para licencia XMldsig antes de entrega comercial. PSE, elegibilidad, modalidad/credenciales reales y recuperación oficial requieren sus validaciones antes de cualquier actividad real; ninguna se declara resuelta por el ensayo.

Decisión 2 del propietario: fusión autorizada del PR #1. Antes de fusionar se comprobó HEAD exacto `96b17e6c581098534716526a86af19d822cda787`, ambos controles GitHub en `SUCCESS`, estado `CLEAN`/`MERGEABLE` y ausencia de cambios locales. El cierre se registró primero en el PR y en el mensaje de fusión para conservar intacto el HEAD aprobado. El merge tiene dos padres: `87ef13afa97e611d24f9bc55fdff3f638aea3220` y el HEAD aprobado; su árbol coincide con el de este último. La actualización posterior de estados en main es exclusivamente documental; no modifica las pruebas, el código, las dependencias ni la infraestructura aceptados.

**H3 cerrado formalmente con observaciones. H4 no autorizado. Comunicaciones reales SUNAT/Beta/producción y certificados o credenciales fiscales reales siguen prohibidos hasta autorización expresa posterior.**
