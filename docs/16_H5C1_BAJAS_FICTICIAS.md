# H5-C1 — bajas ficticias cerradas con observaciones

Autorización expresa del propietario: RA para 01/07/08 F y RC/3 para 03/07/08 B propios, aceptados ficticiamente y con no otorgamiento acreditado. H5-A/B siguen cerrados. H5-C2 (correcciones, reemisión, liberación de reservas), H6 y comunicaciones fiscales reales no autorizados. Implementación revisada en `codex/h5c1-bajas-ficticias`; cierre y fusión del PR #5 aprobados expresamente el 28/10/2026, según registro final.

## Regla temporal documentada antes de programar

Corte 26/10/2026, SEE-Del contribuyente previsto. El art. 14 de la RS 097-2012, sustituido por [RS 114-2019, art. 2.5, pp. 7–8](https://www.sunat.gob.pe/legislacion/superin/2019/114-2019.pdf) y modificado por [RS 206-2019, art. 3.7](https://www.sunat.gob.pe/legislacion/superin/2019/206-2019.pdf), permite dar de baja documentos **no otorgados**: RA para facturas/notas F previamente enviadas; RC de la fecha de emisión para boletas/notas B. Para los enviados/informados con CDR aceptada, el máximo es **siete días calendario contados desde el día siguiente de recibir esa CDR**: recepción D → último día D+7, inclusive, en fecha local Lima. La rama de no informados queda excluida.

La fecha de emisión del CPE, la fecha de generación del CPE, la generación de RA/RC y la recepción de CDR son hechos distintos. `ReferenceDate` identifica los documentos (RA: generación; RC: emisión); `IssueDate` es la generación de la comunicación. Ninguna es por sí misma recepción de CDR. La POC conserva las cuatro fechas y la prueba del resultado aceptado. Los eventos locales de aceptación correlacionados y su `created_at` acreditan **recepción simulada**, sin convertirlos en CDR oficial; `updated_at`, ticket, acuse de envío o silencio no la acreditan. Si falta el evento o no es consistente, se bloquea; no se completa ningún histórico.

[Libro oficial publicado 26/08/2026](https://cpe.sunat.gob.pe/guias-y-manuales), copia/huella S4 de [preparación](15_H5C_PREPARACION_FISCAL.md): RA fila 44 y RC fila 37, error 2957, contienen un control desde **emisión**. Cambios 394–399 incluye una indicación OSE. Pendiente B-01: fiscal + representante deben confirmar su aplicación al SEE previsto. **Política conservadora de esta POC:** exigir simultáneamente recepción simulada D+7 y emisión D+7; la segunda restricción es un bloqueo técnico propio, no una declaración de vencimiento legal. RA solo con generación y emisión coincidentes acreditadas; no inventar una equivalencia universal.

[RS 000048-2026, art. 9, p. 28](https://www.sunat.gob.pe/legislacion/superin/2026/000048-2026.pdf) cambia el cómputo a generación, pero [RS 000143-2026, art. 3.4, p. 3](https://www.sunat.gob.pe/legislacion/superin/2026/000143-2026.pdf) difiere su vigencia al **01/01/2027**. No anticiparla. La política del ensayo se limita al corte 2026; desde 2027 se bloquea la preparación/envío hasta revisión expresa. Las consultas de comunicaciones ya enviadas pueden continuar: el vencimiento no autoriza reenviar ni descartar respuestas tardías.

RC/3 utiliza identidad propia de cada CPE y operación 3, importes originales congelados; para notas, se conserva también la referencia 03 original. El libro RC filas 49–57 exceptúa operación 3 de determinadas obligatoriedades de `BillingReference`; conservar la referencia existente no significa copiar automáticamente el contrato de RC/1. RA UBL 2.0/1.0; RC UBL 2.0/1.1. XML firmado localmente no acredita aceptación SUNAT.

## Entregas y criterios

1. Preparación fiscal y preservación: regla anterior, decisiones separadas, snapshot de BD/archivos antes de migrar; ningún histórico reescrito.
2. Elegibilidad y preparación: afirmaciones de otorgamiento auditables, desconocido bloquea, fechas acreditadas, una reserva por documento, identidad/miembros/manifest inmutables, firma/ZIP privados y outbox en la misma transacción. Bloquear originales con notas y carreras baja/nota/otorgamiento.
3. Transporte exclusivamente local: un envío como máximo, ticket ficticio, consultas acotadas 98/0/99, constancia ficticia íntegra y correlacionada, incertidumbre sin reenvío; rechazo mantiene documento y reserva. Sin recuperación por UUID cuando falta ticket.
4. Verificación: negativos, firmas, concurrencia, idempotencia, SIGKILL antes/después del commit, plazos acreditados, preservación, regresión H1–H5-B y CI remoto. Resultados/commit/PR acreditados en las secciones siguientes.

Pendientes conservados: B-01 y elegibilidad/PSE/validación fiscal (profesional tributario + representante, antes de actividad real); seguridad/autenticación local, operación/respaldos en H6/H7 (técnico + propietario); licencia XMldsig antes de distribución. H5-C2 exige autorización posterior. No declarar H5 global completo.

## Resultado revisable

[PR #5, posteriormente fusionado por autorización expresa](https://github.com/KhalidSeraph07/NEW_GREENTER/pull/5), base main `b434e80083f83e2ddbfec480ac0e53d2834545f6`. Política previa `0a34978`; implementación/pruebas `8caaf4a3fc09c12f11c41ba6d5e70812ca7b32b6`. CI [Push 40443842595](https://github.com/KhalidSeraph07/NEW_GREENTER/actions/runs/40443842595) y [PR 40443875907](https://github.com/KhalidSeraph07/NEW_GREENTER/actions/runs/40443875907) **exitosos**, logs inspeccionados: **276 pruebas / 1.880 aserciones**, incluidas **57 / 460 nuevas H5-C1**; H1 **26** y HTTP anterior **71**, todos satisfactorios. Composer audit sin avisos conocidos al ejecutar; no cubre defectos inéditos ni resuelve licencia.

Diferencias: módulo `app/Voids`, dos migraciones aditivas, `VoidBatch`, configuración fija ficticia y CLI `h5c1:process`. Se comparte el correlativo RC ya existente; RA tiene contador propio. No se altera ningún documento/RC histórico para darlo de baja: el efecto se deriva de una nueva comunicación aceptada. Guardias de una línea, después del replay de H5-A/B, y triggers impiden NC/ND incompatibles con no otorgamiento/reserva de baja. Es una integración requerida por la nueva carrera; sin refactorizar los motores ni cambiar Composer, Docker o infraestructura.

Comportamientos acreditados: seis combinaciones (01, 07/01 F, 08/02 F, 03, 07/01 B, 08/02 B), firmas con `xmlsec1` y manipulación negativa, ZIP exacto, pertenencia/fechas/referencias, desconocido/otorgado bloqueados, D+7/D+8 con eventos de ensayo, vigencia 2027 bloqueada, carreras entre procesos, idempotencia, rechazo sin liberar reservas, 98/0/99/constancia inválida, máximo un envío y nueve consultas (tres por ciclo; recuperación manual), SIGKILL antes de commit/envío/después de ticket, respuesta tardía cercada por token y huérfanos conservados. `inspect` detecta evidencias alteradas/huérfanos/eventos de atención; no borra ni repara automáticamente.

Evidencias locales ignoradas por Git en `storage/audit/h5c1/`: `red.log` (fallo inicial esperado), logs de iteración, `full.log`/`full.xml`, `h1/result.json`, `http-regression/`, `remote-evidence.json`, `preservation-before.json`, `static-review.json` y registros de concurrencia/interrupción. Snapshot inicial: **10.326 filas y 8.602 archivos** de ambas BD/evidencias previas. Los negativos alteran únicamente fixtures nuevos propios. Ningún reset ni eliminación de datos existentes. Graphify actualizado con AST, sin API: 1.008 nodos / 2.293 aristas; advierte diferencia CLI/skill ya existente, sin actualizar dependencias.

Comandos reproducibles (Compose ya inicializado; migraciones aditivas):

```text
docker compose run --rm php php artisan h2:setup
docker compose run --rm php php artisan h2:setup --testing
docker compose run --rm php vendor/bin/phpunit --testsuite "H5-C1 ficticio" --testdox
docker compose run --rm php vendor/bin/phpunit --testdox
python tests/h5b-http-smoke.py --output-dir storage/audit/h5c1/http-nueva-ejecucion
docker compose run --rm php php artisan h5c1:process inspect
```

CLI: `grant --user=<operador> --input=<JSON>` admite solo `kind`, `id`, `state`, `provenance`, `idempotency_key`; `freeze --user=<operador> --input=<JSON>` solo miembros explícitos `{kind,id}`, motivo e identidad idempotente. Tipos: `invoice`, `invoice_note`, `boleta`, `boleta_note`. `send|poll|recover|status <UUID>` conserva identidad/ticket; `--scenario` solo admite escenarios ficticios enumerados en `LocalSimulator`. Sin rutas web nuevas: CLI confía en el acceso al equipo local. Para repetir preservación usar otro directorio de auditoría: el baseline existente está protegido contra sobrescritura. H1 local se ejecutó con un bind mount alternativo para conservar sus evidencias previas.

| Pendiente / responsable | Puerta de resolución |
|---|---|
| B-01, regla 2957/modalidad/vigencia, elegibilidad/PSE y plazos reales — profesional fiscal + representante | Antes de actividad fiscal real; no confundir intersección propia con norma. Desde 2027 bloquear nueva preparación/envío hasta revalidación. |
| Otorgamiento — representante + técnico | Solo afirmación explícita de ensayo con procedencia; no integra entrega real ni reconstruye históricos. Resolver acreditación real antes de habilitar bajas reales. |
| Rechazo de RA/RC, correcciones, reemisión y reservas — propietario + fiscal + técnico | H5-C2 sin autorización. Sin liberación/reenvío; H5 global incompleto. |
| Firma dentro de transacción, huérfanos, operación, respaldo y restauración probada — técnico + propietario | H7 y antes de operación real, conservando puertas anteriores; detección/preservación no equivale a limpieza, restauración de respaldos o recuperación integral demostradas. |
| Autenticación exclusivamente local y seguridad — técnico + propietario | H6 antes de exposición/despliegue. Sin comunicaciones SUNAT/Beta/producción ni certificados/credenciales reales. |
| Discrepancia/avisos XMldsig — propietario + revisión de licencia | Antes de distribución; no resuelta por CI ni por esta vertical. |

## Registro formal de cierre y fusión

**H5-C1 cerrado formalmente con observaciones por aprobación expresa del propietario el 28/10/2026**, sobre el HEAD `e55974b0c64614ebcccbccf5e1eae95715c821dd`. Revalidación previa: HEAD local/remoto exacto, árbol limpio, sin commits nuevos/conflictos, mergeable/clean, [CI Push 40444678857](https://github.com/KhalidSeraph07/NEW_GREENTER/actions/runs/40444678857) y [CI PR 40444682428](https://github.com/KhalidSeraph07/NEW_GREENTER/actions/runs/40444682428) completed/success y ambos checks aprobados. Resultados aceptados: 276 pruebas/1.880 aserciones, H1 26 y HTTP 71 satisfactorios. El cierre se registró primero en el PR para conservar intacto el commit aprobado.

PR #5 marcado Ready for Review y fusionado mediante [merge commit `1debea20a0960c3712b57dd7d4438ff151ff6eb2`](https://github.com/KhalidSeraph07/NEW_GREENTER/commit/1debea20a0960c3712b57dd7d4438ff151ff6eb2), sin squash ni rebase. Padres: main `b434e80083f83e2ddbfec480ac0e53d2834545f6` y el HEAD aprobado. Se verificó igualdad exacta de árboles entre el merge y el commit revisado. El registro posterior en main es exclusivamente documental, sin modificar código funcional, dependencias, infraestructura ni datos/evidencias históricos locales. No se ejecutan pruebas locales que agreguen fixtures durante este cierre; la regresión posterior se verifica en el entorno aislado de GitHub Actions.

CI del merge: [40734375786](https://github.com/KhalidSeraph07/NEW_GREENTER/actions/runs/40734375786). El CI del commit documental posterior se identifica por su SHA en Actions; ambos resultados se verifican antes del informe final. Se conservan íntegros los pendientes de la tabla anterior y sus responsables/puertas; ninguno se declara resuelto por el cierre ficticio.

**H5-C1 cerrado NO significa H5 completo.** H5-A/B siguen cerrados; H5-C2 (corrección de RA/RC rechazados, reemisión de facturas y liberación de reservas) y H6 no autorizados. Continúan prohibidas comunicaciones SUNAT/Beta/producción e identidades/certificados/credenciales fiscales reales. La POC no acredita restauración integral, XSD/fiscalidad completa, CDR oficial ni aceptación SUNAT.
