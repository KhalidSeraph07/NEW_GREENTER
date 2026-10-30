# Emisor — H5 global ficticio cerrado con observaciones

**H6-2B, primera entrega cerrada con observaciones — 30/10/2026**, exclusivamente ficticia y local, por aprobación expresa sobre `990d28dc25cd56be43e54e5c20134be4f3358d64` del [PR #9](https://github.com/KhalidSeraph07/NEW_GREENTER/pull/9). HEAD exacto, [CI Push](https://github.com/KhalidSeraph07/NEW_GREENTER/actions/runs/41142326958) y [CI PR](https://github.com/KhalidSeraph07/NEW_GREENTER/actions/runs/41142331382) exitosos; sin conflictos ni solicitudes/revisiones/hilos pendientes. Marcado Ready y fusionado mediante merge commit `7342af74217f5baf3d4e09948bfd426968cbdc5c`, sin squash ni rebase, con árbol idéntico al aprobado. Solo boletas y RC ficticios por Blade: formularios y claves conservados, selección con motivos de exclusión, revisión sin reserva, confirmación idempotente, filtros/paginación, acciones según estado/ticket e historial persistido. Se reutilizan sin cambios los motores H1–H5 y los controles H6-1/H6-2A. La huella de revisión vive en sesión; tras expirar, revisar de nuevo con la misma clave conserva el RC existente. H4 revalida al congelar; una selección revisada puede quedar reservada o vencer antes de confirmar. Un ticket no equivale a aceptación; la recuperación solo rearma consultas con ticket conservado o registra incertidumbre de una reserva vencida, sin reenviar.

Reproducción focalizada: `docker compose run --rm php vendor/bin/phpunit tests/H62B tests/H4/AccessTest.php`; concurrencia existente: `docker compose run --rm php vendor/bin/phpunit tests/H4/ConcurrencyTest.php`; HTTP local: `python tests/h62b-http-smoke.py --output-dir storage/audit/h6-2b/http-NUEVO` (directorio nuevo; encadena las 100 comprobaciones previas). Suite completa en CI. Preservación local: `docker compose run --rm php php tests/h6-preservation.php before h6-2b` antes de nuevas fixtures y `after h6-2b` al finalizar; no sobrescribir el baseline. Mantener todos los pendientes/responsables/puertas de [H5](docs/19_H5_MATRIZ_ACEPTACION_GLOBAL.md) y [acceso local](docs/20_H6_1_ACCESO_LOCAL.md). **Solo la primera entrega H6-2B está cerrada; H6-2B global y H6 global no cerrados. Segunda entrega de notas, H6-3/4, H7/H8, API pública y comunicaciones/identidades/certificados/credenciales fiscales reales no autorizados. Sin aceptación/CDR oficial ni aptitud para producción.**


Evidencias aceptadas H6-2B: **427 pruebas / 2.994 aserciones, 26 comprobaciones H1 y 118 controles HTTP**; preservación local previa de 29.533 filas, incluidas 2.755 cuentas completas, y 23.230 archivos protegidos, según `storage/audit/h6-2b/registro-final.json`. Los CI posteriores al merge y al registro documental se verifican por separado y se enlazan en el informe final; registro en `storage/audit/h6-2b/cierre/`. El cierre modifica solo README/AGENTS: sin pruebas locales que escriban datos ni cambios a código, dependencias, migraciones, infraestructura o evidencias históricas.

Fixture SIGKILL intencional conservada: `3d641e3e-c2b0-44d5-bf9e-f229dc731a5a`, en `storage/framework/testing/h4/`, procedente de `tests/H4/InterruptionTest.php::test_crash_before_rc_commit_preserves_orphan_but_not_counter_or_membership` durante la regresión H6-2B. Informe `storage/audit/h4/orphan-e3449a68-28cd-455b-85e1-59b014123d63.json`, campo `committed_id` del informe: `38aa17fc-438d-4be8-9823-5d9208cb2dda`; SHA-256 de `signed.xml`: `bb9488fdf063324c63e67e84fba66f900c7ad21846f70cffca3d7fe2b3fbfc90`, comprobada en este cierre sin modificar archivos. **Tratamiento pendiente H7: responsable técnico, revisión/autorización del propietario; sin eliminar ni limpiar automáticamente.** Se conserva separada de los 65 candidatos históricos y de la fixture terminal inválida. La restauración integral sigue sin demostrarse; permanecen todos los responsables y puertas de docs/19 y docs/20.

**H6-2A cerrado con observaciones por aprobación expresa**, exclusivamente ficticio y local, sobre `89328435ba566081f56117e27cce557faf1dffb6` del [PR #8](https://github.com/KhalidSeraph07/NEW_GREENTER/pull/8). HEAD exacto y [CI Push](https://github.com/KhalidSeraph07/NEW_GREENTER/actions/runs/40993184408)/[CI PR](https://github.com/KhalidSeraph07/NEW_GREENTER/actions/runs/40993191677) exitosos, sin conflictos ni solicitudes/revisiones/hilos pendientes; PR marcado Ready y fusionado mediante merge commit `5b932672ae58962c01c33f96ab70b56508a79e8c`, sin squash ni rebase. Se verificó igualdad de árboles con el HEAD aprobado. Alcance aceptado: formulario → revisión sin reserva → preparación idempotente → simulación → recuperación manual H3 → historial PostgreSQL → descarga privada. Motores H1–H5, controles H6-1, propiedad `user_id`, idempotencia y evidencias conservados.

Evidencias aceptadas H6-2A: ambos CI, **413 pruebas / 2.729 aserciones, 26 comprobaciones H1 y 100 controles HTTP**; preservación local de 29.321 filas previas, incluidas 2.727 cuentas completas, y 23.066 archivos protegidos, según `storage/audit/h6-2a/registro-final.json`. El CI posterior al merge y al registro documental se verifica por separado y se enlaza en el informe final, con evidencia en `storage/audit/h6-2a/cierre/`. El cierre solo modifica estos registros documentales: sin funcionalidades, refactorizaciones, dependencias, migraciones ni ejecución de pruebas locales que alteren datos.

**Observaciones conservadas:** la huella de revisión expira con la sesión; tras perderla se debe iniciar sesión y revisar de nuevo con la misma clave antes de confirmar. La idempotencia autoritativa permanece en el motor. La recuperación por UUID consulta únicamente el simulador y puede quedar incierta; no representa recuperación SUNAT, CDR oficial ni habilita reenvío incierto. Todos los pendientes fiscales/SEE/PSE/B-01/2957/otorgamiento real, seguridad, firma transaccional, operación/restauración, licencia XMldsig, 65 candidatos huérfanos, fixture inválida y causa desconocida de enumeración conservan responsables y puertas de la [matriz H5](docs/19_H5_MATRIZ_ACEPTACION_GLOBAL.md#registro-final-de-riesgos-responsables-y-puertas) y del [cierre H6-1](docs/20_H6_1_ACCESO_LOCAL.md#registro-de-cierre-y-pendientes-conservados). También siguen pendientes recuperación operativa de credenciales, revisión comercial de cuentas y acceso compartido. **H6 no está cerrado globalmente; H6-2B, H6-3/4, H7/H8 y comunicaciones/identidades/certificados/credenciales fiscales reales no autorizados.** Sin aptitud para producción.

**H6-1 cerrado con observaciones por aprobación expresa**, exclusivamente ficticio y local, sobre `5bdb5531a2fcb495bc1903c31afeb2ef95ab370e`; [PR #7](https://github.com/KhalidSeraph07/NEW_GREENTER/pull/7) fusionado mediante merge commit `d903ae1bb2aafab38de89c4985de04b1f0ece005`: sesiones Laravel y roles locales ADMIN/OPERADOR; ambos acceden solo a documentos propios. ADMIN crea/desactiva cuentas; la última cuenta ADMIN activa se protege. [Alcance, reproducción y pendientes](docs/20_H6_1_ACCESO_LOCAL.md). CI revisados: 401 pruebas, 2.582 aserciones, 26 comprobaciones H1 y 83 controles HTTP. Recuperación de credenciales, revisión comercial de cuentas y acceso compartido siguen pendientes con responsables/puertas del informe. H6 no cerrado globalmente; H6-2/3/4 y H7/H8 no autorizados. H5 conserva el cierre ficticio siguiente.

**29/10/2026 · America/Lima.** H0 cerrado; H1–H4, H5-A, H5-B y [H5-C1 ficticios cerrados con observaciones por aprobación expresa](docs/16_H5C1_BAJAS_FICTICIAS.md). [H5-C2](docs/18_H5C2_VERTICAL_FICTICIA.md) cerrado formalmente con observaciones por aprobación expresa; PR #6 fusionado mediante merge commit `affa679045833dda5ee12149bbfc813396639cdf`: una sucesora técnica de RA/RC rechazados o una reemisión manual 01; sin cadenas ni liberación de reservas. [Preparación previa al código](docs/17_H5C2_PREPARACION_TECNICA.md). **H5 global cerrado con observaciones por aprobación expresa sobre `aadb6f8721b5b04105e3bb8862265003da4e8f13`, exclusivamente según la [matriz de aceptación y registro final](docs/19_H5_MATRIZ_ACEPTACION_GLOBAL.md). H6, H7 y H8 no autorizados; no se declara cumplimiento fiscal real, homologación SUNAT, aptitud para producción ni cobertura tributaria universal.** Licencia XMldsig pendiente antes de distribución; comunicaciones/identidades/certificados/credenciales fiscales reales prohibidos. Los apartados históricos conservan el contexto de sus hitos.

CI finales verificados: [merge](https://github.com/KhalidSeraph07/NEW_GREENTER/actions/runs/40758097232) y [base aprobada](https://github.com/KhalidSeraph07/NEW_GREENTER/actions/runs/40758763349), ambos exitosos con **384 pruebas, 2.390 aserciones, 26 comprobaciones H1 y 71 controles HTTP**. Se conservan los 65 candidatos huérfanos, la fixture terminal inválida, la causa desconocida de la omisión de enumeración y la restauración integral pendiente de demostración. La matriz conserva responsables y puertas fiscales, de seguridad, operación y licencia; el cierre es exclusivamente documental.

Parche de seguridad justificado en H5-C2: solo `symfony/mime` 7.4.19 → 7.4.22 por CVE-2026-45070; sin nuevas dependencias ni cambios mayores. Evidencias y límites en el informe de H5-C2.

La [planificación H2](docs/08_H2_FACTURA_FICTICIA.md) registra tareas pequeñas, dependencias, aceptación y evidencia. Implementación: operador ficticio con sesiones Laravel (HTTP Basic sustituido en H6-1), Blade mínimo con CSRF, un concepto/contado/PEN, cálculos enteros, correlativo bloqueado y outbox en una transacción PostgreSQL, XML/firma/ZIP privados, historial y transporte exclusivamente simulado. No hay módulo comercial ni conexión SUNAT/Beta.

[H3 ficticio](docs/09_H3_ROBUSTEZ_FICTICIA.md) incorpora reserva durable de intento, fallos simulados, reintentos seguros acotados, consulta del registro del simulador, reconciliación de solo lectura y alertas CLI. Conserva UI, numeración y XML H2. H3 quedó cerrado con observaciones por aprobación expresa sobre el commit `96b17e6`; PR #1 fusionado mediante merge commit. Comunicaciones reales y RUC/certificados/credenciales fiscales reales siguen sin autorización. Los comandos y evidencias H3 están en ese documento; los informes H2 siguientes conservan su contexto histórico.

[H4: boleta y RC ficticios](docs/11_H4_VERTICAL_FICTICIA.md), con D-07 aprobada para V1: formulario mínimo en `/boletas`, XML/firma/ZIP privados, RC inmutable, ticket y consulta 98/0/99 simulados. Cerrado con observaciones por aprobación expresa; [PR #2](https://github.com/KhalidSeraph07/NEW_GREENTER/pull/2) marcado Ready for Review y fusionado mediante merge commit `6e6366336d211f0b527159ef9a94e2a0f7a59dea`. Facturas H2/H3, dependencias y Compose conservados. Corrección de RC rechazados pendiente de etapa expresamente autorizada antes de habilitar ese flujo real. H5 y toda comunicación/identidad/credencial fiscal real siguen sin autorización.

[H5-A: notas de factura ficticias](docs/13_H5A_NOTAS_FACTURA_FICTICIAS.md), con recorte D-02/AF-06 aprobado: NC 07/01 completa y ND 08/02 por incremento, referencia congelada, XML/firma/ZIP privados y transporte/recuperación ficticios. UI mínima `/invoice-notes`; **H5-A cerrado formalmente con observaciones por aprobación expresa**; [PR #3](https://github.com/KhalidSeraph07/NEW_GREENTER/pull/3) marcado Ready for Review y fusionado mediante merge commit `4aef63653cc5ed3a46cf19f9362156451bfce1df`, sin squash ni rebase. **Al cierre de H5-A, H5 seguía incompleto; el cierre global ficticio posterior consta en la matriz 19.** Una nota por original incluso rechazada/incierta. H5-B cerrado con observaciones por decisión posterior, exclusivamente ficticio; H6 y toda actividad fiscal real no autorizados. El informe incluye comandos, pruebas y responsables/puertas pendientes; la RS 165-2021 derogó solo el último párrafo del artículo 22.3.

[H5-B: notas de boleta y nuevos RC ficticios](docs/14_H5B_NOTAS_BOLETA_FICTICIAS.md): NC 07/01 completa y ND 08/02 incremento, solo originales propios otorgados ficticiamente y correlacionados en RC simulado aceptado. UI mínima `/receipt-notes`; CLI `h5b:process send|poll|recover <UUID>` e `inspect` sin borrado. Sin envío individual ni recuperación de ticket perdido. **H5-B cerrado formalmente con observaciones por aprobación expresa**; [PR #4](https://github.com/KhalidSeraph07/NEW_GREENTER/pull/4) marcado Ready for Review y fusionado mediante merge commit `228543d1d7d51e864955134e3b5c1c69f02bafcc`, sin squash ni rebase. **Registro de H5-B/C1 anterior al cierre global:** H5-C1 bajas ficticias cerrado con observaciones por aprobación expresa; [PR #5](https://github.com/KhalidSeraph07/NEW_GREENTER/pull/5) marcado Ready y fusionado mediante merge commit `1debea20a0960c3712b57dd7d4438ff151ff6eb2`, sin squash ni rebase, según [regla previa, alcance y cierre](docs/16_H5C1_BAJAS_FICTICIAS.md). H5-C2 cerrado con observaciones y PR #6 fusionado por aprobación expresa; el cierre global H5 ficticio con observaciones fue aprobado expresamente sobre `aadb6f8`, según la matriz 19. La liberación de reservas y H6 no están autorizados. Conserva las puertas fiscales, H6/H7 y licencia; no demuestra aceptación SUNAT ni CDR oficial.


Preparar/arrancar H2 en PowerShell, sin reinicializar ni borrar datos:

```powershell
./scripts/prepare-local.ps1
docker compose up -d --wait postgres
docker compose run --rm php php artisan h2:setup
docker compose up -d web
```

Abrir [http://127.0.0.1:8180](http://127.0.0.1:8180). Usuario ficticio: `operador.h2@example.invalid`; contraseña local en `H2_OPERATOR_PASSWORD` de `.env` (no compartirla). La clave APP_KEY y contraseñas se generan solo si faltan y no se imprimen. En una copia nueva primero construir las imágenes e instalar dependencias desde el lock con los comandos de infraestructura más abajo. No ejecutar `down -v`, `migrate:fresh` ni sustituir el volumen existente.

Pruebas reproducibles H2/H3 (el segundo setup aplica migraciones aditivas a la BD de pruebas):

```powershell
docker compose run --rm php php artisan h2:setup --testing
docker compose run --rm php vendor/bin/phpunit --testdox --log-junit storage/audit/h3/phpunit.xml
python tests/http-smoke.py --report storage/audit/h3/http-smoke.json
```

PHPUnit 12.5.40 es dependencia **solo de desarrollo** (PHP 8.3 compatible, 25 paquetes dev); al incorporar H2, las 79 dependencias runtime H1 conservaron sus versiones; posteriormente H5-C2 aplica el parche MIME de seguridad descrito arriba. [Soporte del mantenedor](https://phpunit.de/supported-versions.html). PostgreSQL de pruebas: `emisor_h2_test`, separado del uso manual. Las pruebas no borran/resetan la base: agregan fixtures ficticias propias y usan números relativos; guardas bloquean su ejecución sobre otra base.

Evidencias: [JUnit](storage/audit/h2/phpunit.xml), [log PHPUnit](storage/audit/h2/tests.log), [HTTP real](storage/audit/h2/http-smoke.json), [resumen](storage/audit/h2/summary.json), [auditoría Composer runtime + dev](storage/audit/h2/composer-audit.json). Los archivos `storage/audit/h2/concurrency-*.json` registran PIDs, barrera y resultados de ocho procesos para numeración, replays y consumo de outbox; dos procesos disputan una misma clave con contenido distinto. Solo datos sintéticos, sin servicios SUNAT. La regresión H1 se conserva en [directorio separado](storage/audit/h2/h1-regression/result.json).

El proceso web adicional reutiliza la misma imagen PHP: es necesario para el formulario y publica **solo 127.0.0.1:8180**. PostgreSQL no publica puertos. CLI/pruebas conservan red interna sin salida. El web añade una red normal para permitir acceso desde el navegador; **no tiene bloqueo físico total de salida**. La prohibición de SUNAT se implementa manteniendo únicamente transporte simulado, sin URL/credenciales/cliente real ni selector de modalidad. El servidor PHP y las sesiones por HTTP loopback son solo desarrollo local; no constituyen despliegue apto para internet.

Outbox: `h2:dispatch --limit=10 --scenario=accepted` procesa pendientes con respuestas locales; también admite `rejected` o `uncertain`, y los fallos H3 descritos en su documento. La incierta queda detenida y no se reintenta automáticamente. `h3:recover <UUID>` consulta solo el registro del simulador; `h3:inspect --evidence` informa incidentes/integridad sin borrar ni reenviar. En la UI puede simularse una factura propia con los tres escenarios H2. Códigos SIM y estado fiscal siempre indican que **no hay envío ni CDR SUNAT**.

Límites explícitos: tasa 18% y redondeo half-up pertenecen al ensayo, sin ratificación fiscal real; un concepto, sin descuentos/operaciones especiales. El bloqueo de serie incluye firma y escritura local y limita concurrencia; no se promete capacidad. DB/archivos no son una transacción distribuida: fallo controlado antes de commit limpia solo archivos propios; si el commit puede haber ocurrido se conserva evidencia. Una caída abrupta puede dejar huérfanos privados; tratamiento operativo de huérfanos/retención y resistencia a pérdida de energía siguen pendientes en H7; H3 solo acredita reconciliación de lectura. Usuario SQL de la POC conserva privilegios de inicialización; no es el diseño de roles de producción. [Responsables, hitos y condiciones de resolución](docs/08_H2_FACTURA_FICTICIA.md#cierre-formal-de-h2-con-observaciones--25102026). H3 quedó cerrado exclusivamente en su alcance ficticio, con observaciones conservadas; H4 y las comunicaciones reales con SUNAT siguen sin autorización.

## Referencia histórica y reproducción H1

La base entregada en H1 era una prueba CLI con un único comando, sin HTTP, autenticación ni tablas de facturas. H2 añadió la vertical anterior; esta sección conserva las evidencias y límites de H1. Sigue sin gestor JS ni lock de frontend.

| Componente efectivo | Versión comprobada |
|---|---|
| PHP CLI | 8.3.36, Alpine 3.24.3, usuario 1000:1000 |
| Laravel framework | 13.38.0 |
| Greenter XML / Core | 5.3.0 / 5.3.0 |
| Greenter XMldsig | 5.1.0 |
| PostgreSQL | 16.15, Alpine 3.24.3, usuario de SO postgres (70:70) |
| Composer | 2.10.3; 79 dependencias fijadas en composer.lock |
| OpenSSL / libxml / xmlsec1 | 3.5.9 / 2.13.9 / 1.3.11 (OpenSSL) |

Docker Engine 29.5.3, Desktop 4.77.0 y Compose 5.1.4 ya estaban instalados. El PHP Windows 8.3.30 no se utilizó para ejecutar la POC. No se modificaron sus instalaciones ni los contenedores de otros proyectos.

Se eligió PHP 8.3.36 para probar la rama propuesta sin la regresión reportada de SimpleXML de 8.4.27/8.5.12, con soporte de seguridad de 8.3 hasta diciembre de 2027. Laravel 13 admite PHP 8.3. La compatibilidad demostrada corresponde solo a estas versiones y al recorrido ensayado. Fuentes: [PHP, avisos de octubre](https://www.php.net/archive/2026.php), [soporte PHP](https://www.php.net/supported-versions.php), [incidencia SimpleXML](https://github.com/php/php-src/issues/24488), [Laravel 13](https://laravel.com/framework/docs/13.x/releases), [PostgreSQL soportado](https://www.postgresql.org/support/versioning/).

Las imágenes base y Composer están fijados por digest en [Dockerfile](Dockerfile). Se actualizan paquetes Alpine durante la construcción. Los identificadores efectivos de esta ejecución constan en [inventario](storage/audit/inventory.json). Los paquetes de SO y la base de avisos evolucionan: una reconstrucción posterior exige repetir auditorías; no se promete igualdad binaria futura.

Para reproducir desde PowerShell en esta raíz, con Docker Desktop Linux activo:

```powershell
# Solo si todavía no existe .env: contraseña aleatoria exclusivamente local.
if (-not (Test-Path -LiteralPath '.env')) {
    $h1Password = [Convert]::ToHexString([Security.Cryptography.RandomNumberGenerator]::GetBytes(24))
    $h1Env = (Get-Content -LiteralPath '.env.example' -Raw).Replace('DB_PASSWORD=', 'DB_PASSWORD=' + $h1Password)
    Set-Content -LiteralPath '.env' -Value $h1Env -Encoding utf8
}
New-Item -ItemType Directory -Force -Path storage/audit | Out-Null
docker compose config --quiet
docker compose build --pull --no-cache
if ($LASTEXITCODE -ne 0) { throw 'Falló la construcción' }

# Solo descarga/auditoría de dependencias usa salida a repositorios públicos.
docker run --rm --network bridge --mount "type=bind,source=$((Get-Location).Path),target=/workspace" --env COMPOSER_HOME=/tmp/composer emisor-h1-php:local composer install --no-interaction --no-plugins --no-scripts --prefer-dist --no-progress
if ($LASTEXITCODE -ne 0) { throw 'Falló composer install' }
docker run --rm --network bridge --mount "type=bind,source=$((Get-Location).Path),target=/workspace,readonly" --env COMPOSER_HOME=/tmp/composer emisor-h1-php:local composer audit --locked --no-interaction
if ($LASTEXITCODE -ne 0) { throw 'Falló composer audit' }

docker compose run --rm --no-deps php composer validate --strict
if ($LASTEXITCODE -ne 0) { throw 'Manifiesto inválido' }
docker compose run --rm --no-deps php composer check-platform-reqs
if ($LASTEXITCODE -ne 0) { throw 'Plataforma incompatible' }
docker compose up -d --wait postgres
if ($LASTEXITCODE -ne 0) { throw 'PostgreSQL no arrancó' }
docker compose run --rm php php artisan poc:invoice --database
if ($LASTEXITCODE -ne 0) { throw 'POC fallida' }
docker compose ps
```

La red de ejecución CLI de H1 es `internal: true` y no publica puertos, incluida la BD; el servicio web añadido en H2 tiene el alcance descrito arriba. La POC CLI verifica ausencia de rutas externas IPv4/IPv6. No hay configuración, credenciales SOL, cliente SOAP ni llamadas SUNAT/Beta. Para detener solo este proyecto: `docker compose stop`. No borrar volúmenes ni repetir inicializaciones; cambiar `.env` no cambia la contraseña de un volumen ya creado. En Linux ajustar LOCAL_UID/LOCAL_GID al usuario local; CI lo hace automáticamente.

La factura usa RUC `00000000000` deliberadamente inválido, emisor/receptor ficticios y valores fijos de ejemplo; no implementa cálculo ni reglas tributarias del producto. En cada ejecución genera un certificado autofirmado de prueba de un día y una clave RSA en memoria; **no escribe la clave privada**. Firma y digest se configuran explícitamente con SHA256. Esta elección es del ensayo local, no una ratificación del perfil fiscal SUNAT.

Resultado final: **26 comprobaciones aprobadas**, incluidas generación Greenter, parseo DOM/SimpleXML, firma/digest Greenter, verificación independiente xmlsec1 con clave pública fijada, rechazo de importe y SignatureValue alterados, rechazo de otra clave, serialización, 100 ciclos focalizados del caso GC/SimpleXML y ZIP con conservación de bytes. Laravel conecta por TCP a PostgreSQL y la contraseña errónea es rechazada. No se crearon tablas.

Evidencia generada, ignorada por Git y reproducible:

- [Resultado JSON](storage/app/h1/result.json), [XML firmado ficticio](storage/app/h1/invoice-signed.xml), XML original/alterados, certificados públicos y ZIP en `storage/app/h1/`. Solo `status: passed` y salida 0 completan el ensayo; `running` identifica una ejecución incompleta. Certificado/clave nuevos implican hashes y firmas distintos al repetir.
- [Log final de la POC](storage/audit/poc-final.log), [plataforma](storage/audit/platform.txt), [construcción](storage/audit/build-final.log) e [inventario](storage/audit/inventory.json).
- PostgreSQL conservó identificador de clúster `7700753501989244962` tras reinicio y recreación sin borrar el volumen. No equivale a una prueba de backup/restauración.
- [Arranque desde almacenamiento vacío](storage/audit/postgres-fresh.log) probado sin root ni red y sin gosu, usando tmpfs efímero, con [script reproducible](tests/postgres-fresh.sh). El comando completo está también en [CI](.github/workflows/h1.yml).

Auditorías y problemas encontrados:

- [Composer audit](storage/audit/composer-audit.json): salida 0, sin avisos ni paquetes abandonados en las 79 dependencias fijadas. [Licencias declaradas](storage/audit/licenses.json): 70 MIT, 1 Apache-2.0, 1 BSD-2-Clause, 5 BSD-3-Clause y 2 Nette que permiten elegir BSD-3-Clause o GPL; se registra la alternativa BSD. La auditoría de cierre encontró el [LICENSE de XMldsig](licenses/greenter-xmldsig-LICENSE.txt) en el commit exacto instalado: el dist lo omite por `LICENSE export-ignore`. Se conserva íntegro (blob Git `e54bd03ee82857a6d0f3a82092716b1e6d6002e7`). Hay discrepancia: metadatos BSD-2-Clause, aviso con tres cláusulas, incluida prohibición de atribuir respaldo sin permiso. El texto permite uso/redistribución comercial conservando avisos y condiciones; la aclaración del etiquetado y conservación de avisos sigue asignada antes de distribución comercial. No se modificó vendor ni el lock. [Fuente upstream fijada](https://github.com/thegreenter/xmldsig/blob/3b70c1b1cd1b2be3693666b91be3e5adff620b76/LICENSE).
- Docker Scout exigió login. Se utilizó temporalmente Trivy 0.76.1 oficial, fijado por digest. La imagen PHP Debian inicial reportó 6 críticos/276 altos; se sustituyó por Alpine. La base PostgreSQL reportó 1 crítico/25 altos en el runtime Go del auxiliar gosu; se retiró el auxiliar y el servicio arranca directamente sin root. Los informes iniciales se conservan en `storage/audit/php-image.json` y `postgres-image.json`.
- Imágenes finales: [PHP](storage/audit/php-final.json) y [PostgreSQL](storage/audit/postgres-final.json), **cero hallazgos reportados** en los paquetes detectados a esta fecha. [Metadatos de la base del escáner](storage/audit/trivy-db-metadata.json). No es prueba de ausencia total de vulnerabilidades ni escaneo completo de PHP compilado/Composer copiado; sus versiones/avisos y las dependencias PHP se comprobaron por separado.
- En xmlsec1 1.3 fue necesario `--lax-key-search` para seleccionar la única clave suministrada. Se mantiene `--enabled-key-data rsa`, sin confiar en X509 embebido, y referencias solo vacías. La prueba con otra clave confirma el rechazo. No se relajaron las pruebas negativas.
- Corrección de la auditoría: el [aviso PHP del 23/10](https://www.php.net/archive/2026.php) menciona **ambos** identificadores: la mitigación ya figuraba en CVE-2026-109619 y se anuncia aviso completo para CVE-2026-115560. STACK_INIT no era incorrecto por citar este último; se retira la discrepancia afirmada inicialmente. Se verificó `session.upload_progress.enabled=0` en la POC. La incidencia SimpleXML figura cerrada por commit; eso no sustituye comprobar una versión publicada ni esta prueba focalizada.

Para repetir el escaneo de imágenes (herramienta temporal; solo analiza las imágenes, no ejecuta la POC con salida externa):

```powershell
$h1AuditPath = (Resolve-Path -LiteralPath 'storage/audit').Path
foreach ($h1Image in 'emisor-h1-php:local','emisor-h1-postgres:local') {
    docker run --rm --mount 'type=bind,source=/var/run/docker.sock,target=/var/run/docker.sock,readonly' --mount "type=bind,source=$h1AuditPath,target=/reports" aquasec/trivy:0.76.1@sha256:790fd66d1697395d8693d1acaa1ea702406a20f109b6fc7bac8db623eca6c06c image --scanners vuln --cache-dir /reports/cache --no-progress $h1Image
    if ($LASTEXITCODE -ne 0) { throw 'Escaneo incompleto' }
}
```

[CI mínimo](.github/workflows/h1.yml) instala desde lock, audita Composer y ejecuta H1/H2 y HTTP local. Además de la validación local, [GitHub Actions run 40252416751](https://github.com/KhalidSeraph07/NEW_GREENTER/actions/runs/40252416751) terminó en `success` sobre el [commit inicial `2c46f4834e75faa5e128f97ef3f8244b5295deaf`](https://github.com/KhalidSeraph07/NEW_GREENTER/commit/2c46f4834e75faa5e128f97ef3f8244b5295deaf): **33 pruebas, 168 aserciones y 13 controles HTTP satisfactorios**, comprobados en logs remotos. Git está vinculado a `KhalidSeraph07/NEW_GREENTER`, con `main` siguiendo `origin/main`; `.env`, vendor, artefactos, claves y archivo docs.zip siguen excluidos. AGENTS raíz conserva las 13 reglas y las instrucciones Graphify; la actualización estructural/AST de H2 se realizó sin modelos ni APIs de IA. Se observó diferencia entre skill 0.9.95 y ejecutable 0.9.57, sin actualizar herramientas globales. Los informes locales anteriores al push se conservan como evidencia histórica; el CI remoto solo acredita el commit indicado.

Esta POC no valida XSD, casuística tributaria, cadena de confianza real, CDR, aceptación SUNAT ni requisitos de producción. No prueba custodia real, recuperación fiscal o restauración de backups. Antes de distribuir imágenes también se deben conservar/revisar avisos de sus componentes de SO. Los hallazgos de seguridad se reevalúan antes de cualquier siguiente fase; H2 quedó cerrado con observaciones; H3 ficticio también quedó cerrado con observaciones, sin autorización H4 ni uso real.

Auditoría de cierre del 25/10/2026: **APROBADO CON OBSERVACIONES** para H1 ficticio; ver [dictamen y alcance de las 26 comprobaciones](docs/07_AUDITORIA_CIERRE_H1.md). Posteriormente el propietario aprobó formalmente H1 y autorizó H2 ficticio mediante decisiones separadas. La reejecución de la auditoría usa directorios separados y conserva las evidencias originales. El propietario también aprobó formalmente H2 y H3 ficticios con observaciones; véanse sus registros de cierre.
