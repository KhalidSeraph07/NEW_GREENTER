# H7-4A — operación y mantenimiento supervisados, ficticios/locales

Autorizado sobre `0f89bed813579695da8cf722b393e16e672e0eeb`. Entrega pendiente de revisión en PR Draft, sin fusión ni cierre automático. H7 global abierto; H7-4B/C, H8 e infraestructura/identidades/comunicaciones fiscales reales no autorizados.

## Capacidades existentes y límites

| Mecanismo | Diagnóstico permitido | Precaución |
|---|---|---|
| `h3:inspect --evidence` | Facturas inciertas/agotadas/reservas vencidas, integridad y candidatos huérfanos | Lista hasta 100 incidentes y su total. No lista todos los pendientes ni convierte un rechazo en aceptación. |
| `h4:process inspect` | Boletas/RC: evidencias e incidencias | Distinguir el estado del RC del documento miembro. |
| `h5:process inspect` | NC/ND de factura: evidencias, referencia e incidencias | Un rechazo/estado incierto requiere atención; no autoriza otra nota. |
| `h5b:process inspect` | Notas de boleta/RC: evidencias y eventos de atención | Un ticket o estado 98 no confirma resultado. |
| `h5c1:process inspect` | Bajas: evidencias/originales y eventos | Sin ticket no hay recuperación por UUID; no liberar reservas. |
| `h5c2:process inspect` | Vínculos de corrección e indicios de huérfanos | Solo inspección; no preparar correcciones ni reemisión en H7-4A. |
| GET de historiales/detalles y `psql` en transacción `READ ONLY` | Estados, intentos, tickets conservados, membresías, contadores y auditorías | HTTP conserva roles/propiedad `user_id`; SQL es acceso técnico privilegiado a una sola instalación, no un permiso nuevo para ADMIN. |

Los inspectores no modifican operaciones ni reparan archivos; algunos adquieren bloqueos transitorios `FOR UPDATE NOWAIT`. No son todos libres de bloqueos ni ofrecen una vista global atómica. Exigir ventana coordinada sin otros operadores/procesos emisores; no clasificar archivos como huérfanos mientras exista creación concurrente. Salida `1` con informe válido puede significar **atención pendiente**; excepción, salida inesperada o informe incompleto no constituye diagnóstico válido. Salida `0` tampoco acredita éxito fiscal.

`send`, `dispatch`, `poll`, `recover`, `grant`, `freeze`, `prepare` y los POST de simulación no son consultas inocuas. No ejecutarlos durante el mantenimiento. La fixture del ensayo los usa únicamente **antes** del baseline, sobre documentos nuevos y totalmente ficticios.

## Procedimiento mínimo

1. Identificar directorio, proyecto, puerto, imágenes y montajes efectivos. Rechazar perfiles ajenos, almacenamiento compartido inesperado y cualquier `storage/framework/h72-state.json`. No quitar ni editar ese bloqueo: los destinos H7-2 siguen solo consulta.
2. Coordinar ventana sin escrituras ni CLI activos. Consultar estados y reservas con SQL `READ ONLY`, revisar historiales propios y ejecutar los `inspect` anteriores. Conservar informes/códigos de salida; clasificar pendientes, incertidumbres y terminales por sus estados, no por ausencia de errores.
3. Guardar registro nuevo de intervención: proyecto, responsable **declarado**, motivo, hora y huellas previas. No acredita identidad/autorización digital ni sustituye auditoría comercial. Nunca sobrescribir otro registro.
4. En **ese proyecto** y con sus dos archivos Compose, detener HTTP: `docker compose ... stop --timeout 30 web`. Comprobar que no hay CLI activos ni conexiones cliente sin drenar; no terminar procesos desconocidos a ciegas. Verificar tablas/secuencias/archivos y después detener PostgreSQL: `docker compose ... stop --timeout 30 postgres`. No usar `down -v`, `prune`, rollback ni restauración.
5. Conservar el volumen. Comprobar configuración/imagen sin cambios y arrancar **solo PostgreSQL**: `docker compose ... up -d --no-build --pull never --wait postgres`. Cotejar datos, reservas, auditorías, numeración y evidencias con el baseline. Si difieren o falta evidencia, conservar HTTP detenido, registrar la incidencia y pedir examen supervisado; no resolverla por excepción/silencio.
6. Registrar decisión explícita: **reiniciar HTTP, sin despachar ni recuperar transporte**, solo con integridad verificada, ausencia de procesos/conexiones desconocidos y pendientes conocidos preservados. Arrancar `web` con `--no-build --pull never`. Esto no hace la interfaz globalmente de solo lectura ni impide a un administrador del host eludir el procedimiento.
7. Comprobar consultas, sesiones vigentes, permisos/propiedad y comparación final. Añadir registro de finalización técnica sin cerrar incidencias fiscales. Sesión vencida/cerrada o cuenta desactivada/versión de credenciales cambiada exige el comportamiento H6/H7-1 ya aprobado; no se promete conservación indefinida de sesión.

