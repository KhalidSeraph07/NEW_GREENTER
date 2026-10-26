# H4 — Boleta y RC exclusivamente ficticios

D-07 aprobada para V1; propietario autoriza esta vertical ficticia en rama/PR Draft, sin fusión ni cierre automáticos. H5, envío individual, notas, anulaciones, GRE, contingencia impresa y comunicaciones/identidad/credenciales fiscales reales prohibidos. [Reglas y reservas fiscales D-07](10_D07_BOLETAS.md).

| Entrega | Dependencia | Aceptación verificable |
|---|---|---|
| H4-01 Boleta 03 | D-07 y autorización | Entrada restringida y cálculo H2, B000 independiente de F000, XML UBL 2.1/firma/ZIP privados, replay sin nueva numeración. |
| H4-02 RC inmutable | H4-01 | Un emisor/propietario/fecha por lote, miembros explícitos y únicos, identidad/bytes congelados, UBL 2.0/estructura 1.1, outbox en el commit; D+7 simulado. |
| H4-03 Ticket y consulta | H4-02 | Reserva durable antes del simulador; ticket ficticio no es aceptación; 98/0/99 y constancia ficticia correlacionados; incertidumbre sin reenvío, consultas acotadas, recuperación solo con ticket conservado. |
| H4-04 Evidencias y regresión | H4-01–03 | Concurrencia/idempotencia/fallos de procesos/consulta tardía y ticket perdido; control de propiedad/CSRF; H1/H2/H3 intactos, CI y preservación de filas/archivos preexistentes. |

Se conserva el motor de facturas sin cambios. H4 usa su validador/cálculo puro y los modelos Greenter instalados, con registros/archivos propios para no mezclar outbox ni reconciliación de facturas. Reutiliza los patrones probados de reserva, correlación, replay y conservación poscommit; sin infraestructura nueva. Boleta y RC retienen bloqueo de numeración durante firma/escritura como en el ensayo anterior; no se promete capacidad.

Recorte de altas: un miembro no puede incorporarse en otro RC, incluso si el primero fue rechazado; su corrección requeriría alcance posterior explícito. Esta restricción conservadora del ensayo no se presenta como prohibición SUNAT. Un lote incierto nunca se reconstruye ni se reemplaza para sortear la incertidumbre. El simulador solo consulta por ticket; sin lookup de RC/UUID para recuperar uno perdido.

## Resultados

**Vertical entregada para revisión, sin cierre ni fusión.** Evidencia local [JUnit](../storage/audit/h4/phpunit-verified.xml), [log completo](../storage/audit/h4/full-suite-verified.log), [resumen verificable](../storage/audit/h4/result.json): **106 pruebas / 733 aserciones, cero errores/fallos** (H2 33/168, H3 30/218, H4 43/347). [H1 separado](../storage/audit/h4/h1-regression/result.json): 26 comprobaciones; HTTP real loopback: 13 controles H2 y 17 H4 en `storage/audit/h4/http-*/h2.json` y `h4.json`.

