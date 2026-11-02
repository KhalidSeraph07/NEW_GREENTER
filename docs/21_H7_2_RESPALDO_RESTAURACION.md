# H7-2 — respaldo y restauración ficticios/locales

**Cerrado con observaciones — 02/11/2026**, exclusivamente ficticio/local, por aprobación expresa sobre `c6d3f926e33ce281b734a98c4e1260b802f28b4f` del [PR #14](https://github.com/KhalidSeraph07/NEW_GREENTER/pull/14), base `653daa449abd77156fcc16d8f850f9e2879da040`. CI Push/PR exitosos y ausencia de bloqueos comprobados; marcado Ready y fusionado mediante merge commit `78c83e540df9cd7ef9ecfe885a3e85db2e1c4fe0`, con árbol idéntico al aprobado. [CI posterior `41497955355`](https://github.com/KhalidSeraph07/NEW_GREENTER/actions/runs/41497955355) exitoso: 496 pruebas/4.308 aserciones, H1 26, HTTP 216 y H7-2 43; preservación antes/después verificada. Permanecen los límites de **64 MiB, separación solo local, sin respaldo externo real, custodia comercial de claves pendiente y destino solo consulta**. No es una herramienta productiva de respaldo. H6 y H7-1 conservan sus cierres, históricos, fixtures, responsables y puertas; instalación original sin intervenir. H7 global abierto; H7-3/4, H8 y toda actividad fiscal real sin autorización de implementación. Se permite únicamente presentar una propuesta mínima H7-3, sin ejecutarla.

## Contenido y consistencia

| Incluido | Tratamiento |
|---|---|
| `emisor_h1` y `emisor_h2_test` | Dumps lógicos completos `pg_dump -Fc`: datos, estructura, restricciones, índices, triggers, funciones, ACL y secuencias. Se cotejan todas las tablas públicas y valores de secuencias, no solo documentos. Roles/configuraciones adicionales o bases desconocidas bloquean este perfil. |
| `storage/` persistente | XML/ZIP, certificados y claves **de ensayo**, auditorías, historiales y archivos no referenciados. Se verifica cada referencia `artifacts` de los siete conjuntos H2–H5-C1 contra bytes/huella; no se eliminan huérfanos. Los vínculos/correcciones H5-C2 y demás tablas se preservan íntegros en los dumps. |
| Código versionado y `composer.lock`, `.env` | Contenido dentro del conjunto cifrado. Se recupera `APP_KEY` sin imprimirlo. El destino utiliza una contraseña PostgreSQL nueva y cookie de sesión independiente; conserva usuarios, contraseñas de aplicación, versiones de credenciales y permisos. |

Excluidos: sesiones autenticadas, caché, vistas compiladas, `.git`, dependencias `vendor` regenerables, marcadores/override del propio ensayo, directorio físico PostgreSQL/WAL y bases/roles de sistema regenerados por la misma imagen. El único rol de negocio admitido es `emisor_h1`; se cotejan atributos y membresías del clúster. La clave externa del respaldo y su contraseña **no se incluyen** en el conjunto. No es una copia de volumen físico ni recuperación a un instante arbitrario mediante WAL.

La huella de DDL normaliza únicamente dos representaciones equivalentes que PostgreSQL 16 produce al volver a parsear arrays de constantes `varchar` sin límite convertidas a `text`. Conserva nombres, miembros, orden y todas las condiciones; no ignora restricciones ni funciones/triggers. Las pruebas comprueban que cambiar un miembro permitido o quitar el CHECK sigue cambiando la huella. El SQL original sigue dentro del dump autenticado; esta normalización del comparador no modifica los esquemas de origen/destino. [Semántica de varchar sin límite y text](https://www.postgresql.org/docs/16/datatype-character.html).

La fuente debe ser una copia identificada `.h72-installation.json`, con proyecto `emisor-h72-*`, red interna y volumen propio. Las dos redes existentes por copia usan subredes `/28` libres dentro de `10.253.0.0/16`, verificadas contra IPAM de Docker, sin podar redes ni modificar el daemon. El procedimiento rechaza la raíz original y valida montajes. Abre una ventana de mantenimiento, bloquea puntos de entrada, detiene web/procesos de aplicación y exige ausencia de conexiones cliente sin drenar. Compara bases y archivos antes/después de la captura. Cualquier diferencia o referencia alterada/ausente impide publicar un respaldo válido. La fuente permanece bloqueada: no se reanudan escrituras automáticamente.

## Cifrado y claves

Se reutiliza **OpenSSL 3.5.9+ de la rama 3.5 en la imagen PHP existente**, sin dependencias nuevas. CMS firma el TAR con SHA-256/RSA-3072 y lo cifra con AES-256-GCM. Se comprueban tanto descifrado autenticado como firma contra el certificado fijado en el almacén separado, antes de interpretar archivos o ejecutar SQL. Cifrar únicamente con una clave pública no basta para forjar una firma aceptable.

La clave privada externa utiliza PKCS#8 cifrado, AES-256-CBC y PBKDF2 de 600.000 iteraciones. La contraseña se introduce oculta por TTY y viaja a OpenSSL por stdin; no por argumentos/entorno/logs. El certificado es exclusivamente de respaldo ficticio, sin identidad fiscal/legal: se fija desde el almacén externo y su firma se verifica sin imponer su caducidad como fecha de caducidad del archivo recuperable (`-no_check_time`); no se consulta una PKI real.

El TAR y su envoltura firmada se manejan en memoria; la copia temporal de firma vive solo en `tmpfs` de un contenedor criptográfico efímero sin red. El ensayo limita el TAR a **64 MiB** y ese contenedor a 512 MiB; superar el límite produce rechazo. Es una limitación explícita del ensayo, no capacidad comercial prometida. No hay respaldo plano intermedio en el host. Un `.partial` no constituye éxito; `backup.cms` y su recibo solo se publican después de descifrar/verificar la copia recién creada.

Respaldos, claves y datos ocupan directorios separados. Los directorios nuevos usan NTFS restringido al usuario actual en Windows o modos 0700/0600 en Linux. Esto demuestra separación **local**, no almacenamiento externo a un VPS ni supervivencia a pérdida del host. El harness conserva una contraseña exclusivamente sintética en `operator-secrets/fixture.private.json`, con permisos privados y fuera de claves, respaldo e instalaciones, para revisión del ensayo. No es una política de custodia comercial de claves.

## Procedimiento reproducible

Desde el checkout de control con las imágenes/dependencias existentes, preparar y probar exclusivamente instalaciones nuevas:

```sh
python tests/h72-recovery-smoke.py --root /ruta/privada/NUEVO-ENSAYO
```

El harness prepara datos con los servicios aprobados, en un reloj de fixture explícito `2026-11-02T12:00:00-05:00`: facturas pendientes/aceptadas/rechazadas/inciertas, NC/ND de facturas y boletas, RC de notas con ticket, RA/RC3, ticket perdido, permisos y auditoría H7-1 completada/no confirmada. Los hechos son nuevos y ficticios; no se reconstruyen rechazos, otorgamientos ni CDR históricos. Se conserva un archivo huérfano nuevo identificado como tal. La regresión HTTP H1–H7-1 se ejecuta por separado en CI.

Para una copia de ensayo ya identificada, ejecutar desde el checkout de control; los directorios de salida y destino deben ser nuevos y externos a las instalaciones:

```sh
python tools/h72_recovery.py keygen --keys /privado/CLAVES-NUEVAS
python tools/h72_recovery.py backup --source /privado/FUENTE --output /privado/RESPALDO-NUEVO --keys /privado/CLAVES-NUEVAS
python tools/h72_recovery.py restore --backup /privado/RESPALDO-NUEVO/backup.cms --target /privado/DESTINO-NUEVO --project emisor-h72-destino-nuevo --port 8189 --keys /privado/CLAVES-NUEVAS
```

El destino vacío se reconstruye desde el conjunto: código, configuración protegida, archivos y ambas bases. Solo `vendor` se monta en lectura desde el checkout de control con el mismo lock; no se copian datos sueltos desde la fuente. No se ejecutan migraciones ni `h2:setup`, no se restauran sesiones y no se restablecen credenciales de aplicación. La fuente de datos está fuera de servicio durante la prueba de pérdida total.

Tras cotejar esquema, tablas, secuencias, huellas y referencias, se persiste el informe de integridad antes del último marcador `verified` **solo consulta**; si falla ese informe, el destino permanece bloqueado. Para consultar manualmente después de ese resultado:

```sh
docker compose -p emisor-h72-destino-nuevo -f compose.yaml -f .h72-compose.json up -d web
```

Este último comando se ejecuta en el directorio del destino. Se permiten GET/HEAD y login/logout con los controles H6/H7-1; las demás escrituras HTTP se rechazan y Artisan solo permite arrancar el servidor. No hay despacho, recuperación fiscal ni consumo automático de outbox, incluso después de verificar. Se conservan estados, tickets y reservas exactamente; tampoco se liberan por rechazo. La habilitación de operaciones tras una restauración queda fuera de H7-2 y requiere una decisión posterior.

## Fallos, evidencia y límites

Contraseña/clave incorrecta, ciphertext alterado, firma no fijada o conjunto incompleto dejan el destino sin verificar. Una interrupción tras restaurar una sola base conserva el destino parcial bloqueado para HTTP y CLI. No se sobrescribe/reintenta sobre ese destino: se necesita otro destino vacío y revisión supervisada. No hay limpieza de evidencias, reparación de fixtures ni liberación de reservas.

`receipt.json`, `result.json` y los informes de ensayo registran huellas, comparaciones, fallos y tiempos medidos; nunca contraseñas. No sobrescribir ensayos anteriores. Los tiempos no constituyen SLA/RPO/RTO. La instalación original sigue sin migrar, restaurar, detener o cambiar credenciales; tampoco se usa como fuente de datos. Las incidencias y fixtures anteriores conservan sus responsables y puertas.

Pendientes: custodia/rotación de claves y almacenamiento externo reales, reinstalación comercial, activación controlada de operaciones, incidentes, despliegue/HTTPS y reversión autorizados por separado; fiscal/SEE/PSE/B-01/2957/2027 y licencia XMldsig según H6/docs/19–20. Este ensayo no acredita producción, aceptación SUNAT ni recuperación integral de un VPS real.

Fuentes técnicas: [CMS y AES-GCM de OpenSSL](https://docs.openssl.org/3.5/man1/openssl-cms/), [correcciones de seguridad OpenSSL 3.5](https://openssl-library.org/news/vulnerabilities-3.5/) — incluida CVE-2026-34182, corregida antes de la versión utilizada—, [dumps lógicos PostgreSQL 16](https://www.postgresql.org/docs/16/backup-dump.html) y [pg_restore](https://www.postgresql.org/docs/16/app-pgrestore.html). Un dump aislado no garantiza consistencia con archivos; por eso se detienen escrituras y se verifica el conjunto completo.
