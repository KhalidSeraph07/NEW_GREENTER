# H5-A — NC 01 y ND 02 de factura, exclusivamente ficticias

**H5-A cerrado formalmente con observaciones por aprobación expresa del propietario, Decisión 1 del 26/10/2026.** Decisión 2 autoriza la fusión del PR #3 mediante merge commit. [D-02/AF-06, exclusiones y reservas fiscales](12_H5_PREPARACION_FISCAL.md). **H5-A cerrado no significa H5 completo.** H5-B (notas sobre boletas y nuevos RC) y H6 no autorizados. Sin SUNAT/Beta/producción ni identidades, credenciales o certificados fiscales reales.

| Entrega | Dependencia | Criterio verificable |
|---|---|---|
| A1 Preparación NC/ND | Recorte aprobado | Factura aceptada simulada propia, otorgamiento ficticio explícito, un ítem/PEN; NC exacta o incremento ND; correlativo por tipo/serie y referencia congelada, XML/firma/ZIP privados, outbox en mismo commit. |
| A2 Ciclo simulado | A1 | Reserva durable, respuestas correlacionadas; incertidumbre sin reenvío; reintentos acotados solo ante prueba ficticia de no procesamiento; recuperación por registro local claramente ficticio; D+3 desde la nota. |
| A3 Acceso e historia | A1/A2 | Formularios mínimos con propiedad/CSRF, descargas privadas y eventos; nunca modificar documento original ni sumar notas incompatibles. |
| A4 Evidencia/regresión | A1–A3 | Carrera NC contra ND, replay, SIGKILL, fallos/negativos, integridad histórica y evidencias; H1–H4 completos y CI remoto del HEAD del PR. |

Se reutiliza el cálculo entero H2 sin alterarlo, Greenter instalado y los patrones de reserva/cercado/consulta de H3. Las notas tienen registros y almacenamiento propios: `invoices` admite solo 01 y el reconciliador H3 solo conoce sus archivos. No se extraen abstracciones de motores correctos ni se alteran sus tablas, migraciones, numeración o pruebas. Continúa el bloqueo de correlativo durante firma; capacidad y tratamiento de huérfanos siguen H7.

Primera entrega: una nota persistida por original, incluso rechazada/incierta; ningún rechazo libera automáticamente la referencia. Replay devuelve la misma nota; rollback previo al commit no consume identidad ni reserva. Otorgamiento se declara explícitamente **ficticio** en la nota, sin reescribir históricos. No acredita entrega real H6. No hay bajas, correcciones RC, parciales, otros motivos, notas de boleta ni nuevos RC.

## Evidencia de la vertical aceptada