CI verificado del código [`2b0569beebe819cc9c8f8214e214f35e41e88989`](https://github.com/KhalidSeraph07/NEW_GREENTER/commit/2b0569beebe819cc9c8f8214e214f35e41e88989): [push 40264982283](https://github.com/KhalidSeraph07/NEW_GREENTER/actions/runs/40264982283) y [PR 40264986226](https://github.com/KhalidSeraph07/NEW_GREENTER/actions/runs/40264986226), ambos success. El log remoto confirma 106/733, HTTP y preservación; instala/audita el lock, sin dependencias nuevas. No constituye aceptación SUNAT ni nuevo escaneo Trivy. El PR enlaza la verificación de su HEAD documental posterior.

[Snapshot inicial](../storage/audit/h4/preservation-before.json) y [comparación posterior](../storage/audit/h4/preservation-after.log): **1.440 filas preexistentes** de usuarios, facturas, outbox, resultados simulados y eventos H2/H3, y **1.038 archivos de evidencia**, sin cambios de valores/bytes. Migraciones H4 aditivas; no fresh/truncate, borrado de volúmenes ni limpieza de evidencias antiguas. Las pruebas agregan fixtures propias y avanzan correlativos; esto no equivale a restauración/durabilidad física probada.

Los JSON `concurrency-*.json`, `interruption-*.json` y `orphan-*.json` en `storage/audit/h4/` registran PIDs, barreras de ocho procesos, SIGKILL y huellas conservadas. Casos: colisión de miembros, replay, límites D+7, rechazo terminal, respuesta 99 sin constancia, constancia ajena/incompleta/DOCTYPE, consulta fallida, ticket perdido, presupuesto de consulta, fallo de commit y confirmación perdida, caída con/sin ticket durable y respuesta tardía cercada. Firma independiente xmlsec1 y rechazo de importes alterados para boleta y RC. El SQL diferido impide añadir miembros fuera del manifiesto congelado.

Se corrigió un defecto demostrado de los modelos nuevos H4: fechas de creación/actualización perdían el instante UTC al usar hora local. [Reproducción negativa](../storage/audit/h4/timestamps-before.log) y [prueba corregida](../storage/audit/h4/timestamps-after.log); sin reescribir registros ni cambiar modelos H2/H3. Una corrida intermedia falló por ejecutar otro runner sobre la misma BD durante una aserción de contador; se conserva el log y se repitió la suite serial, sin relajar pruebas. **Ejecutar un solo runner por BD**; la concurrencia se ensaya mediante sus procesos internos sincronizados.

Reproducción PowerShell con imágenes/vendor existentes y Docker local preparado:

```powershell
docker compose run --rm php php artisan h2:setup
docker compose run --rm php php artisan h2:setup --testing
docker compose run --rm php vendor/bin/phpunit --testdox --log-junit storage/audit/h4/nueva-corrida.xml
docker compose up -d web
python tests/h4-http-smoke.py
$h4Output = Join-Path (Get-Location).Path ('storage/audit/h4/h1-' + [guid]::NewGuid())
New-Item -ItemType Directory -Path $h4Output | Out-Null
docker compose run --rm --volume "${h4Output}:/workspace/storage/app/h1" php php artisan poc:invoice --database
```

El script HTTP crea un directorio UUID nuevo y ejecuta también los controles H2 sin sobrescribirlos. Para un ensayo independiente de preservación usar `tests/h4-preservation.php before` antes de pruebas y `after` después: before rechaza reemplazar un snapshot existente. No ejecutar consumos globales de la BD manual como prueba; los tests seleccionan sus UUID propios.

Operación ficticia: `h4:process send <UUID>` conserva solo ticket; `h4:process poll <UUID> --scenario=98`, `0` o `99` consulta. Máximo de tres consultas por ciclo de outbox, espera de cinco segundos tras 98, reserva de cinco minutos: parámetros técnicos del ensayo, no plazos SUNAT. Ejecución manual por HTTP/CLI, sin daemon ni scheduler nuevo. `recover <UUID>` identifica el registro local y solo rearma consulta con ticket ya conservado; nunca consulta al simulador por UUID ni vuelve a enviar. Respuestas desconocidas mantienen incertidumbre. Consulta/constancia tardía se conserva aunque venza D+7; el plazo de envío no se convierte en plazo de aceptación. `h4:process inspect` revisa evidencia/huérfanos sin borrado. CLI exit 0 acredita ejecución, no aceptación fiscal.

**Pendientes y condiciones:** revisión/aprobación del propietario antes de cierre H4 o fusión; corrección de RC rechazados, notas/bajas e individual siguen fuera de alcance. Mantener validación de emisor/SEE/PSE y recuperación oficial sin ticket antes de actividad real afectada. Capacidad/bloqueo durante firma, tratamiento de huérfanos/retención, respaldo/restauración y seguridad/roles SQL de producción corresponden a H7; usuarios/entrega/consulta del receptor a H6/H7, y licencia XMldsig antes de distribución comercial. Las respuestas son XML de prueba expresamente marcado, no un parser ni una CDR oficial. Sin aceptación fiscal, homologación, custodia real o garantías de pérdida de energía demostradas.

**Propuesta: cierre de la vertical H4 ficticia con observaciones, pendiente de revisión expresa. H4 sigue abierto; PR #2 Draft sin fusionar. H5 y comunicaciones/certificados/credenciales fiscales reales no autorizados.**
