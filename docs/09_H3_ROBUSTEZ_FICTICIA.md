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

Pendiente de ejecutar los cambios y sus pruebas. Este documento no declara H3 terminado.