Los puntos suspensivos significan **las mismas opciones** `--env-file <instalación>/.env -f <instalación>/compose.yaml -f <instalación>/maintenance-compose.json`, con `COMPOSE_PROJECT_NAME` comprobado y exclusivo. No ejecutar estos ejemplos desde la raíz original ni imprimir `compose config` con secretos: usar la configuración saneada del ensayo y `config --quiet`.

## Demostración reproducible y evidencia

```sh
python tests/h74a-maintenance-smoke.py --root /ruta/privada/ENSAYO-NUEVO --responsible ENSAYO-TECNICO-H74A --reason "MANTENIMIENTO FICTICIO SUPERVISADO SIN RESOLVER INCERTIDUMBRE"
```

Se crea una instalación nueva, nunca se reutiliza un destino H7-2 ni una instalación original. Imágenes existentes fijadas por ID, `vendor` aprobado compartido solo lectura y configuración validada con la utilidad de prueba H7-3 existente. `.env`, claves y cuentas se generan nuevas desde la plantilla; no se copian de otra instalación. Puerto predeterminado `127.0.0.1:8211`, dos redes explícitas `10.254.80.0/28` y `10.254.80.16/28`; si hay conflicto, acordar `--port`/`--cidrs` distintos y otra raíz vacía, sin búsqueda dinámica ni limpieza de ensayos.

Solo se añaden fixtures y comprobaciones de prueba, **ninguna funcionalidad de aplicación**. Los servicios aprobados preparan facturas pendientes/inciertas/aceptadas/rechazadas, notas, boletas/RC, RC de notas con ticket pendiente y bajas terminales/inciertas sin ticket. No se recrean hechos históricos. La demostración conserva clientes HTTP independientes del propietario, otro operador, ADMIN y RECEPTOR durante stop/start, sin realizar POST de transporte después del baseline.

`SNAPSHOT_SQL` calcula huellas de todas las tablas públicas y conserva secuencias en una transacción repetible de solo lectura; `DIAGNOSIS_SQL` muestra estados/reservas sin modificarlos. Se usan [SHA-256 nativo PostgreSQL 16](https://www.postgresql.org/docs/16/functions-binarystring.html) y [representación ordenada de consultas](https://www.postgresql.org/docs/16/functions-xml.html#FUNCTIONS-XML-MAPPING); es comparación acotada de este ensayo, no una herramienta de respaldo/exportación.

La raíz privada contiene `accounts.private.json` y, dentro de `audit/`, configuración saneada, fixture, baseline/final, diagnóstico, informes de inspect, intervención iniciada/finalizada y decisión de reinicio. `result.json` resume comprobaciones y recursos. Los registros públicos no incluyen contraseñas, cookies, claves ni `.env`. Un fallo conserva el ensayo y su registro incompleto, sin limpieza o finalización automática.

Resultado local: ensayo `greenter-h74a-run-01-58b9c1`, proyecto `emisor-h74a-6d12fdc04f`, **22 comprobaciones satisfactorias**, 39 tablas públicas con secuencias y 68 archivos privados idénticos. Sesiones/permisos comprobados antes/después durante 226,172 segundos; no constituye SLA. `inspect` devolvió 1 para factura incierta, nota rechazada y baja incierta, y 0 para los demás informes válidos: ninguno equivale a aceptación fiscal. Factura/RA inciertas y ticket ausente se conservaron; el RC de notas quedó `awaiting` con ticket y sin resultado definitivo. Se compararon todas las filas, incluidos contadores, reservas y auditorías; no se despacharon operaciones durante ni después del mantenimiento. Solo se ejecutó este ensayo focalizado local, sin repetir suites H1–H7 ni escanear o arrancar la instalación original. El workflow existente no se modifica: cualquier CI de publicación conserva su alcance previo y no se presenta como ejecución de este ensayo H7-4A.

## Observaciones pendientes

No hay bloqueo operativo adicional, vigilancia automática, alertas remotas, acreditación del responsable ni garantía ante otros administradores del host. CLI con límites y diferentes alcances se complementa con consultas SQL/historiales; no se añade un inspector universal. Reiniciar procesos no concilia fiscalmente, no confirma CDR ni libera reservas o recupera tickets.

Se conservan H6/H7-1/H7-2/H7-3, sesiones de duración limitada, dependencia de imágenes/vendor y todas las incidencias/fixtures protegidas. H7-2 sigue experimental: 64 MiB, separación solo local, custodia comercial pendiente, sin respaldo externo real y restauración solo consulta. Fiscal/SEE/PSE/B-01/2957/2027, licencia, seguridad y operación comercial conservan responsables/puertas de docs/19–22. Responsable técnico del ensayo: equipo técnico; aprobación de alcance/cierre: propietario. H7-4B/C, H8 y comunicaciones SUNAT/Beta/producción siguen sin autorización. No acredita producción.
