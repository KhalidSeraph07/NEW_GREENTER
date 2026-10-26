# PLAN MAESTRO DE DESARROLLO — Emisor y gestor de comprobantes electrónicos (Perú)

**Estado:** propuesta de planificación para revisión; NO es autorización para implementar todo de una vez.  
**Fecha de referencia:** 24 de octubre de 2026.  
**Propósito:** proporcionar al orquestador, implementadores y revisores una dirección completa desde el análisis hasta la operación comercial, evitando sobreingeniería y funcionalidades sin comprobar.

## 0. Instrucción principal para los agentes

1. Leer el `AGENTS.md` de la raíz antes de toda tarea y cumplirlo. `AGENTS.md` es permanente; `STACK_INIT.md` es solo para la inicialización única de un repositorio nuevo, nunca para reinicializar uno existente.
2. Este plan **define el destino y las puertas de calidad**. No ordena escribir todos los módulos de una vez. Antes de cada tarea, consultar los documentos aprobados y verificar el estado real del repositorio.
3. No presentar supuestos, pruebas simuladas, mocks ni éxitos de CI como si fueran aceptación real de SUNAT. No afirmar que un documento fue aceptado sin la evidencia requerida.
4. Preferir la solución más sencilla comprobable. No crear microservicios, patrones, capas, políticas, tablas o abstracciones «por si acaso». No imponer límites artificiales de líneas ni comentarios redundantes.
5. Toda propuesta que cambie alcance, seguridad, contratos fiscales, esquema de datos o arquitectura exige decisión registrada del orquestador y aprobación humana cuando afecte requisitos, costos o riesgo legal.
6. Nunca utilizar claves SOL reales, certificados privados ni datos fiscales reales en pruebas sin autorización y controles adecuados. No exponerlos en commits, trazas, capturas, reportes ni prompts.
7. El proceso no termina con código «completo»: termina con resultados verificables, operación controlada y documentación suficiente para mantener y recuperar el sistema.

## 1. Visión y límites del producto

**Producto:** aplicación independiente para que empresas elegibles para el SEE del contribuyente configuren su identidad fiscal y puedan preparar, emitir, consultar, entregar y gestionar comprobantes electrónicos. No depende de YaradaFact ni de un ERP cliente.

**Objetivo V1 recomendado:** operaciones nacionales comunes de factura, boleta, notas asociadas, sus procedimientos de comunicación SUNAT según corresponda, historial, archivos y gestión de incidencias; primero un piloto con un emisor autorizado. El objetivo comercial posterior es multiempresa.

**Fuera de V1 salvo decisión expresa:** ERP completo, contabilidad y declaraciones, inventario avanzado, ventas omnicanal, punto de venta complejo, suscripciones SaaS, GRE 2.0, exportación avanzada, detracciones complejas, recibos por honorarios y promesa de soportar automáticamente todos los regímenes. Estas funciones requieren evaluación tributaria individual.

**Principios no negociables:** trazabilidad documental, aislamiento de empresas, seguridad de certificados y credenciales, identidad fiscal única por documento, evidencia inmutable, recuperación ante caídas, experiencia de usuario comprensible y capacidad de soporte.

### Decisiones que deben cerrarse antes de diseñar en detalle

| ID | Decisión | Recomendación inicial | Quién aprueba |
|---|---|---|---|
| D-01 | Modelo comercial | Arquitectura preparada para multiempresa; piloto con una empresa real, sin módulo de cobros SaaS en V1 | Propietario |
| D-02 | Operaciones V1 | Factura, boleta y notas nacionales; definir casuística exacta por matriz | Propietario + revisión fiscal |
| D-03 | Modalidad de emisión | SEE del contribuyente; verificar condición de emisor y requisitos antes de pruebas reales | Revisión fiscal |
| D-04 | Tecnología | Laravel + PHP compatible/parcheado + Greenter + PostgreSQL + Docker Compose; Vue/Inertia candidato, no impuesto | Orquestador |
| D-05 | Colas | Persistencia durable en PostgreSQL; Redis y workers solo cuando el flujo asíncrono esté implementado | Orquestador |
| D-06 | Provisión y custodia de secretos | Cifrado autenticado, aislamiento, rotación y procedimientos de recuperación | Revisión de seguridad |
| D-07 | Alcance de boletas y resúmenes | Confirmar procedimiento actual oficial, plazos, tickets y variantes antes de codificar | Revisión fiscal |
| D-08 | Almacenamiento/retención y acceso de cliente | Confirmar obligación aplicable, descarga segura, respaldos y costos | Propietario + revisión fiscal |

