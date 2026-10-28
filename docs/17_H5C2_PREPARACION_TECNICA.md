# H5-C2 — preparación técnica aprobada; implementación ficticia autorizada

28/10/2026, America/Lima. Base `e9eaa18fb0dd844c46fde788778cd21de034e6ed`. **Alcance funcional aprobado y autorización posterior de implementación exclusivamente ficticia, condicionada al PR Draft previo al código.** H5-A/B/C1 cerrados con observaciones. H5 global incompleto; H6 y comunicaciones/identidades/certificados/credenciales fiscales reales prohibidos.

## 1. Restricciones comprobadas en el repositorio

Análisis estático de migraciones 000001–000008 y consumidores; Graphify usado primero, luego fuentes directas por resultados amplios/truncados. No se inspeccionaron BD vivas ni se ejecutaron pruebas nuevas.

| Área | Reserva y protección vigente | Consecuencia para C2 |
|---|---|---|
| H4 | `h4_boletas` congeladas; `h4_members.boleta_id UNIQUE`; `h4_summaries` identidad/manifiesto/ticket congelados y resultado terminal inmutable. `h4_members_consistent`/`h4_summary_consistent` diferidos exigen membresía exacta. | Otro RC normal no puede incorporar los mismos CPE. No borrar membresías ni cambiar el RC rechazado. |
| H5-A | `h5_notes.original_id UNIQUE`; `h5_note_guard` exige factura aceptada y referencia exacta; resultado terminal sin transición de reapertura. `h5_receipts/events` append-only. | No liberar la reserva NC/ND ni reemitir notas; una factura rechazada no habilita una nota. |
| H5-B | `h5b_notes.original_id UNIQUE`, nota inmutable; `h5b_members.note_id UNIQUE`; `h5b_note_original`, guardia de resumen y consistencia diferida. | Corregir el RC no crea otra nota. Lectores y trigger de elegibilidad hoy apuntan al RC original, no a una eventual sucesora. |
| H5-C1 | `h5c1_members` PK `(kind,document_id)`; grants/miembros/recibos/eventos inmutables; `h5c1_grant_guard`, `h5c1_note_gate`, guards de miembros/sobre/resultado. | Las bajas rechazadas siguen reservando. Conservar no otorgamiento acreditado, aceptación fuente, dependencias y ventana original. |
| Transporte | Outboxes separados: `h4_outbox`, `h5_outbox`, `h5b_outbox`, `h5c1_outbox`; RA/RC máximo un envío por comunicación, leases/tokens y consultas por ticket. Recibos/eventos por identidad. | Nueva comunicación → outbox/ticket propios; nunca reiniciar intentos del padre. RC comparte `h4_counters.RC`; RA usa `h5c1_counters`. |
| Factura H2/H3 | `invoices`, `invoice_series.F000`, `invoice_outbox`, `simulation_receipts`, `invoice_transport_events`; unicidades de identidad/clave y controles de estado/lease. No hay inmutabilidad SQL general de factura/eventos equivalente a H5-B. | Enlace nuevo, contador existente y protección acotada del padre rechazado; no cambiar su estado a “reemitido”. |

## 2. Prueba necesaria, no solo una etiqueta de estado

En la POC toda prueba será ficticia. Antes de operar realmente se requerirá CDR auténtica, verificación de integridad/firma/identidad y validación fiscal; los XML `H4/H5B/H5C1FictitiousConstancia` no lo demuestran.

| Hecho | Evidencia exigible / decisión |
|---|---|
| Rechazo definitivo RA/RC | `sim_rejected`, outbox completado, ticket conservado, respuesta 99 **con** constancia válida y correlacionada a nombre/UUID/ZIP/miembros, recibo y evento terminal concordantes. Además causa concreta y subsanación verificable. |
| Factura rechazada | `simulated_rejected`, outbox completado; respuesta `H2_LOCAL_FAKE`/`SIM-REJECTED` concordante con recibo, identidad, huella y evento/attempt token. Exigir también causa y corrección, operación subsistente y ausencia de contradicciones/dependencias. |
| Aceptación | Aceptación acreditada, incluidas observaciones o evidencia previa incompatible con un rechazo posterior: no corregir esa comunicación ni reemitir esa factura; escalar contradicciones. |
| Incertidumbre | Timeout, excepción, 98 agotado, constancia ausente/ajena/alterada, lease vencido o ticket perdido: conservar bloqueo; no crear sucesora. Recuperación permitida no equivale a corregir. |
| Error técnico | Error local o excepción no prueba rechazo fiscal ni ausencia de procesamiento. La respuesta ficticia `not_processed` de H3 tiene su procedimiento existente; no convertirla en permiso C2. |

