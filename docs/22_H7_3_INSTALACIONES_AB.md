# H7-3 — instalaciones A/B ficticias y locales

Autorizado sobre `1fc2efb761be78d87703879c53cc364f11ef6e2e`. Implementación pendiente de revisión mediante una rama y PR Draft, sin fusión ni cierre automático. H6/H7-1/H7-2 conservan sus cierres; H7 global abierto. H7-4/H8 y comunicaciones/identidades/certificados/credenciales fiscales reales no autorizados.

## Configuración mínima

Se conserva `compose.yaml`, incluido su nombre predeterminado `emisor-h1`. La variable estándar [`COMPOSE_PROJECT_NAME`](https://docs.docker.com/compose/how-tos/project-name/) prevalece sobre ese nombre: cada instalación utiliza uno distinto, sin `-p`, volúmenes externos ni nombres globales de contenedor. `.env.example` explicita la cookie actual, conservando el comportamiento previo, y muestra el nombre de proyecto como comentario.

El ensayo crea únicamente dos directorios nuevos externos al checkout. Copia código versionado, no `.env`, bases, sesiones, cachés ni claves. Cada `.env` se genera desde la plantilla con `APP_KEY`, contraseña PostgreSQL, contraseña inicial H2 y cuenta operadora sintética independientes. Solo se ejecuta `h2:setup` en las bases nuevas. El identificador de emisor continúa deliberadamente inválido `00000000000` en ambas: no demuestra alta de emisores reales.

Antes de arrancar se examina `docker compose config --format json`: proyecto, volumen, redes, montajes, puertos loopback e imágenes; se rechazan colisiones de nombres, puertos y almacenamiento. Después se cotejan los recursos realmente creados por Docker. Un conflicto o fallo detiene el ensayo y conserva sus recursos; no se reutiliza un destino previo.

Se reutilizan las imágenes PHP/PostgreSQL existentes, **fijadas por su ID** en el override de prueba; `up` usa `--no-build --pull never` y `run` usa `--pull never` sin `--build`, con los IDs ya comprobados localmente. Crear B no reconstruye ni sustituye la imagen de A. `vendor` del checkout aprobado se monta solo lectura en ambas; el resto del código, almacenamiento y cachés reside en su directorio propio. Esta dependencia compartida exige conservar el checkout/imagen para repetir la demostración: no se demuestra independencia de actualizaciones ni se introduce un sistema de despliegue.

## Reproducción

Con Docker, imágenes aprobadas y dependencias del lock ya disponibles:

```sh
python tests/h73-isolation-smoke.py --root /ruta/privada/ENSAYO-NUEVO --keep-running
```

Puertos predeterminados: A `127.0.0.1:8193`, B `127.0.0.1:8194`. Redes de ensayo explícitas: A `10.254.73.0/28` y `10.254.73.16/28`; B `10.254.73.32/28` y `10.254.73.48/28`. No se buscan subredes libres, se podan redes ni se modifica Docker. Si hay conflicto, detenerse y acordar valores explícitos distintos mediante `--ports PUERTO_A PUERTO_B --cidrs CIDR_A_LOCAL CIDR_A_BROWSER CIDR_B_LOCAL CIDR_B_BROWSER`; no reintentar sobre el mismo directorio. Docker rechaza subredes solapadas con redes existentes.

Los directorios nuevos son privados (NTFS para el usuario actual o modo 0700); `.env` y `accounts.private.json` contienen únicamente secretos de ensayo y nunca se publican. Para navegador, iniciar sesión por separado con las cuentas privadas de cada instalación; no copiar sus credenciales al informe. `compose.sanitized.json`, `state-before.json`, `state-after.json` y `result.json` son las evidencias de configuración, recursos, datos y resultados, sin contraseñas ni cookies.

El ensayo utiliza procesos HTTP independientes con sesiones separadas. Comprueba credenciales ajenas, documentos privados, cookies copiadas tanto con su nombre original como renombradas, acceso PostgreSQL con la contraseña ajena y conectividad entre redes. Detiene A con `docker compose stop`, verifica sesión/documentos y todas las filas/secuencias/evidencias de B, reinicia A y coteja ambas instalaciones. No consume outbox ni simula envíos: las facturas quedan pendientes y conservadas. Sin `--keep-running`, al terminar detiene ambos proyectos sin eliminar volúmenes. Para conservar o repetir consultas, usar exclusivamente los proyectos y directorios indicados en `result.json` y sus dos archivos Compose.

La preservación local compara directamente cada archivo del baseline nuevo del checkout y el estado de sus contenedores originales; no sobrescribe baselines anteriores ni arranca la instalación original. CI añade el ensayo después de la regresión H1–H7-2 y repite el control de preservación existente.

Incidencias de preparación conservadas: lectura Windows de una fixture y permisos de caché (inspección sin red mediante montaje solo lectura); opción `--no-build` no disponible en `compose run`; punto de montaje `vendor` inicialmente ausente; comprobación del aviso ficticio sensible a mayúsculas. El primer CI detectó esa aserción y el siguiente, un orden variable de `Mounts` en Docker, reproducido con 12 consultas y dos ordenaciones. La comparación ahora ordena por destino y conserva todos los campos, IDs y estados, sin ignorar montajes o permisos. No se corrigieron motores ni datos históricos.

## Límites y puertas conservadas

Solo separación funcional local, sin protección ante administrador del host, acceso privilegiado a Docker o pérdida física del servidor. HTTPS, firewall, endurecimiento, exposición externa y actualización independiente requieren decisiones posteriores. H7-2 sigue experimental: 64 MiB, separación solo local, sin respaldo externo real ni custodia comercial de claves demostrada; destino restaurado solo consulta.

Se conservan fixtures huérfanas, incidencias históricas y responsables/puertas fiscales, SEE/PSE, B-01/2957/2027, representación imprimible, seguridad/operación, restauración comercial y licencia XMldsig de H6/docs/19–21. Sin limpieza, reparación histórica ni motores fiscales modificados. Responsable del ensayo y aislamiento: equipo técnico; aprobación del alcance/cierre: propietario. No se acredita producción ni aceptación SUNAT.