**Si una decisión sigue abierta:** documentar las opciones, el riesgo y la fecha de resolución. Se puede avanzar en trabajo independiente, pero no diseñar sobre una suposición no autorizada.

## 2. Paquete documental de planificación (referencia de los agentes)

Preparar los seis documentos del video más una matriz fiscal específica. Mantenerlos breves, conectados, versionados y sin duplicar texto innecesario.

1. `docs/01_PRD.md` — usuarios, necesidades, alcance V1, exclusiones, casos de uso y aceptación comercial.
2. `docs/02_APP_FLOW.md` — flujos felices y excepcionales: borrador, emisión, envío, incertidumbre, respuesta, resúmenes, notas, bajas y descargas; diagrama de estados.
3. `docs/03_TRD_ARQUITECTURA.md` — elección tecnológica con razones, fronteras modulares, esquema de despliegue, seguridad y decisiones pendientes.
4. `docs/04_BACKEND_SCHEMA.md` — entidades, claves, restricciones, transacciones, aislamiento multiempresa, contratos de API y migraciones.
5. `docs/05_UI_UX.md` — mapa de navegación, pantallas, formularios, mensajes de estado, accesibilidad y entorno fiscal visible sin etiquetas invasivas.
6. `docs/06_IMPLEMENTATION_PLAN.md` — hitos, tareas, dependencias, responsables, pruebas, criterios de terminado, riesgos y entregables por PR.
7. `docs/MATRIZ_FISCAL.md` — fuente oficial, vigencia, documentos, operaciones, reglas, escenarios válidos/inválidos y evidencia de validación.

**Regla:** las fuentes regulatorias deben citarse dentro de `MATRIZ_FISCAL.md` con fecha de consulta. Una norma no confirmada no se convierte en validación automática. Resolver discrepancias de fuentes con una revisión humana competente.

## 3. Arquitectura inicial: suficiente, no definitiva

- **Aplicación:** monolito modular Laravel, con responsabilidades delimitadas: identidad/acceso; empresas/configuración fiscal; emisión/documentos; transporte SUNAT; evidencias/archivos; seguimiento/auditoría.
- **Frontend:** Vue 3 + Inertia como propuesta, validando necesidades reales antes de confirmar. Interfaz clara y no invasiva; Beta o Producción del sistema NO equivalen necesariamente a Beta o Producción de SUNAT. Identificar inequívocamente el ambiente fiscal donde importa.
- **Base:** PostgreSQL con transacciones, restricciones únicas e integridad; considerar `tenant_id` desde el inicio si se aprueba multiempresa.
- **Motor fiscal:** Greenter detrás de un adaptador pequeño, sin replicar todo UBL/SOAP; la lógica tributaria y los estados pertenecen al dominio de la aplicación. Verificar versión, dependencias, licencia y compatibilidad en una prueba mínima.
- **Procesamiento:** registro de trabajo pendiente *durable* en PostgreSQL (*transactional outbox*); Redis/worker para ejecutar envíos en segundo plano cuando sea necesario. Redis no debe ser el único lugar donde exista un envío pendiente.
- **Archivos:** XML, firma, ZIP, CDR, PDF y evidencias de comunicación en almacenamiento privado, con integridad, política de retención y restauración.
- **Infraestructura:** Docker Compose inicialmente; servicios y puertos mínimos. No introducir Kubernetes ni microservicios sin una necesidad medida.
- **Seguridad:** certificados y secretos cifrados, claves de cifrado fuera de la base, acceso por empresa y rol, HTTPS, no logs sensibles, cargas de archivos restringidas, respaldos probados.
- **Dependencias:** seleccionar versiones estables, parcheadas y compatibles al instalar; fijar versiones efectivas y archivos `lock` para despliegues reproducibles; repetir revisión de avisos de seguridad antes de publicar.