**Carencia actual:** 99 y `SIM-REJECTED` no detallan una causa subsanable. Mantener esos históricos; no añadirles una explicación inventada. Los futuros fixtures deberán producir respuestas nuevas con causa, ámbito y evidencia estructurados. Una anotación manual sin respaldo no habilita el flujo. No clasificar solo por rango numérico.

## 3. Corrección de comunicaciones: conservar la reserva, enlazar otra versión

**Recorte aprobado P-01:** una sola sucesora por caso original en esta vertical; sin cadenas, bifurcaciones ni segundo intento de corrección. Si vuelve a rechazarse, queda bloqueada para revisión posterior. Es un límite de producto aprobado, no una regla SUNAT.

Proponer tres relaciones tipadas append-only: `h5c2_h4_corrections`, `h5c2_h5b_corrections`, `h5c2_void_corrections`. Cada una: FK al original y a la sucesora de su propia tabla; original único, sucesora única, actor/fecha, motivo, prueba de rechazo y huella de solicitud/diferencias. Impedir enlaces a otra empresa/familia, autorreferencias, reutilizar una sucesora como padre y adoptar comunicaciones preexistentes. Relaciones solo locales: no inventar campos UBL ni sustituir la referencia al CPE de la nota por una referencia a su RC anterior. Sin tabla polimórfica ni motor general.

La sucesora se guarda en `h4_summaries`, `h5b_summaries` o `h5c1_batches`, con UUID/nombre/correlativo/fecha de generación/firma/ZIP nuevos y outbox propio. **No insertar otra reserva de miembro:** los registros `h*_members` permanecen anclados al original; cada versión conserva su manifiesto íntegro y explícito. Adaptar la consistencia diferida para comparar la sucesora con esa reserva raíz y validar el enlace en el mismo commit, incluida igualdad canónica de todos los campos fiscales de ambos manifiestos, no solo IDs. Los freezes ordinarios siguen rechazando cualquier miembro reservado.

**P-02:** inicialmente solo subsanar un defecto técnico de comunicación acreditado, conservando todos los CPE, orden, importes, referencias, fecha de referencia y operación: RC/1 sigue 1; RC/3 sigue 3; RA sigue RA. Ninguna división/fusión de conjuntos ni edición de XML fiscal histórico. Antes de implementar, fijar un fixture con causa/delta demostrables —por ejemplo firma de comunicación con certificado exclusivamente de ensayo vencido y renovación acreditada— y su política de admisión. Un 99 genérico no es ese caso. No confundir nueva identidad con subsanación suficiente.

Revalidar cada miembro: RC/1 sin aceptación previa incompatible; RA/RC3 mantienen CPE aceptados, no otorgados y no dados de baja, grants/referencias íntegros. Bloquear duplicados, respuestas contradictorias y códigos que indiquen registro previo. **Bloquear una comunicación aceptada no significa rechazar el CPE aceptado que exige una baja elegible.** El rechazo del sobre no borra otras aceptaciones.

Un resolvedor pequeño para estas tres relaciones distinguiría resultado histórico y resultado efectivo. `BoletaNotes/Original`, `Voids/Source` y `h5b_note_original` deberán validar la aceptación de la sucesora y su fecha de recepción real de ensayo. Los lectores de membresía resuelven la reserva raíz. Notas históricas que referencian RC aceptados no cambian: esos RC no admiten sucesoras.

**No se reinician plazos:** RC/1 conserva fecha de emisión y D+7; bajas conservan fechas acreditadas/ventanas de H5-C1, incluido B-01/2957 y bloqueo 2027 pendiente de revalidación. Nuevo ticket, rechazo tardío o nueva generación de archivo no prorrogan la elegibilidad. Consultar resultados tardíos es otro procedimiento.

## 4. Reemisión manual de factura

**P-03:** solo factura 01 propia definitivamente rechazada y subsanable, operación subsistente, mismo emisor/receptor/PEN/perfil V1, sin notas/bajas/dependencias incompatibles. Registrar justificación y diferencias de los únicos campos V1 admitidos (`description`, `quantity`, `unit_price`); recalcular en servidor. No aceptar totales, XML arbitrario ni cambios de receptor/moneda.

Una cuarta relación append-only `h5c2_invoice_reissues` enlaza original único → nueva factura única, actor/causa/prueba/solicitud. Una sola reemisión por caso y no reemitir la sucesora bajo otra clave. Reutilizar `InvoiceIssuer`/`InvoiceInput`/firma/evidencia/outbox; número nuevo de F000, jamás reciclar el rechazado. Enlace solo local, sin inventar un campo UBL.

