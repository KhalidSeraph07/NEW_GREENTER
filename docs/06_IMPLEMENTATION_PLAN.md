# Plan de trabajo y puertas de calidad — H0, versión 0.3

**Actualización de ejecución 25/10/2026:** el propietario cerró H1 con observaciones y autorizó H2 exclusivamente ficticio, progresivo y sin SUNAT/Beta. [Tareas, dependencias y aceptación H2](08_H2_FACTURA_FICTICIA.md). El resto de este plan conserva su referencia histórica; H2 no está cerrado y H3 no autorizado.

**Planificación H0 v0.3 del 24/10/2026. Estado actualizado el 25/10/2026:** H0 cerrado por aprobación expresa del propietario y H1 autorizado por separado, solo POC técnica ficticia local. H2 exige nueva autorización. Fuente: Plan Maestro §§5–8 y acta de cierre. Las situaciones y puertas siguientes son la planificación aprobada; no acreditan validaciones posteriores.

La [adenda confirmada por el propietario](../DOCS_GREENTER/ADENDA_H0_MODELO_OPERATIVO.md) sustituye el modelo compartido por instalación independiente administrada y ajusta la puerta: H0 documenta V1 propuesta, responsabilidades y pendientes con límite; dictamen PSE, elegibilidad y validaciones reales se exigen antes de prestar actividades reguladas/piloto real, y AF-04 antes de activar el flujo real afectado. No son requisitos de una POC H1 ficticia. **Registrar este ajuste no aprueba H0 ni autoriza H1.**

## Backlog inmediato H0

| ID / prioridad | Objetivo, alcance y exclusión | Dependencia / responsable | Terminado y evidencia | Situación |
|---|---|---|---|---|
| H0-01 / P0 | Leer Plan completo, AGENTS y guía; inventariar estado. Sin STACK_INIT ni inicialización. | Orquestador | Ubicaciones y límites registrados en control H0. | Realizado documentalmente. |
| H0-02 / P0 | Registrar instalación por cliente/RUC inicial y V1 propuesta; sin inventar perfil del emisor real. | Propietario + orquestador | D-01 aprobada, PRD/exclusiones propuestos y pendientes del cliente asignados a sus puertas. | Adenda incorporada; alcance/firma final H0 pendientes. |
| H0-03 / P0 | Conservar evidencia fiscal/archivo 2026 y precisar encargo de dictamen de la operación administrada. | Fiscal + orquestador | Reparto de actividades de adenda y fuentes disponibles; dictamen/elegibilidad reales con responsable y bloqueo previo a prestación/H8. | Evidencia preservada; dictamen pendiente sin impedir por sí solo POC ficticia. |
| H0-04 / P0 | Precisar incidente de respuesta incierta/ticket ausente y puerta de recuperación efectiva. | Proveedor/soporte + representante/fiscal | Contención, expediente y escalamiento definidos; pendiente de resolución/prueba antes de activar flujo real. No llamar recuperación a la mera contención. | Política registrada; AF-04 continúa pendiente para flujo real, según adenda. |
| H0-05 / P0 | Registrar Compose por cliente, custodia técnica del proveedor, respaldo separado y salida. | Propietario, proveedor/seguridad y orquestador | Responsables/garantías mínimos definidos, controles a demostrar y condiciones comerciales antes de H8. Sin proveedor/precio/capacidad fijados. | Modelo incorporado; evidencia técnica y acuerdos pendientes en su hito. |
| H0-06 / P0 | Revisar paquete de siete documentos, dependencias y riesgos. | H0-02–H0-05; revisor técnico + fiscal + propietario | Acta de cierre H0 con versión, alcance y pendientes compatibles con inicio. | Pendiente de revisión y firma. |

P0 es necesario para cerrar H0, no autorización para programar. La consulta de normas y la preparación documental son el trabajo autorizado actual. No existe estimación de horas aprobada.

## Hitos posteriores, sujetos a autorización

