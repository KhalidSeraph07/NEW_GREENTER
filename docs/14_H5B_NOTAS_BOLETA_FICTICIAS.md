# H5-B — notas sobre boletas y RC ficticios

Estado: autorizado para implementación y revisión; no cerrado. Base `1d8ac51acc9f6e334aba129acb3f41b626e48ecd`. H5-A cerrado con observaciones, sin reapertura. H6 y toda comunicación/identidad fiscal real continúan prohibidos.

## Comprobación fiscal previa al código RC (26/10/2026)

Se consultó el [portal SUNAT](https://cpe.sunat.gob.pe/guias-y-manuales) y se descargó nuevamente el [libro publicado el 26/08/2026](https://cpe.sunat.gob.pe/sites/default/files/2026-08/Reglas%20de%20validaci%C3%B3n%20-%20actualizado%20al%2026.08.2026.xlsx): 855.113 bytes, SHA-256 `cb5e871cfe3b81abea7faf25b156e5d35b21e8c4837979350919926612da73b6`, idéntico a la copia conservada. Hoja `Resumen Diario1_1`, filas 6–14, 28–39, 49–57, 76–80. Metadatos locales: `storage/audit/h5b/fuentes.json` y `fuentes-esquema.json`; PDF oficiales descargados sin credenciales y conservados con sus huellas.

La [guía RC versión 2, enero 2018, publicada actualmente](https://cpe.sunat.gob.pe/sites/default/files/inline-files/GUIA_Resumen_de_Boletas_11-01-2018%20%282%29_2_0%20%281%29_0.pdf), pp. 5, 9, 11–13, distingue la identidad de la nota (tipo 07/08, serie B y número) de la boleta afectada: `SummaryDocumentsLine/BillingReference/InvoiceDocumentReference/{ID,DocumentTypeCode}`. Se usará tipo afectado 03, no se deducen campos de la línea 03. UBL 2.0 / CustomizationID 1.1; ReferenceDate es la fecha de las notas y IssueDate la generación del nuevo RC. ConditionCode 1 adiciona la nota; NC 01 no equivale a ConditionCode 3 ni modifica el RC histórico.

El [plazo publicado](https://orientacion.sunat.gob.pe/04-resumen-diario-boleta-de-venta-electronica) es D+7 desde la emisión de las notas. Se controla en preparación y envío ficticio; no se atribuye a SUNAT nuestro bloqueo conservador al vencerlo (la fila 14 del libro señala observación 4443). El [Manual del Programador](https://cpe.sunat.gob.pe/sites/default/files/inline-files/manual_programador%20%281%29.pdf), pp. 17–21, describe ticket asíncrono y consulta 98/0/99; 0/99 requieren examinar el contenido. No demuestra recuperación de ticket perdido por UUID.

La aplicabilidad al emisor, tratamiento fiscal/IGV, SEE/PSE y vigencias futuras siguen condicionados a fiscal + representante antes de actividad real. Las reglas futuras del libro no se adelantan. Se conservan D-02/AF-06 y sus pendientes; RS 165-2021 solo derogó el último párrafo del artículo 22.3.

## Entregas y aceptación

1. Original y nota: boleta propia, otorgamiento declarado ficticio, RC aceptado correlacionado, referencia congelada, NC completa/ND incremento positivo, una nota permanente por original, correlativo independiente, XML/firma/ZIP privados. Sin envío individual.
2. RC nuevo de notas: miembros propios explícitos, misma fecha de nota, contenido/identidad/manifiesto congelados y outbox en un commit. Numeración RC comparte el contador existente para evitar colisiones, sin alterar documentos históricos.
3. Transporte: simulador local, ticket ficticio persistido, 98 acotado, 0/99 solo tras correlación íntegra; incertidumbre bloquea reenvío. Recuperación solo de consulta con ticket conservado. Historial/alertas mínimos y evidencia sin borrado.
4. Pruebas: NC contra ND, claves repetidas, referencias/fechas/propiedad incorrectas, firmas y alteraciones, rechazo/incertidumbre, SIGKILL, commit fallido, preservación, regresión H1–H5-A y CI remoto. Rama y PR Draft; sin fusión automática.

## Puertas conservadas

- Técnico/orquestador + fiscal: rechazo/incertidumbre no libera original ni miembro; corrección/reemisión y acumulación requieren autorización posterior. Sin bajas, corrección RC, parciales ni otros motivos.
- Fiscal + representante: validación real antes de actividad fiscal. La constancia simulada no es CDR ni prueba aceptación o recuperación oficial; no se acredita validación fiscal completa.
- Producto/técnico H6: acceso, entrega y consulta reales; autenticación local sigue siendo una limitación.
- Operación/seguridad H7: bloqueo transaccional durante firma, capacidad, retención/huérfanos, custodia, respaldo/restauración y roles SQL.
- Distribución: discrepancia/avisos XMldsig antes de distribución comercial.

## Contratos de esta entrega

- La nota conserva estado `prepared` inmutable; su resultado de comunicación se consulta en su RC, separado de la preparación. Una nota solo tiene un miembro RC y un original solo una nota; ni rechazo ni incertidumbre liberan esas reservas.
- Las series ficticias son BC00/BD00. Los RC H4/H5-B comparten el contador RC existente para evitar identidad repetida; H5-B no reinicia numeración por fecha y bloquea agotamiento a 99.999. No cambia el algoritmo/motor H4 ni sus documentos.
- Reutilización directa: validación/cálculo entero H2, hash canónico H5-A y creación/consulta H4 de originales. XML de notas y transporte RC permanecen en `app/BoletaNotes`, con contratos propios para referencia 03 y huellas completas de miembros; se conserva el motor aprobado, sin refactorización transversal.
- La ventana D+7 se cuenta desde la nota; una consulta tardía no amplía el envío. Tres consultas 98 por ciclo con cinco segundos entre ellas; rearme controlado solo con ticket. La constancia es XML propio identificado `H5BFictitiousConstancia`, sin apariencia de CDR oficial.
- `h5b:process inspect` informa evidencias ausentes/alteradas, huérfanos e incidentes; no borra ni adjunta archivos encontrados a documentos existentes. SIGKILL puede dejar archivos huérfanos que se conservan.

## Evidencias y reproducción

El commit de código/pruebas `b8d6fc50bac8767771436f6f4602dba902f1ddd4` pasó [CI Push 40417908717](https://github.com/KhalidSeraph07/NEW_GREENTER/actions/runs/40417908717) y [CI Pull Request 40417946469](https://github.com/KhalidSeraph07/NEW_GREENTER/actions/runs/40417946469), ambos completed/success. La corrección del arnés `b1726c174dab7ed725ebef351404674d56cee071` también pasó [Push 40418562984](https://github.com/KhalidSeraph07/NEW_GREENTER/actions/runs/40418562984) y [PR 40418569697](https://github.com/KhalidSeraph07/NEW_GREENTER/actions/runs/40418569697), con los mismos resultados. Logs de ambos examinados: **219 pruebas / 1.420 aserciones**, sin fallos/errores/omisiones; H2 33/168, H3 30/218, H4 43/347, H5-A 57/289 y H5-B **56/398**. H1 separado: 26/26. HTTP loopback: 13 H2 + 17 H4 + 18 H5-A + 23 H5-B = **71/71**. Controles remotos de preservación antes/después positivos. Composer validate/platform/audit aprobados; no dependencias nuevas ni actualización de imágenes. No se ejecutó nuevo Trivy, análisis estático con herramientas no instaladas ni medición de cobertura.

Hay evidencia de ocho procesos concurrentes NC contra ND, misma clave y misma referencia, reserva RC y envío/consulta; SIGKILL durante preparación de nota/RC, antes/después de recepción y tras ticket persistido; respuesta tardía cercada, fallo de persistencia final y confirmación de commit perdida. `xmlsec1` verifica NC/ND/RC y rechaza XML con referencia alterada. Se ensayan 98, 0, 99 sin constancia, huella/miembro/ticket/RC incorrectos, timeout, presupuesto agotado, plazos D+7/D+8, acceso/CSRF y conservación de originales. Ningún resultado acredita aceptación o recuperación SUNAT.

Se conservaron las corridas intermedias locales, incluidas la prueba roja sin implementación, la ambigüedad SQL de la función nueva, comparaciones del arnés de objetos/JSONB y el helper CLI que requería Mockery. Se corrigieron la función H5-B y el arnés sin agregar dependencias ni relajar invariantes. La función nueva se reemplazó únicamente en la BD local de pruebas ya migrada; no se borraron datos ni migraciones. La migración completa se comprobó luego desde cero en el runner remoto. La regresión local definitiva `storage/audit/h5b/regression-final.xml` y `.log` confirmó también 219 pruebas/1.420 aserciones, cero errores/fallos/omisiones (701,996 s). H1 separado `h1/result.json`: 26 controles; recorrido `http-ba0688bf-beff-46e5-b8ce-436e9cd1f069/`: 71 controles satisfactorios. La salida HTTP local conserva la etiqueta histórica anterior al cambio diagnóstico del arnés; su condición y resultado 404 son idénticos. No se sobrescribieron evidencias H1–H5-A. `preservation-after.log` comprobó intactas las **6.216 filas y 5.065 archivos preexistentes**, mediante valores y huellas; `result.json` consolida resultados y huellas. Graphify actualizado por AST, sin llamadas a modelos (persisten avisos de versión CLI/skill; no se actualizaron herramientas).

Reproducción PowerShell (Docker preparado; un solo runner PHPUnit por BD; nunca `fresh`, `truncate` ni limpieza de volúmenes):

```powershell
docker compose up -d --wait postgres
docker compose run --rm php php artisan h2:setup
docker compose run --rm php php artisan h2:setup --testing
if (!(Test-Path storage/audit/h5b/preservation-before.json)) {
    docker compose run --rm php php tests/h5b-preservation.php before
}
$h5bRun = 'storage/audit/h5b/revision-' + [guid]::NewGuid()
New-Item -ItemType Directory -Path $h5bRun | Out-Null
docker compose run --rm php vendor/bin/phpunit --testdox --log-junit "$h5bRun/phpunit.xml"
$h5bH1 = Join-Path (Get-Location).Path ($h5bRun + '/h1')
New-Item -ItemType Directory -Path $h5bH1 | Out-Null
docker compose run --rm --volume "${h5bH1}:/workspace/storage/app/h1" php php artisan poc:invoice --database
docker compose up -d web
python tests/h5b-http-smoke.py --output-dir "$h5bRun/http"
docker compose run --rm php php tests/h5b-preservation.php after
```

El control HTTP heredado de `/boleta-notes` conserva exactamente su condición 404; solo se renombró su etiqueta de informe, antes `h5b_not_implemented`, porque H5-B ahora existe en `/receipt-notes`. No se relajaron pruebas ni se modificó el motor H5-A.

El snapshot previo se niega a sobrescribirse. El arnés agrega fixtures propios con UUID; no limpia outboxes globales ni repara históricos. Los contadores avanzan por operaciones nuevas legítimas y se excluyen de la comparación de documentos previos. Los archivos de evidencia se mantienen privados e ignorados por Git en `storage/audit/h5b/`; el CI conserva resultados en sus logs.

**Propuesta:** revisión de código y evidencias del [PR #4 Draft](https://github.com/KhalidSeraph07/NEW_GREENTER/pull/4), con posibilidad de cierre ficticio con las observaciones y responsables anteriores. Pendiente de aprobación expresa del propietario; no fusionar automáticamente. H5-B y H5 no se declaran cerrados. H6 y toda actividad/identidad fiscal real siguen prohibidos.
