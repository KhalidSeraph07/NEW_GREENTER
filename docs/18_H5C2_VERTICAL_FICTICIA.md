# H5-C2 — cerrado formalmente con observaciones, exclusivamente ficticio

28/10/2026 · America/Lima. **Cierre aprobado expresamente por el propietario**, sobre `3454135de66c9f885ba6a452ba405f40430bfd30`, del [PR #6](https://github.com/KhalidSeraph07/NEW_GREENTER/pull/6), trabajado en `codex/h5c2-correcciones-ficticias`, base main `e9eaa18fb0dd844c46fde788778cd21de034e6ed`. PR marcado Ready for Review y fusionado mediante merge commit `affa679045833dda5ee12149bbfc813396639cdf`. H5-A/B/C1 siguen cerrados con observaciones; **H5 global no cerrado**. H6 y toda comunicación/identidad/certificado/credencial fiscal real siguen prohibidos. No se declara producto preparado para producción.

La preparación [17](17_H5C2_PREPARACION_TECNICA.md) se publicó en `b5453ae` antes del código. Autorización AGENTS en commit separado `35f23e2`, sin alterar las trece reglas. El anexo `65c12f6`, también previo al código, conserva el contrato ZIP ficticio heredado de factura H2.

## Alcance y diferencias

Única causa admitida: `SIM-C2-ZIP-ENTRY`, creada en nuevos fixtures que congelan desde el inicio un ZIP con entrada `H5C2-INVALID-ENTRY.xml`. El simulador examina el ZIP y correlaciona causa, UUID, XML, manifiesto y huellas. Los rechazos genéricos antiguos no se enriquecen ni se habilitan. No es un código SUNAT.

| Operación | Comportamiento comprobable |
|---|---|
| Corrección RC de boletas, RC de notas B y RA/RC3 de bajas | Una sucesora con identidad, artefactos, firma, outbox y ticket propios. Conserva íntegros orden/miembros/CPE/importes/referencias/fecha fiscal/operación y motivo de baja. |
| Reemisión manual 01 | Nuevo correlativo F000, mismo receptor/PEN/detalle/importes, causa subsanada, operación subsistente y fecha de operación de ensayo explícitas. Sin notas/bajas/dependencias incompatibles. |
| Integridad | Cuatro relaciones tipadas append-only. Reservas permanecen en el original; consistencia diferida compara el manifiesto de cada versión con esa raíz. Una sucesora, sin cadenas, adopción de hijos preexistentes ni liberación. |
| Resultado | Transportes anteriores reutilizados; incertidumbre/ticket perdido no autorizan reenvío. Consulta con ticket conservado, tres intentos por ciclo y máximo nueve para sucesoras RC/RA; recuperación manual. Resultado ficticio correlacionado con outbox, recibo y evento al commit. |
| Lectores | Resuelven la versión efectiva y comprueban su prueba causal; SQL y formulario de notas, referencias de baja y enlaces de consulta reconocen la sucesora. El original conserva su rechazo histórico. Sin rutas ni interfaz nuevas; operaciones C2 por CLI local. |

La subsanación cambia el empaquetado y la identidad de la nueva versión, sin editar documentos anteriores. ZIP RA/RC usa `<nombre>.xml`; factura conserva `invoice.xml` de H2. Esto **no demuestra conformidad fiscal del ZIP real**, XSD, cadena de confianza ni aceptación SUNAT. XML y constancias son exclusivamente ficticios.

No reiniciar ventanas: factura D+3 de emisión original, con fecha de operación ficticia inicialmente igual a esa fecha; RC/1 D+7 de emisión de sus miembros; bajas bajo intersección conservadora H5-C1 de emisión y recepción acreditada de aceptación. Verificación antes de preparar/enviar; consultas tardías conservan resultados. Desde 2027 se bloquea nueva preparación/envío C2 hasta revisar vigencia. Son puertas del ensayo, no habilitación fiscal real.

Cambios funcionales en `app/Corrections/`, CLI `H5C2Process` y migraciones aditivas 000009–000013. Adaptaciones puntuales en empaquetadores, simuladores, transportes y lectores existentes. No se editaron migraciones históricas 000001–000008, infraestructura, modelos fiscales ni datos anteriores. La única excepción de dependencia es el parche de seguridad mínimo MIME descrito abajo. Las migraciones adicionales conservan las ya aplicadas durante las pruebas y corrigen las guardas verificadas; no se ejecuta rollback destructivo.

## Parche de seguridad necesario durante la validación

Un CI posterior fue bloqueado por Composer Audit al incorporar el aviso actualizado CVE-2026-45070. Se verificó la [publicación oficial 7.4.22](https://github.com/symfony/mime/releases/tag/v7.4.22), publicada el 28/10/2026, y se reprodujo la inyección de nombre de parámetro MIME con 7.4.19. Un dry-run acotado y el delta del lock acreditan **solo `symfony/mime` 7.4.19 → 7.4.22**, sin altas, bajas, cambios mayores ni cambios en composer.json. Commit separado `7072376`. Es una corrección de riesgo demostrado, no una actualización preventiva; no se desactiva ni se ignora el aviso. El test de nombre malicioso se bloquea con el parche y el nombre de archivo válido sigue funcionando: dos pruebas / tres aserciones. La página histórica del aviso muestra rangos anteriores; para el parche se usaron el aviso que bloqueó el CI y la release oficial vigente, sin inferir inocuidad por ese contenido anterior.

## Evidencia y reproducción

CI completo del código `0e6bcce532245bcfc5c86ee768fd89c8941af79e`: [Push 40755618574](https://github.com/KhalidSeraph07/NEW_GREENTER/actions/runs/40755618574) y [PR 40755629339](https://github.com/KhalidSeraph07/NEW_GREENTER/actions/runs/40755629339), ambos exitosos: **384 pruebas, 2.390 aserciones, 26 comprobaciones H1 y 71 controles HTTP**, con preservación y Composer Audit sin avisos de vulnerabilidades conocidos al ejecutar. CI remoto efectivamente ejecutado, no inferido de resultados locales. Su baseline es el del runner nuevo; la conservación de históricos locales se contrasta por separado.

| Evidencia local | Resultado |
|---|---|
| H1, salida privada separada | 26 comprobaciones favorables, repetidas después del parche MIME. |
| Regresión H2–H5-C1, suites del JUnit `full.xml` | 276 pruebas / 1.880 aserciones, cero errores/fallos. |
| Iteración completa `full.xml` iniciada antes del ajuste del arnés | 372 casos: tres errores PDO del arnés C2, sin fallos adicionales; log conservado. No se presenta esa invocación como verde. |
| Revalidación C2 dirigida `recheck.xml` | 24 pruebas / 125 aserciones favorables de integridad/resultado/lectores/CLI tras corregir el arnés; adicionalmente respuesta tardía 1 / 4, seguridad MIME 2 / 3 y salida fallida de preservación 1 / 2. Logs separados, sin ocultar la iteración anterior. |
| Preservación local antes/después | 15.468 filas y 12.326 archivos previos íntegros en ambas BD; comparación `preservation-final.log` favorable. Inspección testing: 240 vínculos, una fixture terminal inválida y 65 candidatos huérfanos señalados y conservados. Desarrollo: cero vínculos C2/incidencias. La comprobación posterior al parche pasó leyendo directamente todas las rutas protegidas; resultado en `preservation-direct-final.log` y comprobación independiente del host. |
| HTTP de regresión final | 71 controles favorables sobre loopback, repetidos tras el ajuste de lectores y el parche MIME. |

La regresión remota final sí ejecuta los 384 casos conjuntamente, incluido H5-C2 y los negativos de lectura nuevos; no se confunde con las invocaciones locales anteriores.

PHP 8.3.36, Laravel 13.38.0, Greenter XML/Core 5.3.0, XMldsig 5.1.0 y PostgreSQL 16.15 conservados. Composer validate/platform y lint PHP favorables; sin añadir dependencias o analizadores, ni actualizar Trivy. Composer Audit no acredita licencia ni seguridad integral.

Problemas encontrados y resueltos: la nueva inspección intentaba consultar nombres de carpetas no UUID; se reportan como candidatos sin tratarlos como claves SQL. Una sucesora H4 admitía cambio SQL de estado sin resultado; guarda acotada y correlación diferida exigen prueba durable. El arnés requirió comparación canónica JSONB y capturar PDO con SQLSTATE durante el rechazo del COMMIT, sin relajar los negativos. Se conservan logs fallidos. Una fixture nueva de la iteración adversarial previa a la guarda quedó terminal sin resultado: se conserva sin reconstruirla; la validación de lectura también exige recibo/outbox/evento y la inspección la señala. No se modifican históricos para hacer pasar controles. Los lectores web que consultaban solo el RC raíz se adaptaron y probaron sin cambiar la interfaz. El helper CLI de preservación devolvía 0 tras un error manejado por Laravel: se corrigió su salida y se probó el fallo explícito. Una enumeración posterior omitió 76 rutas existentes; la captura diagnóstica y lectura directa del host/contenedor acreditan que sus huellas seguían coincidiendo. Ahora `after` exige cada ruta del baseline, rechaza ausencias/enlaces (también ancestros) y compara el mismo SHA-256, sin sustituir baseline ni archivos. La causa de la omisión de enumeración no se da por establecida; queda como observación del entorno/operación H7, sin usar esa enumeración para certificar preservación.

Graphify actualizado por AST: 1.170 nodos / 2.614 aristas. Advertencia preexistente de versión CLI/skill conservada; sin upgrades, LLM ni nuevas llamadas API.

Evidencias locales ignoradas por Git en `storage/audit/h5c2/`: baseline protegido `preservation-before.json`, informes/logs de iteración, JUnit completo, H1 con salida separada, HTTP, concurrencia/SIGKILL, resultados negativos de firma y resumen `result.json`. Los negativos solo manipulan fixtures nuevos propios. Inspección conserva huérfanos y reporta candidatos; nunca los elimina automáticamente.

Comandos sobre el entorno Compose existente, sin reinicializar ni borrar datos:

```text
docker compose run --rm php php artisan h2:setup
docker compose run --rm php php artisan h2:setup --testing
docker compose run --rm php vendor/bin/phpunit --testsuite "H5-C2 ficticio" --testdox
docker compose run --rm php vendor/bin/phpunit --testdox
python tests/h5b-http-smoke.py --output-dir storage/audit/h5c2/http-otra-ejecucion
docker compose run --rm php php artisan h5c2:process inspect
```

`h5c2:process fixture|prepare <invoice|h4|h5b|void> --user=<ID> --input=<JSON>` exige operador local y entrada explícita; `fixture` crea solo un documento nuevo. `prepare` recibe original_id, idempotency_key y reason; factura exige además operation_date_test y operation_subsists_test. `send|poll|recover|status <familia> <UUID>` usa exclusivamente simuladores enumerados; factura no usa consulta de tickets RA/RC. `status` diferencia id/estado histórico de effective_id/effective_state; baja efectiva aceptada informa `voided_simulated`. La CLI confía en acceso al equipo, no constituye autenticación de producción.

Preservación: el comando `tests/h5c2-preservation.php before` usa creación exclusiva y no debe repetirse sobre el baseline existente; `after` lo compara. Para repetir una auditoría desde cero usar una copia nueva con su propio baseline; no sustituir el existente. H1 se ejecutó con bind mount alternativo en `storage/audit/h5c2/h1` para conservar su salida anterior.

## Observaciones y puertas conservadas

| Pendiente / responsable | Condición de resolución |
|---|---|
| Fiscal, SEE/PSE, B-01/2957, códigos corregibles/duplicados, plazos/fecha de operación y contratos oficiales — profesional fiscal + representante | Antes de cualquier actividad fiscal real. Una causa ficticia ZIP no acredita catálogo real de subsanación ni recuperación oficial por UUID. Fuentes y límites documentados en preparación 17 y H5-C1. |
| Otorgamiento real — representante + técnico | Antes de bajas reales; solo hechos de ensayo explícitos, sin reconstruir históricos. |
| Otros defectos, notas/boletas individuales, segunda sucesora, rechazo posterior o reserva retenida — propietario + fiscal + técnico | Requieren decisión posterior; permanecen bloqueados. No motor general ni reemisión automática. |
| Seguridad/acceso y aislamiento — H6, técnico + propietario | Antes de exposición o uso real; CLI local y HTTP Basic no acreditan seguridad comercial. |
| Bloqueo durante firma, huérfanos, operación y restauración — H6/H7, técnico + operador | Firma sigue dentro de transacción; detectar/preservar huérfanos no sustituye operación y restauración probada. Sin borrado automático ni cambios preventivos en motores aprobados. |
| Licencia XMldsig — propietario + asesoría competente | Resolver discrepancia y avisos antes de distribuir. Composer/audit y CI no prueban compatibilidad jurídica comercial. |

## Registro formal de cierre y fusión

El propietario aprobó H5-C2 con observaciones y autorizó fusionar el PR #6 sobre el HEAD exacto `3454135de66c9f885ba6a452ba405f40430bfd30`. Aceptó 384 pruebas / 2.390 aserciones, 26 comprobaciones H1 y 71 controles HTTP, así como el parche MIME acotado. Revalidación previa: árbol local limpio, HEAD remoto idéntico, [CI Push 40756439233](https://github.com/KhalidSeraph07/NEW_GREENTER/actions/runs/40756439233) y [CI PR 40756449661](https://github.com/KhalidSeraph07/NEW_GREENTER/actions/runs/40756449661) completed/success, ambos checks exitosos, mergeable/clean, sin solicitudes de revisión, revisiones ni hilos pendientes. La aprobación humana consta en la instrucción del propietario; no se inventa una revisión formal en GitHub.

PR marcado Ready for Review y fusionado mediante [merge commit `affa679045833dda5ee12149bbfc813396639cdf`](https://github.com/KhalidSeraph07/NEW_GREENTER/commit/affa679045833dda5ee12149bbfc813396639cdf), sin squash ni rebase, con condición de HEAD exacto. Padres: main `e9eaa18fb0dd844c46fde788778cd21de034e6ed` y el commit aprobado. Se comprobó igualdad de árboles entre el merge y el HEAD revisado. [CI posterior al merge 40758097232](https://github.com/KhalidSeraph07/NEW_GREENTER/actions/runs/40758097232); su resultado y el del registro documental posterior se verifican antes del informe final, con evidencias separadas en `storage/audit/h5c2/cierre/`.

Este cierre actualiza solo documentación. No modifica código, migraciones, dependencias, infraestructura, bases ni evidencias históricas; tampoco ejecuta pruebas locales que agreguen fixtures. La regresión se comprueba en el runner aislado de GitHub. Se conservan todos los responsables/puertas de la tabla anterior, los **65 candidatos huérfanos**, la **fixture terminal inválida**, la **causa no establecida de la omisión de enumeración** y la **restauración integral aún no demostrada**. Técnico y operador deben abordar estos pendientes en H7 y antes de operación real; conservar/detectar/leer hashes no equivale a limpiar ni restaurar.

**H5-C2 cerrado no equivale a H5 global cerrado.** Solo se prepara la [matriz de aceptación global H5](19_H5_MATRIZ_ACEPTACION_GLOBAL.md) para aprobación expresa posterior. H6 y todas las comunicaciones/identidades/certificados/credenciales fiscales reales continúan sin autorización.