| Hito | Trabajo acotado y entregable | Dependencias | Validación/puerta del Plan |
|---|---|---|---|
| H1 | Compatibilidad PHP/Laravel/Greenter, Compose/PostgreSQL y firma ficticia; CI mínimo; frontend ratificado o aplazado justificadamente | H0 cerrado y **autorización explícita de H1** | Instalación reproducible, versiones/licencias registradas, firma verificable. STACK_INIT solo si corresponde, una vez. |
| H2 | Base segura y una factura completa: acceso, empresa, validación, numeración, archivos, trabajo durable y envío de prueba | H1; MF de factura aprobadas | Demostración completa con evidencia del ambiente real usado, sin confundir mocks, Beta y producción. |
| H3 | Robustez de factura: incertidumbre, consulta, idempotencia, plazos, reintentos y recuperación | H2 y procedimiento MF-09 | Fallos y concurrencia sin duplicación ni pérdida; CDR preservada; notificación operativa según canal aprobado. |
| H4 | Boleta y comunicación elegida, entrega/representación, ticket y recuperación | H3; ruta D-07 revisada para el ensayo y política AF-04 documentada | Casos positivos/negativos y evidencia vinculada; sin activación real hasta resolver/validar AF-04 y demás requisitos del flujo. |
| H5 | NC/ND aprobadas, bajas y correcciones procedentes | H3–H4; motivos y plazos revisados | Vínculos, elegibilidad y escenarios negativos; documentos originales íntegros. |
| H6 | Completar configuración, formularios, búsqueda/filtros, permisos, consulta/descarga del receptor e incidencias | Flujos fiscales previos; D-08 | Empresa opera V1 desde UI sin intervención habitual de desarrollador. UI mínima ya acompaña H2–H5. |
| H7 | Seguridad/E2E, despliegue por cliente, alertas, respaldo cifrado separado del VPS, restauración, rollback y retirada/exportación controlada | Flujos V1, custodia técnica y modelo operativo | Sin defectos críticos; aislamiento, restauración y salida demostrados; controles de acceso administrativo y claves revisados. |
| H8 | Piloto real restringido y aceptación comercial | H7; dictamen PSE/SEE aplicable, emisor/series/casuística autorizados y condiciones del servicio aceptadas | Evidencia fiscal y decisión humana de liberación; no iniciar actividades reguladas antes de cumplir validación aplicable. |

No saltar a H4 con una boleta que solo genera XML, ni a H8 porque pasó CI. La prueba de producción que Beta no cubra se planifica y autoriza expresamente; nunca se simula como si hubiera ocurrido.

## Entregables propuestos por cambio revisable

No se abrieron PRs: el directorio actual no es un repositorio Git. Los siguientes paquetes son planificación para cambios futuros, no ramas ni tareas despachadas.

| Paquete futuro | Alcance y archivos/módulos previstos | Evidencia mínima | Exclusión |
|---|---|---|---|
| H1-A | Entorno, manifiestos/locks, Compose, configuración ficticia y CI mínimo | Construcción reproducible y versiones/avisos/licencia | Emisión real, Redis preventivo y módulos de negocio. |
| H1-B | Prueba mínima de biblioteca/adaptación y documento de compatibilidad | XML/firma verificables; limitaciones escritas | Construir todos los documentos V1. |
| H2-A | Acceso, pertenencia, empresa/configuración y archivos privados | Negativos de autorización/carga y ausencia de exposición | Gestión de suscripciones/CRM. |
| H2-B | Primera factura y persistencia de operación/identidad/artefactos/outbox | Camino completo y fallos de persistencia/concurrencia relevantes | Boletas/notas antes de estabilizar factura. |
| H2-C | Transporte y registro del resultado, interfaz mínima | Evidencia saneada, ambiente y estado sin falsos éxitos | Recuperación universal no demostrada. |
| H3-A | Conciliación, reintentos limitados y atención de incidentes de factura | Caída posenvío, consultas e idempotencia | Generalizar adaptadores para países/proveedores futuros. |
| H7-A | Despliegue independiente, accesos mínimos, alertas y respaldo/restauración por cliente | Restauración desde respaldo cifrado fuera del VPS con base/archivos/claves coherentes; conciliación antes de reanudar | Consola global, Kubernetes, HA multi-región o SLA no probado. |
| H7-B | Procedimiento de retirada, exportación y revocación acordado | Exportación recuperable e íntegra, recepción autorizada y revocación comprobada sin pérdida de evidencia exigible | Destrucción de datos por conveniencia o retención indefinida no acordada. |
| H4–H8 | Dividir al aprobar cada hito según flujo y evidencia exigida | Una tarea por objetivo verificable y revisión del cambio real | Descomposición detallada especulativa ahora. |