## 4. Modelo de estados y reglas críticas (diseño obligatorio)

No confundir `documento`, `envío` y `respuesta SUNAT`. Ejemplo de modelo conceptual (ajustar con la matriz fiscal):

- **Documento:** `BORRADOR` → `PREPARADO` (identidad + correlativo + XML firmado + huella inmutables).
- **Transporte:** `PENDIENTE` → `ENVIANDO` → `RESPUESTA_CONFIRMADA` / `INCIERTO` / `ERROR_RECUPERABLE`.
- **Resultado fiscal:** `SIN_CONFIRMAR`, `ACEPTADO`, `ACEPTADO_CON_OBSERVACIONES`, `RECHAZADO`, según la evidencia aplicable; algunos documentos requieren ticket/resumen u otro procedimiento.

Invariantes:

1. Un pedido repetido de emisión no crea comprobantes adicionales accidentalmente (idempotencia).
2. Una combinación fiscal empresa/tipo/serie/número no se duplica; numeración protegida por transacción y restricción única.
3. Un timeout no significa «rechazado» ni «no recibido». Estado incierto exige consulta/recuperación controlada según el canal oficial aplicable.
4. Reintentar **transporte** no significa volver a **emitir** ni regenerar identidad, XML o ZIP.
5. Un comprobante preparado no se edita silenciosamente. Las correcciones siguen el procedimiento tributario aplicable.
6. Cada intento conserva timestamp, identificador, resultado, error saneado, correlación y evidencia sin secretos.
7. Los cambios de certificado, régimen o catálogos no alteran documentos históricos.
8. Si PostgreSQL o los archivos durables no están disponibles, bloquear nuevas emisiones que no pueden persistirse de manera segura.
9. Mostrar al usuario el estado real; nunca «aceptado» solo porque terminó el envío HTTP o porque un test pasó.
10. El acceso a documentos/descargas comprueba empresa, usuario y permisos **en el servidor**.

## 5. Desarrollo en hitos con puertas de calidad

### H0 — Confirmar producto y riesgos (sin programar)

**Trabajos:** recopilar y validar alcance V1, actores, decisiones D-01…D-08, fuentes oficiales, matriz tributaria y escenarios críticos. Preparar documentación inicial de los siete documentos con el nivel necesario para arrancar.

**Salida:** PRD V1 firmado, matriz fiscal revisada, flujos esenciales y backlog priorizado. **No avanzar** si se desconoce cómo se emite o recupera un documento central de V1.

### H1 — Prueba de compatibilidad técnica e infraestructura mínima

**Trabajos:** examinar repositorio; ejecutar `STACK_INIT.md` solo si corresponde y una única vez; Docker Compose + PostgreSQL; pequeña prueba PHP/Laravel/Greenter con versiones parcheadas y XML/firma usando datos ficticios.

**Salida:** instalación reproducible, prueba de librería y firma verificable, CI mínimo y decisión de frontend ratificada o aplazada justificadamente. No desplegar infraestructura extra.

### H2 — Base segura y primera operación de extremo a extremo

**Trabajos:** autenticación, empresa/permisos mínimos, configuración fiscal, almacenamiento privado, un formulario mínimo de factura nacional, validación, numeración atómica, XML firmado, ZIP, outbox, envío de prueba y registro del resultado.

**Salida:** demostración de **una factura completa**, con historial y evidencia verificable; marcar explícitamente lo probado con mocks y lo verificado ante servicios oficiales. No confundir recepción de HTTP con aceptación fiscal.

### H3 — Robustez fiscal de factura

**Trabajos:** estados inciertos, consulta/recuperación, idempotencia, reintentos limitados, plazos/alertas, errores SUNAT, conservación de CDR y notificaciones. Pruebas de concurrencia y caída de procesos.

**Salida:** la misma factura puede atravesar fallos sin duplicación, pérdida de identidad ni estados falsos. Evidencia de pruebas negativas y recuperación.

### H4 — Boletas y flujo asociado