Bloquear original/clave y contador, impedir colisión con una emisión ordinaria no enlazada y crear factura + enlace + outbox dentro de una transacción exterior. `InvoiceIssuer` ya usa transacción anidable; comprobar específicamente rollback exterior y pérdida de confirmación de COMMIT: ningún hijo enviable sin enlace, ninguna evidencia de commit incierto borrada. Proteger mediante triggers acotados los padres/pruebas enlazados; no cambiar globalmente los motores H2/H3.

No añadir columnas a `invoices`, boletas, notas o eventos: sus atributos completos participan en hashes/referencias congeladas. Los enlaces separados evitan invalidarlas o reescribirlas. La nueva fecha de emisión no acredita fecha de operación ni renueva obligaciones: el esquema actual no registra esta última; hace falta hecho de ensayo explícito y validación fiscal antes del uso real. La ruta controlada evita duplicación dentro del caso; no deduce identidad de ventas iguales por importes.

## 5. Pruebas y riesgos que condicionan la implementación

- SQL: invariantes viejas intactas, enlaces tipados/únicos/inmutables y manifiestos completos; bypass directo, otra empresa, miembros cambiados/duplicados, reutilizar hijo y segunda corrección prohibidos.
- Evidencia: 0/99 sin constancia, fuente/ticket/nombre/huella/miembro ajenos, error ambiguo o sin subsanación, aceptación con advertencias, reserva/otorgamiento/fechas incompatibles → bloqueo.
- Concurrencia: misma clave devuelve mismo hijo; otra carga con esa clave falla; dos claves para un padre producen un solo hijo. Carrera con notas, baja, grant, envío/consulta del padre y contador RC compartido H4/H5-B/C1. Mantener orden de locks existente —incluido B000 en H4—, miembros ordenados y contadores al final de la preparación; probar ausencia de deadlocks.
- SIGKILL antes de commit, después de commit sin respuesta al cliente, antes de envío, tras recepción ficticia pero antes de ticket y durante consulta: replay conserva identidad; un envío por versión; sin ticket no hay recuperación inventada; respuesta tardía cercada por token. Conservar/detectar huérfanos.
- D+7/D+8, D+3 de factura, rechazo/aceptación tardíos y vigencia 2027 con hechos acreditados; ningún `updated_at` como fecha de CDR. El plazo de la operación requiere caso fiscal, no solo fecha nueva.
- Preservación: hashes de todas las filas/archivos previos antes/después; contadores solo avanzan por operaciones nuevas. Regresión completa H1–H5-C1, HTTP, firmas/negativos y CI remoto. No reset/truncate ni sobrescritura de baselines. Plan de restauración sigue en H7.

Riesgos principales: resolvedor y triggers deben coincidir; una aceptación de sucesora no debe quedar invisible ni alterar el padre; rechazo del conjunto no prueba ausencia de registro previo; falta causa detallada en históricos; cambios de atributos pueden romper referencias congeladas. No se propone eliminar unicidades, copiar transportes enteros, Redis ni infraestructura adicional.

## 6. Archivos que eventualmente cambiarían

| Archivos existentes / nuevos propuestos | Motivo acotado |
|---|---|
| Nueva migración posterior a `000008` | Cuatro relaciones, FK/unicidades/guards; adaptar funciones `h4_members_consistent`, `h5b_members_consistent`, `h5c1_consistent` y `h5b_note_original`. No editar migraciones ya aplicadas. |
| `app/Boletas/Preparation.php`, `SummaryTransport.php`; `app/BoletaNotes/SummaryPreparation.php`, `SummaryTransport.php`; `app/Voids/Preparation.php`, `Transport.php` | Preparación específica de sucesora y verificación contra reserva raíz; mismo transporte. |
| `app/BoletaNotes/Original.php`, `app/Voids/Source.php` | Aceptación efectiva y prueba/fecha de la sucesora. No relajar elegibilidad ni cambiar referencias históricas. |
| `app/Boletas/LocalSummarySimulator.php`, `app/BoletaNotes/LocalSummarySimulator.php`, `app/Voids/LocalSimulator.php`, `app/Invoices/LocalSimulation.php` | Escenarios nuevos de causa acreditada; preservar escenarios/recibos anteriores. |
| Nuevos servicios pequeños de prueba de rechazo, corrección, reemisión y resolución de enlace; CLI en `app/Console/Commands/`, registro en `bootstrap/app.php` | Dos operaciones explícitas; reutilizar `InvoiceIssuer`, builders y almacenes existentes. Sus cambios solo si una prueba demuestra necesidad. |
| `tests/H5C2/`, worker/preservación nuevos, `phpunit.xml`, `.github/workflows/h1.yml` | Casos anteriores y regresión. Agregar las excepciones por la ruta C2 sin relajar ni deshabilitar los negativos existentes del freeze ordinario. |

## 7. Base oficial y aprobaciones pendientes