Antes de ejecutar, cada tarea tendrá objetivo, alcance/exclusiones, módulos/archivos reales afectados, dependencias, responsable, escenarios/pruebas, definición de terminado y evidencia. H0 no inventa nombres de archivos de una aplicación inexistente.

## Escenarios críticos del Plan y cobertura prevista

Es una asignación de pruebas futuras, no un reporte de ejecución.

| Escenario | Hito responsable | Criterio verificable |
|---|---|---|
| Solicitudes repetidas/concurrentes de la misma operación | H2/H3 | Una identidad y operación lógica; conflicto si cambia contenido. |
| Operadores concurrentes de una misma serie | H2/H3 | Unicidad y consistencia de correlativos. |
| Conexión perdida tras recepción / worker caído después de enviar | H3; H4–H5 para comunicaciones | Incertidumbre sin regeneración; AF-04 y vía efectiva revisados/probados antes de activar el flujo real, sin exigirlo a POC H1 ficticia. |
| Fault técnico o error de negocio SUNAT | H2/H3 | Clasificación, evidencia y ausencia de bucle ciego. |
| Redis no disponible, si se usa | H3/H7 | Pendientes durables recuperables. |
| PostgreSQL o almacenamiento no disponibles | H2/H7 | Nuevas emisiones inseguras bloqueadas. |
| Certificado vencido/revocado/ajeno; credencial incorrecta | H2/H7 | Firma/envío afectados detenidos y diagnóstico saneado. |
| Cambio de certificado, regla o catálogo | H3/H7 | Evidencia histórica y contenido preparados se preservan. |
| Usuario A pide recurso de B / receptor pide documento ajeno | H2/H6/H7 | Denegación desde servidor y almacenamiento privado. |
| Restaurar respaldo de base, archivos y claves | H7 | Coherencia, huellas y conciliación antes de retomar envíos. |
| Aceptación, observación y rechazo | H2–H5 | UI coincide con evidencia y objeto fiscal correcto. |
| Plazo agotado o ticket sin resultado | H3–H5 | Incidencia/escalamiento documentados; no éxito por silencio. |
| Despliegue fallido | H7 | Rollback compatible con datos/evidencias conservados. |
| Instalaciones de clientes distintos | H7 | Usuarios, BD, secretos, archivos y respaldos no se mezclan; restauración/aplicación no apuntan al cliente equivocado. |
| Retirada del servicio | H7, condiciones aceptadas antes de H8 | Entrega/exportación verificables, revocación de accesos y conservación residual según obligación/acuerdo. |

## Dependencias y decisiones que limitan el camino crítico

Modelo independiente/reparto operativo y V1 propuesta → pendientes con puerta y confirmación expresa H0 → autorización separada H1 → compatibilidad ficticia → diseño/controles y factura → robustez → boletas/notas → despliegue/restauración/salida por cliente → piloto real con dictamen, elegibilidad y acuerdos validados.

Conservar documentos y facilitar acceso al receptor son parte del producto. Proveedor custodia/administra técnicamente; cliente conserva titularidad documental y obligaciones legales. La adenda permite dejar dictamen y recuperación efectiva como pendientes declarados para sus puertas reales; no convertir esos pendientes en validaciones aprobadas ni usarlos para emitir realmente. Las decisiones técnicas reversibles se ratifican en su hito y los costos antes de contraerlos.

### Qué bloquea H1 y qué puede resolverse después

