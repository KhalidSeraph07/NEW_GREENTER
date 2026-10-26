# Auditoría de cierre H1 — 25/10/2026, America/Lima

**Cierre formal posterior al dictamen: aprobado por el propietario en este chat, Decisión 1 del 25/10/2026, con observaciones.** Pendiente: responsable de distribución conserva/aclara licencia XMldsig antes de distribución. La ejecución remota de CI se acreditó posteriormente mediante el [run 40252416751](https://github.com/KhalidSeraph07/NEW_GREENTER/actions/runs/40252416751), exitoso sobre `2c46f4834e75faa5e128f97ef3f8244b5295deaf`. En Decisión 2 separada se autorizó H2 ficticio progresivo según [su plan acotado y cierre formal](08_H2_FACTURA_FICTICIA.md). El propietario aprobó después el cierre formal H2 con observaciones y autorizó H3 exclusivamente ficticio en decisiones separadas; H4 y comunicaciones reales con SUNAT no autorizados. El cuerpo del dictamen conserva el alcance y las evidencias de la auditoría H1 original.

**Dictamen: APROBADO CON OBSERVACIONES, exclusivamente para la POC ficticia local.** La generación/firma y la compatibilidad ensayada son reproducibles. No se encontraron defectos de aplicación que exigieran modificar código. Se corrigieron dos afirmaciones del README y se incorporó el aviso de licencia omitido por el dist de XMldsig. **Al emitir el dictamen original, H2 no estaba autorizado y la aceptación formal H1 aún no se había recibido; las decisiones posteriores se registran arriba. No habilita producción.**

Método: revisión del código y configuración reales, reejecución separada, instalación limpia desde lock, verificación criptográfica con xmlsec1 y contraste con fuentes primarias. Es auditoría técnica en este chat; no se atribuye revisión humana/organizativa a un tercero. No se acepta `status: passed` como evidencia suficiente por sí solo.

La [huella inicial de los archivos](../storage/audit/cierre-h1-20261025/baseline-sha256.json) precede a la reejecución. Los artefactos originales en `storage/app/h1/` se conservaron; las nuevas pruebas escriben en `reproduced/` y `fresh-reproduced/` dentro del directorio de auditoría. Las versiones e IDs efectivos coinciden con el inventario H1; el lock conserva SHA256 `361e2ff197d327c91b7862fdafd488ed633a3fe72bdfaca719857380532791af`.

## Las 26 comprobaciones

Las 26 se reprodujeron con salida 0 de la POC, tanto con vendor existente como tras instalar las **79 dependencias desde cero**, sin actualizar versiones ni ejecutar plugins/scripts. Son 26 aserciones de un recorrido, no 26 pruebas independientes de aceptación fiscal. [Resultado con dependencias limpias](../storage/audit/cierre-h1-20261025/fresh-reproduced/result.json), [log](../storage/audit/cierre-h1-20261025/clean-poc.log). Orden exacto del JSON:

| Nº | Comprobación y lo que efectivamente demuestra |
|---|---|
| 1 | PHP efectivo satisface `>=8.3.36`; observado 8.3.36. No sustituye una auditoría de todas las vulnerabilidades ni acredita otros runtimes. |
| 2 | La configuración cargada tiene `session.upload_progress.enabled=0`; mitigación presente en CLI. No prueba PHP-FPM, que no existe aquí. |
| 3 | UID del proceso PHP distinto de 0; no acredita todos los permisos del host. |
| 4 | No se encontró ruta por defecto IPv4/IPv6; Compose y Docker confirman red interna sin puertos publicados. No es registro histórico de tráfico. |
| 5 | `Greenter\See` no está disponible. Lock/código confirman ausencia de greenter/ws y de envíos; la aserción sola no demuestra ausencia de cualquier biblioteca de red. |
| 6 | Greenter generó XML parseable cuya raíz es Invoice. No validación XSD ni SUNAT. |
| 7 | El XML contiene el identificador ficticio. La auditoría adicional comprobó que **ambos** RUC son `00000000000`; la aserción sola solo busca una cadena. |
| 8 | SignatureMethod declara RSA-SHA256. La operación criptográfica se acredita junto con 10/13, no solo leyendo el atributo. |
| 9 | DigestMethod declara SHA256; misma limitación que 8. |
| 10 | Greenter acepta firma y referencias/digest del documento generado. No valida confianza de una CA ni identidad de un emisor. |
| 11 | El certificado tiene CN de prueba esperado. La generación revisada solo crea certificados sintéticos; el CN por sí solo no demuestra autofirma. |
| 12 | OpenSSL verifica la autofirma del certificado con su propia clave pública; no una cadena de confianza real. |
| 13 | xmlsec1 verifica firma y referencias con la clave pública esperada, sin usar el X509 embebido como autoridad. |
| 14 | Existe el nodo PayableAmount que se alterará en la prueba negativa; es una precondición, no un control tributario. |
| 15 | Greenter rechaza el cambio del importe firmado. |
| 16 | xmlsec1 rechaza ese cambio por fallo de referencia/digest (`REFERENCE`). |
| 17 | Greenter rechaza un SignatureValue manipulado. |
| 18 | xmlsec1 rechaza ese SignatureValue por fallo `SIGNATURE`. |
| 19 | xmlsec1 rechaza otra clave pública por fallo `SIGNATURE`; se comprueba que no confía ciegamente en el certificado incluido en el XML. |
| 20 | Parsear/serializar con SimpleXML conserva una firma válida para esta fixture. No cubre todo XML posible. |
| 21 | El proceso alcanza el final de 100 ciclos del caso focalizado GC/SimpleXML sin caída. `check(true)` registra supervivencia; no mide fugas ni prueba ausencia general de regresiones. |
| 22 | ZipArchive puede crear el ZIP. |
| 23 | El ZIP se puede reabrir. |
| 24 | Recuperar el XML del ZIP conserva exactamente sus bytes. La auditoría también comprobó CRC e integridad SHA256. |
| 25 | Laravel conecta a PostgreSQL por TCP y obtiene la base esperada; no son pruebas de migraciones, transacciones fiscales ni backup. |
| 26 | La conexión con contraseña modificada es rechazada con error de autenticación. No prueba roles de producción ni custodia de secretos reales. |

## Firmas, integridad y comandos observados

Se verificó también el **XML original** sin regenerarlo: xmlsec1 devuelve 0 para el válido y 1 para importe, firma y clave alterados, con los motivos indicados arriba. Los tres juegos de artefactos —original y dos reproducciones— conservan una firma única y una referencia vacía; huellas del XML/certificado, certificado embebido, ambos RUC y ZIP coinciden con sus resultados. [Control independiente de integridad](../storage/audit/cierre-h1-20261025/artifact-integrity.json).

[Registro de comandos de esta auditoría](../storage/audit/cierre-h1-20261025/commands.jsonl): ejecutable, argumentos exactos, horarios, salida esperada/real y log. Incluye Compose, validate/platform, POC aislada, xmlsec1 positivo/negativos, PostgreSQL desde tmpfs vacío sin root/red, instalación limpia, auditoría Composer y Trivy. No se sustituyeron rechazos criptográficos por errores de fichero o comando. Los archivos previos son logs generados, no una transcripción completa e inmutable del historial anterior; la reejecución aporta evidencia nueva con códigos de salida.

El script PostgreSQL de CI se ejecutó localmente con salida 0 desde tmpfs vacío. La base persistente existente no se borró. Se observó cero tablas de usuario; su rol de inicialización es superusuario de BD, limitado a esta POC. UID sin root no equivale a un rol SQL de mínimo privilegio para producción.

## Dependencias, licencias y seguridad

**Composer:** 79 versiones instaladas iguales al lock, sin diferencias; `validate --strict` y `check-platform-reqs` pasan. Auditoría nueva, con acceso solo a repositorios públicos, devuelve 0 y listas de avisos/abandonados vacías. [Log](../storage/audit/cierre-h1-20261025/composer-audit.log). No se configuraron ignorados de advisories en el proyecto; plugins/scripts desactivados durante la instalación. Esto consulta avisos conocidos: no revisa todo el código, PHP compilado, SO, licencias ni seguridad fiscal. [Alcance de Composer audit](https://getcomposer.org/doc/03-cli.md#audit).

**Trivy:** los informes originales finales corresponden a los IDs actuales y solo clasifican paquetes de SO Alpine: 48 PHP y 45 PostgreSQL; no incluyen el vendor montado ni detectan PHP/Composer copiados como paquetes de lenguaje. El nuevo escaneo de ambos IDs con Trivy 0.76.1 repite cero hallazgos reportados: [PHP](../storage/audit/cierre-h1-20261025/php-rescan.json), [PostgreSQL](../storage/audit/cierre-h1-20261025/postgres-rescan.json). Los informes iniciales con vulnerabilidades pertenecen a otras imágenes y se conservan; no se presentaron como cero ni se ignoraron para obtener el resultado final. Cero hallazgos no equivale a ausencia total de vulnerabilidades, y código 0 del escáner por defecto solo indica escaneo completado; se inspeccionaron además las listas de resultados. La construcción usa `apk upgrade`: es repetible como procedimiento, no binariamente idéntica a futuro.

**XMldsig, observación comercial:** Packagist, composer.json y lock del commit `3b70c1b1cd1b2be3693666b91be3e5adff620b76` declaran BSD-2-Clause. El [LICENSE del mismo commit](https://github.com/thegreenter/xmldsig/blob/3b70c1b1cd1b2be3693666b91be3e5adff620b76/LICENSE) contiene **tres cláusulas**, incluida no atribuir respaldo de Robert Richards/contribuyentes sin permiso. [.gitattributes](https://github.com/thegreenter/xmldsig/blob/3b70c1b1cd1b2be3693666b91be3e5adff620b76/.gitattributes) explica la ausencia en el dist: `LICENSE export-ignore`.

Se incorporó [copia íntegra del aviso](../licenses/greenter-xmldsig-LICENSE.txt), sin inventar titulares ni años: blob Git `e54bd03ee82857a6d0f3a82092716b1e6d6002e7`, SHA256 `4be68075383a590a70aa19184fc62554a4e160081bcbd6ec159c9820b3f932f3`. El texto es permisivo y admite uso/redistribución comercial conservando avisos, condiciones y descargo, y sin atribuir patrocinio; no obliga por sí solo a abrir el código propio. Coincide con la estructura [BSD de tres cláusulas de OSI](https://opensource.org/license/bsd-3-clause). La diferencia de etiquetado permanece como observación a aclarar por el responsable de distribución antes de entregar comercialmente el producto/imágenes. No bloquea esta POC privada. Los ocho archivos instalados de XMldsig (composer.json y src) coinciden byte a byte por blob Git con el commit upstream: [comprobación](../storage/audit/cierre-h1-20261025/xmldsig-upstream-integrity.json). No se modificaron licencias upstream, vendor ni composer.lock, ni se contactó a terceros.

**PHP:** se corrige el README anterior: el [aviso oficial del 23/10](https://www.php.net/archive/2026.php) sí menciona CVE-2026-115560 además de la mitigación ya citada en CVE-2026-109619. No había fundamento para decir que STACK_INIT estaba equivocado por ese identificador. Se conservan PHP 8.3.36 y la mitigación cargada; esto no acredita FPM/web ni versiones futuras.

## CI y límites de alcance

En la auditoría original se revisó el [workflow](../.github/workflows/h1.yml): checkout fijado, permisos contents:read, plazo de 20 minutos, instalación desde lock sin plugins/scripts, audit, pruebas offline y parada sin `-v`. Entonces solo se habían validado YAML y comandos **localmente**, sin remoto ni commits. Después de la publicación autorizada, el run citado arriba ejecutó H1/H2 y HTTP en un runner GitHub `ubuntu-24.04`, con conclusión `success`; sus logs registran `OK (33 tests, 168 assertions)` y 13 controles HTTP satisfactorios. Esta evidencia posterior resuelve la observación de ejecución remota para ese commit; no amplía retroactivamente las 26 comprobaciones H1 ni acredita producción.

No se detectaron RUC reales, SOL ni certificados fiscales reales en el código/configuración/artefactos H1 revisados. Existe contraseña aleatoria de **PostgreSQL local** en `.env`, ignorada por Git; no se imprime ni se equipara a credenciales fiscales. No hay claves privadas persistidas en los artefactos. No se encontraron rutas/código de envío SUNAT, módulos comerciales, boletas/notas implementadas, Redis ni despliegue VPS. Frameworks pueden contener bibliotecas genéricas de red: no se deduce su ausencia de la prueba 5. [Resumen de inspección sin valores secretos](../storage/audit/cierre-h1-20261025/scope-secrets-check.json).

Los comandos de generación/verificación se ejecutaron en red interna sin puertos y sin ruta por defecto; solo instalación/auditorías/fuentes públicas utilizaron salida externa, sin endpoints SUNAT. No existe captura exhaustiva del tráfico histórico ni se certifica ausencia universal de secretos en todo el equipo o archivos ajenos. No se realizaron llamadas SUNAT/Beta durante esta auditoría.

Las observaciones H1 fueron aceptadas por el propietario; la licencia sigue pendiente y la ejecución remota de CI tiene la evidencia posterior indicada. Para uso real siguen vigentes las puertas PSE, elegibilidad, credenciales/series, recuperación sin ticket, custodia, respaldo/restauración y producción. **La auditoría original no ejecutó H2; su implementación posterior y cierre formal aprobado se registran por separado. H3 autorizado solo ficticio; H4 y comunicaciones reales no autorizados.**