Código [`1f52c3883fe77f517f123eb6dd48ac526f1a6903`](https://github.com/KhalidSeraph07/NEW_GREENTER/commit/1f52c3883fe77f517f123eb6dd48ac526f1a6903), integrado por [PR #3](https://github.com/KhalidSeraph07/NEW_GREENTER/pull/3), posteriormente aceptado y fusionado. CI remoto de ese commit [push 40400927324](https://github.com/KhalidSeraph07/NEW_GREENTER/actions/runs/40400927324) y [PR 40400933773](https://github.com/KhalidSeraph07/NEW_GREENTER/actions/runs/40400933773): ambos success. Log remoto examinado: **163 pruebas / 1.022 aserciones**, H2 33/168 + H3 30/218 + H4 43/347 + H5-A **57/289**; H1 separado 26 comprobaciones, HTTP real loopback 13 H2 + 17 H4 + 18 H5-A, todas verdaderas. Los dos controles de preservación remotos pasan. El CI instala/audita el lock existente, sin cambios de dependencias ni nuevo escaneo Trivy.

Local: [suite propia H5-A](../storage/audit/h5a/h5a-verified.xml) 57/289 aprobada, [H1](../storage/audit/h5a/h1-regression/result.json) 26 comprobaciones y [recorrido HTTP](../storage/audit/h5a/http-verified.log) 48 controles aprobados. Regresión completa local **163 pruebas / 1.022 aserciones, cero errores/fallos**, en [JUnit](../storage/audit/h5a/full-suite-verified.xml), [log](../storage/audit/h5a/full-suite-verified.log) y [resumen](../storage/audit/h5a/result.json); no se usa una corrida intermedia como evidencia de éxito.

Baseline [protegido antes de implementar](../storage/audit/h5a/preservation-before.json): **3.523 filas** de usuarios, documentos, outbox, miembros, resultados/eventos H2–H4 en ambas BD y **2.959 archivos**. La [comparación final local](../storage/audit/h5a/preservation-after.log) confirmó todos sus valores/hashes intactos; permite fixtures nuevas y avance de contadores. No significa restauración ni durabilidad ante pérdida física de energía. Los JSON `concurrency-*.json`, `interruption-*.json` y `orphan-*.json` en `storage/audit/h5a/` conservan PIDs/barreras, SIGKILL y huellas.

Los ensayos verifican: NC exacta e incremento ND (incluyendo cantidad fraccionaria/redondeo entero); referencia/identidad y terminales congelados en SQL; ocho procesos y un solo ganador NC/ND; replay también con clave común y originales distintos; outbox fallido sin consumir referencia/correlativo; pérdida de confirmación de commit; proceso muerto antes/después de recepción, resultado no persistido y respuesta tardía cercada; aceptación/rechazo solo con respuesta ficticia completa y correlacionada; incertidumbre sin reenvío, recuperación local sin aumentar intentos; reintento máximo tres ante señal ficticia completa de no procesamiento, con espera; D+3/D+4 desde la nota y consulta posterior al plazo; acceso por propietario/CSRF, archivos privados, firma xmlsec1 y alteración negativa de ambos XML. Boleta, motivos ajenos, importes/receptor/moneda editables y modo real se bloquean.

Corridas intermedias se conservan: se corrigió el arnés para comparar mapas JSONB por contenido y normalizar el tipo numérico de `SUM`; no cambió el dato ni se relajó el invariante. El primer HTTP arrancó antes de que el servidor atendiera; se repitió una vez disponible. No se modificaron pruebas ni motores H2–H4.

## Reproducción

PowerShell, con Docker local preparado y un solo runner por BD (la concurrencia usa procesos internos sincronizados):

```powershell
docker compose up -d --wait postgres
docker compose run --rm php php artisan h2:setup
docker compose run --rm php php artisan h2:setup --testing
$h5Run = 'storage/audit/h5a/revision-' + [guid]::NewGuid()
New-Item -ItemType Directory -Path $h5Run | Out-Null
docker compose run --rm php vendor/bin/phpunit --testdox --log-junit "$h5Run/phpunit.xml"
docker compose up -d web
for ($h5Ready = 0; $h5Ready -lt 30; $h5Ready++) {
    curl.exe -s -o NUL http://127.0.0.1:8180/
    if ($LASTEXITCODE -eq 0) { break }
    Start-Sleep -Seconds 1
}
python tests/h5-http-smoke.py
$h5H1Output = Join-Path (Get-Location).Path ($h5Run + '/h1')
New-Item -ItemType Directory -Path $h5H1Output | Out-Null
docker compose run --rm --volume "${h5H1Output}:/workspace/storage/app/h1" php php artisan poc:invoice --database
docker compose run --rm php php tests/h5-preservation.php after
```

`tests/h5-preservation.php before` se usa antes de ensayar en una copia sin snapshot; se niega a reemplazar el baseline existente. HTTP crea salida UUID propia y ejecuta los controles H2/H4 sin sobrescribir sus informes previos. No fresh/truncate, rollback destructivo, limpieza de volúmenes ni consumo global de outbox. UI: `/invoice-notes`. CLI: `h5:process dispatch <UUID> --scenario=timeout`, `recover <UUID>` e `inspect`; este último solo informa evidencias/huérfanos e incidentes, sin borrarlos. Código de salida 0 de un procesamiento no prueba aceptación fiscal. Sin daemon, scheduler ni alertas externas nuevos.

## Límites y puertas conservadas

- **Técnico/orquestador + fiscal:** una nota por original permanece reservada incluso rechazada, fallida o incierta; corrección/reemisión y acumulación requieren alcance posterior autorizado. No crear una segunda nota para sortear ese bloqueo. H5-B (boletas y nuevos RC), bajas, corrección RC, parciales y otros motivos no implementados.
- **Fiscal + representante:** validar motivo, tratamiento/IGV y emisor/SEE/PSE antes de actividad real afectada. La simulación no demuestra CDR oficial, aceptación SUNAT, validación completa XSD/catálogos ni recuperación oficial operativa; el lookup por UUID consulta únicamente el registro local ficticio.
- **Producto/técnico H6; operación/seguridad H7:** otorgamiento ficticio no acredita entrega/consulta real, autenticación sigue local; retención/huérfanos, custodia, respaldo/restauración y roles SQL de producción pendientes. Bloqueo durante firma/capacidad corresponde a H7; no se promete rendimiento.
- **Distribución:** aclaración/avisos XMldsig antes de distribución comercial. Graphify actualizado por AST, sin llamadas a modelos; persiste aviso de versiones skill/CLI, sin actualizar herramientas globales.

## Registro de cierre y fusión

El propietario aprobó expresamente H5-A con observaciones sobre `b0e8cd23495b90779b62446e0f007a91f6f38f91`. Antes de fusionar se revalidaron ese HEAD exacto, [CI Push 40402502632](https://github.com/KhalidSeraph07/NEW_GREENTER/actions/runs/40402502632) y [CI Pull Request 40402509282](https://github.com/KhalidSeraph07/NEW_GREENTER/actions/runs/40402509282) completed/success, checks aprobados, ausencia de conflictos (mergeable/clean) y árbol local limpio. El cierre se registró primero en el PR, sin modificar el commit revisado.

PR #3 marcado Ready for Review y fusionado mediante [merge commit `4aef63653cc5ed3a46cf19f9362156451bfce1df`](https://github.com/KhalidSeraph07/NEW_GREENTER/commit/4aef63653cc5ed3a46cf19f9362156451bfce1df), sin squash ni rebase. Padres: `f6d298c6858c0207bdc8c7d6988f994349354b81` y el HEAD aprobado. Se comprobó que el árbol del merge coincide exactamente con el aprobado; los Markdown de estado se actualizan en un commit exclusivamente documental posterior. Sin cambios funcionales, de dependencias o infraestructura ni operaciones sobre BD/evidencias locales durante este cierre.

CI del merge: [ejecución 40403645385](https://github.com/KhalidSeraph07/NEW_GREENTER/actions/runs/40403645385). El CI del commit documental posterior se identifica por su SHA en GitHub Actions; ambos resultados se verifican antes del informe final. Se conservan íntegramente responsables y puertas de la sección anterior; el cierre no acredita resolución de corrección/reemisión, validación fiscal real, seguridad/operación ni licencia.

**H5-A cerrado formalmente con observaciones. H5 incompleto. H5-B (notas sobre boletas y nuevos RC), H6, comunicaciones SUNAT/Beta/producción e identidades/certificados/credenciales fiscales reales no autorizados.**