| Decisión/evidencia | Puerta real | Situación y efecto |
|---|---|---|
| Confirmación de cierre H0 y permiso H1 | Antes de H1 | **Resuelto el 25/10/2026:** cierre H0 y permiso H1 ficticio otorgados en decisiones separadas por el propietario. |
| Modelo, V1 propuesta, responsables y garantías mínimas | Cierre H0 según adenda | Modelo/reparto registrados; propietario debe aceptar documentación/pendientes. PSE y AF-04 no se declaran resueltos ni bloquean por sí solos POC ficticia. |
| Identidad jurídica del emisor, RUC, series, certificados y credenciales reales | Antes de utilizarlos/activar emisión real; representante autorizado | **No son prerrequisitos técnicos de H1 ficticio.** Confirmar modalidad y exclusividad de series antes de emisión real, sin pedir secretos para esta revisión. |
| Dictamen PSE y habilitación/contratación aplicables | Antes de prestar actividades reguladas y de H8; resolver integración condicionada antes de comprometer su diseño | Instalación independiente/certificado del cliente no eximen automáticamente. Profesional competente revisa reparto de adenda; cualquier cambio/costo requiere aprobación. No exigir trámite a la POC ficticia. |
| Versiones, compatibilidad/licencia y frontend | H1 autorizado | Es precisamente evidencia a obtener en H1; no condicionarla a una prueba que todavía no se permite ejecutar. |
| Custodia concreta, autorización y transacción base/archivos | Antes de implementarlas en H2 y antes de manejar secretos reales | Compromisos de seguridad permanecen en H0; algoritmo/proveedor o infraestructura adicional requieren justificación cuando se elijan. |
| D-07 y AF-04: ruta de boletas/bajas y recuperación | Revisión antes de H4/H5; evidencia en H7 antes de activar flujo real/H8 | Pendientes declarados en H0 con contención conservadora. Si falta validación/procedimiento efectivo, ese flujo real queda bloqueado; no se finge aceptación ni se impide por ello la POC ficticia. |
| Motivos/códigos NC/ND y excepciones excluidas | Antes de H5; aprobar exclusiones comerciales en H0 | No exigir todos los catálogos/casos para instalar entorno. Una necesidad que cambie V1 requiere decisión del propietario. |
| Hosting definitivo, capacidad, presupuesto operativo, RPO/RTO y respaldo comercial | Antes de contratar/comprometer costos o desplegar; validar recuperación en H7, antes de H8 | No bloquean por sí solos H1 ficticio. No asignar cantidades ni contratar servicios preventivamente. |

La tabla aplica el ajuste de puerta de la adenda: el pendiente fiscal/operativo del cliente real no se confunde con el permiso de POC. **El 25/10/2026 se aprobó H0 y autorizó exclusivamente H1 ficticio local**, sin servicios SUNAT/Beta ni datos, certificados o VPS reales. H2 permanece sin autorización.

## Puerta de salida H0

- PRD de V1 propuesta aceptado por el propietario, con exclusiones explícitas y ausencia de perfil concreto del primer emisor declarada. La validación profesional de aplicación al cliente real tiene puerta previa a su uso/H8; no se afirma ya realizada.
- D-01…D-08 registradas al nivel necesario para arrancar, con estado preliminar/final y pendientes asignados a la puerta real de la tabla anterior; sin exigir RUC, secretos, servicios, presupuesto o detalle de H5/H7 para H1 ficticio.
- Matriz con fuentes, reglas/límites y pendientes PSE/elegibilidad/AF-04 asignados a responsable y puerta de activación real; política conservadora definida, sin presentar esos pendientes como resueltos.
- Flujos esenciales, backlog priorizado, responsables y evidencia esperada revisados.
- Riesgos con tratamiento y compromisos de custodia/retención reconocidos; los costos concretos se aprueban antes de contraerlos, con justificación.
- Registro de cierre con versión y fecha. **La autorización para H1 es una decisión separada y explícita.**

Estado actual: **H0 v0.3 cerrado; H1 ficticio local autorizado; H2 no autorizado.** Las pruebas técnicas se registran en H1; las evidencias históricas de H0 no se convierten en pruebas ejecutadas ni en aceptación fiscal.