El [manual SUNAT, mayo 2021, §§2.5–2.7/4.1, pp. 20/24/28–29](https://cpe.sunat.gob.pe/sites/default/files/inline-files/manual_programador%20%281%29.pdf) distingue excepción, rechazo y observación: factura rechazada consume numeración y exige otro documento/número; RA/RC rechazados se corrigen como conjunto, sin procesamiento parcial ni invalidar las numeraciones de sus miembros. Permite reutilizar nombre; **nombre nuevo y límite de una sucesora son propuestas de producto**. La recuperación asíncrona documentada requiere ticket; 0/99 necesita examen del contenido. No consta una recuperación RA/RC por UUID.

[RS 000302-2024, modificación del art. 10 del SEE, pp. 6–7](https://www.sunat.gob.pe/legislacion/superin/2024/000302-2024.pdf): no tratar numeración previa como disponible. [RS 000003-2023, art. 3](https://www.sunat.gob.pe/legislacion/superin/2023/000003-2023.pdf): envío individual D+3, distinto de la obligación de emitir. [RS 114-2019](https://www.sunat.gob.pe/legislacion/superin/2019/114-2019.pdf) y [RS 000143-2026, art. 3.4](https://www.sunat.gob.pe/legislacion/superin/2026/000143-2026.pdf): conservar la separación/vigencias de [H5-C1](16_H5C1_BAJAS_FICTICIAS.md); el rechazo no establece una prórroga general.

[Portal oficial](https://cpe.sunat.gob.pe/guias-y-manuales) consultado: sigue anunciando reglas 26/08/2026. Copia local SHA-256 `cb5e871cfe3b81abea7faf25b156e5d35b21e8c4837979350919926612da73b6`: RA filas 9/37/42–44 (2324/2105/2398/2323/2957), RC 10/14/30/35–37 (2223/4443/2663/2987/2282/2957). Esos casos no son una lista automática de errores subsanables. Descarga binaria nueva respondió 403; no se afirma identidad byte a byte remota al corte de hoy. El manual publicado es de 2021: no sustituye ratificación del contrato/reglas vigentes por modalidad.

**Para aprobar el diseño:** P-01 una sucesora sin liberación; P-02 corrección solo técnica del mismo conjunto, con fixture/causa/delta concretos; P-03 reemisión manual 01 con campos V1 y justificación, sin cambiar receptor/perfil. Fiscal + representante deben validar códigos admitidos, duplicados/registro previo, nombre nuevo, plazos/fecha de operación y SEE/PSE antes de actividad real. Casos fuera del recorte quedan bloqueados; V1 no se presenta como fiscalmente completa. Seguridad/operación/restauración H6/H7 y licencia XMldsig antes de distribución conservan responsables y puertas.

La preparación original fue estática y no ejecutó pruebas/CI ni modificó código/migraciones/BD. Posteriormente el propietario aprobó P-01/P-02/P-03 y autorizó implementar únicamente la vertical ficticia, en rama y PR Draft, sin fusionar ni cerrar automáticamente. Se conservan íntegros los límites anteriores.


## Comprobación de alcance antes del código

La autorización posterior coincide con el recorte: una sucesora por caso, reservas y evidencia anterior intactas; corrección técnica del conjunto RA/RC o reemisión manual 01, sin cadenas ni corrección/reemisión individual de notas o boletas. El cambio de autorización de AGENTS se presenta en un commit separado; sus trece reglas generales no se alteran.

Defecto técnico inicial elegido: **entrada XML mal nombrada dentro del ZIP de un documento nuevo de ensayo**. El fixture conserva desde su creación XML firmado, ZIP defectuoso y huellas; un escenario nuevo del receptor ficticio examina ese defecto y devuelve rechazo definitivo con causa `SIM-C2-ZIP-ENTRY` correlacionada. La sucesora reconstruye un ZIP cuyo único nombre interno coincide con su identidad XML; XML/CPE del padre no se reescriben. La reemisión 01 inicial conserva también descripción/cantidad/precio: subsana solo el empaquetado, con nuevo correlativo. No se habilitan ediciones económicas que no resuelvan esta causa.

Elegibilidad se revalida antes de preparar y enviar: rechazo y causa acreditados, sin sucesora/dependencia incompatible; misma operación ficticia declarada, fecha de operación de ensayo explícita y coincidente con emisión del padre en este primer recorte; factura dentro del D+3 original, RC dentro del D+7 original y bajas bajo la política conservadora H5-C1. Nueva generación/ticket no renueva plazos. Rechazos históricos genéricos quedan bloqueados. Únicamente los fixtures nuevos optan por el defecto antes de congelar artefactos; no hay bandera HTTP ni alteración de archivos ya persistidos.