**Trabajos:** reglas validadas de boleta, entrega/representación, resúmenes diarios según modalidad y vigencia aplicable, tickets y consultas; escenarios de fallo.

**Salida:** boleta y comunicaciones asociadas completas, probadas y trazables.

### H5 — Notas y correcciones documentales

**Trabajos:** notas de crédito/débito del alcance, relaciones y referencias, validaciones, tipos de afectación/corrección, comunicaciones de baja cuando procedan y sus estados.

**Salida:** casos positivos/negativos verificados; operaciones no elegibles se rechazan claramente.

### H6 — Producto utilizable e independiente

**Trabajos:** configuración fiscal asistida, usuarios y permisos, formularios finales, búsqueda, filtros, descargas seguras, cliente, panel de incidencias, mensajes de error comprensibles, accesibilidad y limpieza visual.

**Salida:** una empresa puede operar los flujos V1 desde la interfaz sin intervención del desarrollador. La interfaz se habrá refinado durante hitos previos; este hito la completa, no la crea toda desde cero.

### H7 — Seguridad, fiabilidad y despliegue

**Trabajos:** pruebas E2E, validaciones de autorización horizontal, secretos, rate limiting, respaldo/restauración, caída de Redis/DB/red, vulnerabilidades, observabilidad, manual de incidentes, migraciones seguras, prueba de recuperación, documentación de operación y rollback.

**Salida:** reporte de aceptación, sin defectos críticos abiertos, restauración demostrada, estrategia de actualización y despliegue con revisión.

### H8 — Piloto real controlado y liberación V1

**Trabajos:** emisor real autorizado y configuración válida (con consentimiento), despliegue con acceso restringido, operación supervisada, validación de evidencias fiscales, manejo de incidentes y correcciones limitadas.

**Salida:** aceptación de piloto documentada y decisión humana explícita de producción comercial. Si falla una regla fiscal o de integridad, no se libera y se corrige el alcance afectado.

**Nota:** estos hitos son puertas de salida, no estimaciones de horas ni garantía de duración. Se pueden realizar tareas paralelas únicamente si están desacopladas y las pruebas no interfieren.

## 6. Matriz mínima de incidentes que deben simularse

| Incidente | Resultado exigido |
|---|---|
| Dos solicitudes simultáneas con misma operación | Una emisión lógica; no dos documentos no previstos |
| Dos emisores de la misma serie al mismo tiempo | Correlativos únicos y transacciones consistentes |
| Se cae la conexión tras recibir SUNAT | `INCIERTO`; consulta o recuperación segura, sin nueva identidad |
| SUNAT devuelve fault/error de negocio | Motivo y evidencia; no reintento ciego en bucle |
| El worker muere después del envío | Recuperación de trabajo e intento conciliable |
| Redis no responde | Trabajo durable recuperable al restablecer servicio |
| PostgreSQL deja de responder | Emisión nueva bloqueada de forma segura; alerta |
| Certificado vencido, revocado o erróneo | Firma nueva bloqueada y diagnóstico sin secretos |
| Clave SOL incorrecta o cambiada | Pausa/alerta del envío afectado; actualización controlada |
| Cambio fiscal o de certificado | No altera XML, huellas ni evidencias históricos |
| Usuario de empresa A pide comprobante de B | Acceso denegado en backend, incluso con ID conocido |
| Backup restaurado | Base + archivos + configuración protegida coherentes |
| Rechazo, aceptación u observaciones | Estado mostrado coincide con evidencia, no con apariencia |
| Caducidad de plazo o ticket sin respuesta | Alertas y procedimiento definido según normativa actual |
| Despliegue defectuoso | Rollback de aplicación compatible con migraciones; sin pérdida documental |

## 7. Contrato operativo para los agentes

**Orquestador/planificador:**
- Mantiene las decisiones D-01…D-08 y los documentos; divide cada hito en tareas pequeñas con dependencias.
- Cada tarea indica objetivo, alcance/exclusiones, archivos/módulos afectados, escenarios y pruebas, definición de terminado y evidencia esperada.
- No añade funcionalidades fuera de V1 sin decisión del propietario; autoriza tareas paralelas solo si no se pisan.
- Verifica código/diff/test/evidencia de manera independiente del reporte del implementador.

