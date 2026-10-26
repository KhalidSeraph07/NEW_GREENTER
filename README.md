# Emisor — H4 ficticio cerrado con observaciones

**25/10/2026 · America/Lima.** H0 cerrado; [H1](docs/07_AUDITORIA_CIERRE_H1.md), H2, H3 y [H4 ficticios cerrados con observaciones por aprobación expresa](docs/11_H4_VERTICAL_FICTICIA.md). Licencia XMldsig pendiente antes de distribución. H5 y actividad fiscal real no autorizados.

La [planificación H2](docs/08_H2_FACTURA_FICTICIA.md) registra tareas pequeñas, dependencias, aceptación y evidencia. Implementación: operador ficticio con HTTP Basic, Blade mínimo con CSRF, un concepto/contado/PEN, cálculos enteros, correlativo bloqueado y outbox en una transacción PostgreSQL, XML/firma/ZIP privados, historial y transporte exclusivamente simulado. No hay módulo comercial ni conexión SUNAT/Beta.

[H3 ficticio](docs/09_H3_ROBUSTEZ_FICTICIA.md) incorpora reserva durable de intento, fallos simulados, reintentos seguros acotados, consulta del registro del simulador, reconciliación de solo lectura y alertas CLI. Conserva UI, numeración y XML H2. H3 quedó cerrado con observaciones por aprobación expresa sobre el commit `96b17e6`; PR #1 fusionado mediante merge commit. Comunicaciones reales y RUC/certificados/credenciales fiscales reales siguen sin autorización. Los comandos y evidencias H3 están en ese documento; los informes H2 siguientes conservan su contexto histórico.

[H4: boleta y RC ficticios](docs/11_H4_VERTICAL_FICTICIA.md), con D-07 aprobada para V1: formulario mínimo en `/boletas`, XML/firma/ZIP privados, RC inmutable, ticket y consulta 98/0/99 simulados. Cerrado con observaciones por aprobación expresa; [PR #2](https://github.com/KhalidSeraph07/NEW_GREENTER/pull/2) marcado Ready for Review y fusionado mediante merge commit `6e6366336d211f0b527159ef9a94e2a0f7a59dea`. Facturas H2/H3, dependencias y Compose conservados. Corrección de RC rechazados pendiente de etapa expresamente autorizada antes de habilitar ese flujo real. H5 y toda comunicación/identidad/credencial fiscal real siguen sin autorización.

[H5-A: notas de factura ficticias](docs/13_H5A_NOTAS_FACTURA_FICTICIAS.md), con recorte D-02/AF-06 aprobado: NC 07/01 completa y ND 08/02 por incremento, referencia congelada, XML/firma/ZIP privados y transporte/recuperación ficticios. UI mínima `/invoice-notes`; **H5-A cerrado formalmente con observaciones por aprobación expresa**; [PR #3](https://github.com/KhalidSeraph07/NEW_GREENTER/pull/3) marcado Ready for Review y fusionado mediante merge commit `4aef63653cc5ed3a46cf19f9362156451bfce1df`, sin squash ni rebase. **H5 sigue incompleto.** Una nota por original incluso rechazada/incierta. H5-B (boletas/RC nuevos), H6 y toda actividad fiscal real no autorizados. El informe incluye comandos, pruebas y responsables/puertas pendientes; la RS 165-2021 derogó solo el último párrafo del artículo 22.3.

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

PHPUnit 12.5.40 es dependencia **solo de desarrollo** (PHP 8.3 compatible, 25 paquetes dev); las 79 dependencias runtime H1 conservaron sus versiones. [Soporte del mantenedor](https://phpunit.de/supported-versions.html). PostgreSQL de pruebas: `emisor_h2_test`, separado del uso manual. Las pruebas no borran/resetan la base: agregan fixtures ficticias propias y usan números relativos; guardas bloquean su ejecución sobre otra base.

Evidencias: [JUnit](storage/audit/h2/phpunit.xml), [log PHPUnit](storage/audit/h2/tests.log), [HTTP real](storage/audit/h2/http-smoke.json), [resumen](storage/audit/h2/summary.json), [auditoría Composer runtime + dev](storage/audit/h2/composer-audit.json). Los archivos `storage/audit/h2/concurrency-*.json` registran PIDs, barrera y resultados de ocho procesos para numeración, replays y consumo de outbox; dos procesos disputan una misma clave con contenido distinto. Solo datos sintéticos, sin servicios SUNAT. La regresión H1 se conserva en [directorio separado](storage/audit/h2/h1-regression/result.json).

El proceso web adicional reutiliza la misma imagen PHP: es necesario para el formulario y publica **solo 127.0.0.1:8180**. PostgreSQL no publica puertos. CLI/pruebas conservan red interna sin salida. El web añade una red normal para permitir acceso desde el navegador; **no tiene bloqueo físico total de salida**. La prohibición de SUNAT se implementa manteniendo únicamente transporte simulado, sin URL/credenciales/cliente real ni selector de modalidad. El servidor PHP y HTTP Basic por HTTP son solo desarrollo local; no constituyen despliegue apto para internet.

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