**Implementador:**
- Antes de tocar código lee `AGENTS.md`, tarea aprobada y componentes relevantes del repositorio.
- Entrega un cambio acotado con pruebas relevantes; no cambia contratos o base de datos por iniciativa propia.
- No elimina protección, tests, logs útiles ni archivos ajenos para hacer «pasar» la tarea.
- Reporta qué ejecutó, qué no pudo verificar, riesgos y evidencia. Un test verde no sustituye una aceptación fiscal real.

**Revisor técnico/QA:**
- Comprueba requisitos, diff y pruebas; incluye casos negativos, seguridad, concurrencia y recuperación en operaciones críticas.
- Rechaza complejidad gratuita y defectos reproducibles, pero no exige refactorizaciones estéticas ni comentarios evidentes.
- La aprobación del código no sustituye la decisión legal/fiscal o de despliegue del responsable.

**Propietario del proyecto:**
- Decide alcance, modelo comercial, costo, acceso real a SUNAT, manejo de secretos y aceptación del piloto.
- No necesita escribir código ni producir decenas de prompts: responde solo decisiones bloqueantes y revisa demostraciones/evidencias de cada hito.

## 8. Definición de terminado (para cada tarea y para V1)

**Tarea terminada si:** objetivo probado; cambios acotados; pruebas pertinentes corridas; seguridad proporcional revisada; no regresión conocida; evidencia y riesgos residuales comunicados; revisión aprobada.

**V1 terminada si, además:** una empresa autorizada puede configurar su emisión, emitir los documentos de alcance, consultar evidencia, gestionar incidentes y obtener archivos; la comunicación SUNAT ha sido comprobada mediante el procedimiento oficial aplicable; hay pruebas de concurrencia, idempotencia, aislamiento, seguridad, recuperación de fallos y restauración; existe procedimiento de soporte, monitoreo y despliegue; el propietario aprueba comercializar.

**No son evidencia suficiente:** cantidad de líneas, número de pruebas sin analizar, mocks, «HTTP 200», la existencia de un ZIP, capturas bonitas, un reporte del agente sin revisar o meses de esfuerzo acumulado.

## 9. Primera orden concreta al orquestador (copiable)

> Asume el rol de orquestador de la nueva aplicación independiente de emisión y gestión de comprobantes electrónicos del SEE del contribuyente en Perú. Lee `AGENTS.md`, `STACK_INIT.md` (solo como referencia de inicialización única) y `PLAN_MAESTRO_AGENTES_EMISOR_SUNAT.md`. **No inicialices ni programes todavía.** Tu primera entrega es la planificación H0: propón los documentos `01_PRD.md`, `02_APP_FLOW.md` y `MATRIZ_FISCAL.md` en versión inicial breve, identificando para cada requisito una fuente verificable, supuestos y preguntas bloqueantes. Resume las decisiones D-01…D-08 con recomendación, alternativas y riesgos. Separa obligaciones confirmadas de hipótesis y de funcionalidades futuras. No prometas cobertura de todos los regímenes. Entrega un listado mínimo de preguntas que verdaderamente requieren decisión humana y los criterios de salida de H0. Espera aprobación antes de pasar a H1.

## 10. Fuentes de referencia iniciales (revalidar al ejecutar)

- SUNAT, SEE del contribuyente: https://cpe.sunat.gob.pe/sistema_emision/see_contribuyente
- SUNAT, boleta electrónica: https://cpe.sunat.gob.pe/tipos_de_comprobantes/boleta
- SUNAT, nota de crédito: https://cpe.sunat.gob.pe/tipos_de_comprobantes/nota_de_credito
- SUNAT, nota de débito: https://cpe.sunat.gob.pe/tipos_de_comprobantes/nota_de_debito
- Greenter, proyecto oficial: https://github.com/thegreenter/greenter
- Greenter, paquete PHP: https://packagist.org/packages/greenter/greenter

**Advertencia:** estas referencias inician la investigación; no sustituyen revisión de resoluciones SUNAT, catálogos, guías técnicas y requisitos vigentes por operación al momento de implementar.
