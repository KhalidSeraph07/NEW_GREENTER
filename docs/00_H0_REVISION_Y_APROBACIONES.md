# H0 — Revisión, decisiones y aprobaciones

**Versión 0.3 · 24 de octubre de 2026 · America/Lima**

**Cierre formal: 25/10/2026 · America/Lima.** El propietario aprobó expresamente **H0 v0.3** (Decisión 1 en este chat): PRD V1, exclusiones, instalación independiente por empresa, VPS administrado por nosotros y pendientes con responsables y puertas. En decisión independiente autorizó **H1 exclusivamente como POC técnica ficticia local** (Decisión 2), sin datos/certificados reales, servicios SUNAT ni VPS reales. **H2 no está autorizado.** El cierre no resuelve PSE, elegibilidad tributaria, recuperación sin ticket ni requisitos de producción. La evidencia de revisión siguiente describe lo entregado el 24/10; el acta final registra la aprobación posterior.

Informe breve: una instalación y un VPS independientes por cliente, administrados por el proveedor, con un RUC inicial y datos/secretos separados; proveedor como custodio técnico y operador, cliente como responsable de confirmar operaciones y decisiones tributarias. PSE, elegibilidad y recuperación efectiva sin ticket permanecen pendientes con puertas antes de actividad/flujo real, sin bloquear por sí solos una POC ficticia autorizada. Se preservó la evidencia fiscal v0.2 y no se añadieron servicios, precios, capacidad ni funcionalidades ajenas a la adenda.

| Diferencia v0.2 → v0.3 | Documentos afectados |
|---|---|
| Modelo independiente; sin tenants globales, BD compartida ni selector SaaS | PRD, TRD, Backend Schema y UI/UX |
| Custodia técnica, respaldo cifrado separado del VPS, administración y salida por cliente | PRD, TRD, flujos y plan |
| POC ficticia separada de validación/prestación real; pendiente PSE/AF-04 con responsable y puerta | Matriz fiscal, flujos y plan |
| Estados D-01…D-08 y aprobación H0/H1 independientes | Este control |

Las correcciones se limitan a secciones afectadas de los siete documentos existentes. La adenda se conserva como fuente íntegra en DOCS_GREENTER; no se creó otro informe de planificación.

## Base y evidencia local

Se leyeron íntegramente las 211 líneas del [Plan Maestro](../DOCS_GREENTER/PLAN_MAESTRO_AGENTES_EMISOR_SUNAT.md), el [AGENTS existente](../DOCS_GREENTER/AGENTS.md) y la [guía de agentes](../DOCS_GREENTER/GUIA_AGENTES.md), junto con las reglas Graphify proporcionadas por el usuario. Se revisaron objetivos, exclusiones, D-01…D-08, arquitectura propuesta, estados, hitos H0–H8, incidentes, funciones y criterios de terminado. La instrucción actual del propietario limita la ejecución a H0 y reserva STACK_INIT para la inicialización autorizada.

Inventario de partida: cuatro documentos en DOCS_GREENTER; sin aplicación, `.git`, `docs/` ni `graphify-out/graph.json` en la raíz; tampoco grafo dentro de DOCS_GREENTER. No se generó un grafo ni se instaló Graphify. Al no existir grafo, no era posible consultar uno; al no modificar código, no corresponde `graphify update .` en esta entrega.

AGENTS está en DOCS_GREENTER y no en la raíz que exige el Plan. Se respeta como normativa permanente en esta tarea; no se movió ni reescribió. Su ubicación debe normalizarse sin perder estas reglas ni las Graphify al preparar la raíz del proyecto, cuando se autorice esa intervención. No se utilizó ni abrió el contenido de STACK_INIT, ni se ejecutó inicialización.

La adenda de Downloads se copió sin cambios a DOCS_GREENTER. SHA-256 de origen y copia: `0fdcc1bd3e45d2b54cfc508a7befc4000b1de5a4d315ea8a2dfe6a4c648012e7`. Es decisión del propietario para producto/operación, no fuente fiscal ni prueba de elegibilidad. Su incorporación confirmada ajusta la puerta H0 del Plan: se admite documentar los pendientes del cliente real con responsable y bloqueo de activación/prestación, conservando aprobación expresa de H0 y permiso separado H1.

## Entregables de H0

| Entregable | Contenido de esta versión | Estado |
|---|---|---|
| [01_PRD.md](01_PRD.md) | Problema, actores, RP-01…RP-14 con fuente, V1/exclusiones y aceptación | V1 propuesta/exclusiones aceptadas el 25/10/2026; perfil real pendiente. |
| [02_APP_FLOW.md](02_APP_FLOW.md) | Dimensiones de estado, flujo esencial, excepciones, recuperación, notas/bajas y descarga | Conceptual; incertidumbre sin ticket abierta. |
| [03_TRD_ARQUITECTURA.md](03_TRD_ARQUITECTURA.md) | Dirección proporcional, fronteras, despliegue, seguridad y prueba futura | Propuesta, sin versiones instaladas. |
| [04_BACKEND_SCHEMA.md](04_BACKEND_SCHEMA.md) | Información, relaciones, unicidad, transacciones y contratos lógicos | Conceptual; sin DDL/API implementada. |
| [05_UI_UX.md](05_UI_UX.md) | Navegación, formularios, estados, ambiente y accesibilidad | Propuesta sin frontend. |
| [06_IMPLEMENTATION_PLAN.md](06_IMPLEMENTATION_PLAN.md) | Backlog H0, dependencias, responsables, hitos y puertas de calidad | H0 cerrado; H1 ficticio autorizado por separado el 25/10/2026. |
| [MATRIZ_FISCAL.md](MATRIZ_FISCAL.md) | 23 referencias, análisis PSE, celdas/huella del libro 2026, MF-01…MF-20, AF y CF | Archivo revisado en v0.2; dictamen fiscal pendiente. |

El Plan §2 y H0 piden los siete documentos; su primera orden §9 destaca tres. Se entregan los siete al nivel inicial que pide H0, sin adelantar el diseño detallado o implementación de H1.

## D-01…D-08 — Estado, responsable y hito límite

La adenda confirmada reemplaza la dirección SaaS compartida. Se mantiene General/RMT, PEN, IGV común y contado como perfil recomendado, sin RUC/series/casuística real confirmados. **Aprobada** significa decisión documental de principio, no control implementado; **condicional** depende de evidencia indicada; **pendiente** no tiene elección definitiva. Ningún estado de esta tabla equivale a aprobación global de H0 o autorización de H1.

| ID | Estado | Decisión / reserva | Responsable | Hito límite y evidencia |
|---|---|---|---|---|
| D-01 | **Aprobada** | Instalación y VPS independiente por cliente, administrado por el proveedor; un RUC inicial; sin tenants globales, cobros SaaS ni licenciamiento complejo | Propietario; orquestador registra | Registrada en H0 por adenda confirmada. Titular/pago del VPS y salida acordados antes de H8. No implica exención PSE. |
| D-02 | **Condicional** | V1 propuesta: factura/boleta/NC/ND nacionales; General/RMT, PEN, IGV común y contado. [Preparación H5, 26/10/2026](12_H5_PREPARACION_FISCAL.md): **NC 01 y ND 02 y exclusiones aprobados por el propietario**. Cliente real sin confirmar | Propietario + profesional fiscal; representante acredita datos | H0 aceptó propuesta/exclusiones generales. [H5-A cerrado con observaciones y PR #3 fusionado por aprobación expresa](13_H5A_NOTAS_FACTURA_FICTICIAS.md), solo factura ficticia; H5 incompleto; [H5-B cerrado con observaciones y PR #4 fusionado por aprobación expresa](14_H5B_NOTAS_BOLETA_FICTICIAS.md); H6 no autorizado. Ratificar caso, perfil y series antes de actividad real/H8; entrega/custodia mantienen puertas H6/H7. Sin ampliar por suposición. |
| D-03 | **Condicional** | SEE del contribuyente y certificado/credenciales del cliente sujetos a elegibilidad y dictamen sobre la prestación administrada | Profesional competente + representante; propietario aprueba ajustes | Antes de actividad regulada/real y H8; resolver integración condicionada antes de comprometer su diseño. POC H1 solo ficticia. |
| D-04 | **Condicional** | Monolito modular del Plan; Compose/PostgreSQL por cliente aprobados como base; versiones/frontend sin ratificar | Orquestador/revisor técnico; propietario para costos/cambios | H1 autorizado demuestra compatibilidad, licencia/versiones y firma ficticia; frontend ratificado o aplazado. VPS/proveedor/capacidad/precio antes de contratación/H8. |
| D-05 | **Aprobada** | Outbox durable en PostgreSQL; trabajadores cuando hagan falta; sin Redis u otras colas por defecto | Orquestador + revisor técnico | Implementar/probar persistencia y recuperación en H2/H3; una garantía de diseño no es prueba ejecutada. |
| D-06 | **Aprobada** | Proveedor custodio técnico con acceso privilegiado no rutinario, autorizado/auditado; cliente autoriza/revoca. Cifrado autenticado y claves separadas | Proveedor/seguridad + representante | Modelo registrado H0; controles antes de implementarlos/manejar secretos reales, prueba completa H7 antes de H8. Dictamen D-03 puede exigir ajuste. |
| D-07 | **Aprobada para diseño V1; aplicación real condicionada** | [Decisión breve y fuentes revalidadas](10_D07_BOLETAS.md): ruta única RC para SEE del contribuyente previsto; individual reconocido como alternativa no implementada; recuperación sin ticket no confirmada | Fiscal + orquestador/soporte + representante; propietario aprueba alcance | [H4 ficticio cerrado con observaciones y PR #2 fusionado por aprobación expresa](11_H4_VERTICAL_FICTICIA.md). Mantener AF-04 y demás puertas antes de actividad real. Corrección de RC rechazados requiere etapa expresamente autorizada antes de habilitar ese flujo real. H5 no autorizado. |
| D-08 | **Aprobada** | Proveedor: archivos privados, consulta segura, backup cifrado separado, restauración/exportación/revocación. Cliente: titular documental y obligación legal | Proveedor/operación + representante/profesional fiscal; propietario para acuerdos/costos | Diseño mínimo de archivos en H2 y acceso H4/H6; restauración/salida probadas H7; contrato, retención, soporte y RPO/RTO probados aceptados antes de H8. |

D-04/D-05 son decisiones técnicas delegadas al orquestador por el Plan. Sus propuestas se registran aquí; no se pide elegir versiones al propietario. Toda variante que altere alcance, costos, seguridad o riesgo legal requiere su aprobación conforme Plan §0.5.

## Ambigüedades y dependencias de mayor impacto

1. **Prestación administrada y PSE:** reparto operativo ya definido por la adenda; falta dictamen sobre esas actividades. Separación de VPS y certificado del cliente no acreditan exención. Sin actividad real/comercial hasta validación aplicable (MF-02).
2. **Casuística tributaria:** «nacional común» y «notas asociadas» no definen impuestos, motivos, condiciones de pago, moneda ni excepciones. El recorte del PRD es propuesta, no decisión tomada.
3. **Recuperación asíncrona:** contención y escalamiento en APP_FLOW; falta vía efectiva sin ticket. La adenda permite mantener el pendiente declarado en H0, pero bloquea activación del flujo real hasta validación. No se presenta contención como recuperación.
4. **Fuentes divergentes:** páginas con reglas SOL/contribuyente, plazos distintos para correcciones excepcionales y texto de crédito dentro de débito. No codificar por copiar orientación aislada.
5. **Paquete técnico:** archivo CPE de 26/08/2026 recuperado e inspeccionado. Tiene cambios aplazados a 2027; la fecha del archivo no prueba vigencia uniforme. Completar solo el subconjunto necesario antes de cada flujo.
6. **Series y coexistencia:** saber si ya emite con otro sistema, historial y exclusividad de series. El correlativo inicial no se presume 1; backups y varias instalaciones tampoco pueden duplicar autoridad de numeración.
7. **Operación/costos:** compromisos de seguridad/conservación permanecen; presupuesto, hosting y capacidad se fijarán con justificación antes de comprometerlos. No son todos bloqueos de H1. Puertas exactas en IMPLEMENTATION_PLAN.
8. **Gobierno documental:** PRD firmado, revisión fiscal y permiso H1 son actos distintos. La instrucción de orquestar no autoriza implementar, despachar agentes, abrir servicios ni utilizar secretos reales.

## Riesgos y sobreingeniería

Impacto y probabilidad son valoraciones cualitativas del análisis, no mediciones. Las probabilidades no estimables quedan indicadas.

| ID | Riesgo / probabilidad estimada | Impacto | Mitigación proporcional / responsable / puerta |
|---|---|---|---|
| R-01 | Operación administrada con encuadre regulatorio pendiente / probabilidad no cuantificada | Alto legal y de retrabajo | Dictamen sobre actividades definidas en adenda; no eximir por VPS/certificados separados; bloqueo previo a prestación real/H8. Profesional fiscal + representante. |
| R-02 | Reglas antiguas o de otra modalidad / alta por discrepancias halladas | Alto fiscal | Matriz con ámbito y fuentes; resolver AF y validar antes de implementar; fiscal. |
| R-03 | Resumen recibido sin ticket local / posible, frecuencia desconocida | Alto integridad/plazo | Política de incidente documentada y validación de recuperación antes de activar flujo real; proveedor/soporte + representante/fiscal, H4/H5–H7. |
| R-04 | Duplicación de identidad por concurrencia, restauración o coexistencia / posible | Alto | Unicidad, idempotencia, autoridad de series y reconciliación; H2/H3/H7. |
| R-05 | Robo de claves o error administrativo entre instalaciones/receptores / posible | Alto seguridad/legal | Custodia técnica autorizada/auditada, separación por cliente, permisos servidor y pruebas negativas; proveedor/seguridad H2/H7. |
| R-06 | Pérdida de documentos o borrado prematuro / posible | Alto fiscal/operativo | D-08, art. 87.7, respaldos coherentes y restauración; propietario + operación, H7. |
| R-07 | Cliente piloto necesita casuística omitida / no estimable sin perfil | Alto comercial | Confirmar casos sin secretos y ajustar alcance antes de emitir realmente; propietario + fiscal antes de H8. No considerar universal la V1 propuesta. |
| R-08 | Confundir Beta/HTTP/ticket con aceptación / posible | Alto | Evidencia por ambiente y objeto, UI honesta; H2–H8. |
| R-09 | Monolito convertido en plataforma genérica / prevenible | Medio costo/mantenimiento | Sin microservicios, Kubernetes, motor tributario universal, repositorios vacíos ni API pública sin consumidor; orquestador por cambio. |
| R-10 | Reintroducir tenants globales, consola central, cobros o catálogos extensos / prevenible | Medio costo/retrabajo | Aplicar una instalación/RUC inicial y usuarios locales; sin infraestructura compartida ni licenciamiento complejo; D-01/D-02. |
| R-11 | Infraestructura extra o compatibilidad presumida / posible | Medio/alto | H1 prueba pequeña con versiones mantenidas; Redis/KMS externo solo justificados; orquestador. |
| R-12 | Sobredocumentación y diseño definitivo sin decisiones / prevenible | Medio retrabajo | Siete documentos breves conectados y este control; detalle por hito, sin replicar normativa en cada archivo. |

## Decisiones que aún requieren al propietario

No enviar claves SOL, certificados privados, contraseñas ni archivos fiscales reales por el chat. Para definir alcance basta un perfil descriptivo; la verificación formal se registra por un canal adecuado después.

| ID | Pregunta/decisión requerida | Recomendación y efecto |
|---|---|---|
| Q-01 | **Resuelta el 25/10/2026: H0 v0.3 cerrado por aprobación expresa (Decisión 1).** | V1 propuesta, exclusiones y modelo aceptados con pendientes; sin dictamen, elegibilidad ni recuperación ya demostrados. |
| Q-02 | **Resuelta el 25/10/2026: H1 ficticio autorizado por separado (Decisión 2).** | Entorno local, compatibilidad, auditorías, XML/firma de prueba, verificación y CI mínimo; sin SUNAT/Beta, secretos reales, VPS ni módulos comerciales. H2 exige nueva autorización. |
| Q-03 | Antes del piloto real: aceptar condiciones del servicio y cualquier ajuste regulatorio/costo o ampliación de alcance que resulte necesario. | Acordar titular/pago VPS, datos, accesos, soporte/contactos, custodia/rotación, retención, backup/restauración, exportación/baja y RPO/RTO probados. D-07 se ratifica tras revisión fiscal. No se solicitan ahora contrataciones ni precios. |

Q-01 y Q-02 se resolvieron mediante las dos decisiones explícitas del propietario sobre el paquete revisado. Q-03 y las reservas regulatorias/operativas conservan sus puertas; la aprobación documental y la POC no las resuelven.

## Evidencia disponible y límites

- Lectura completa del Plan y normativa local aplicable; inventario de partida y revisión de integridad de originales.
- Investigación oficial y localizadores en MATRIZ_FISCAL. En v0.2 se corrigió el requisito de homologación con RS 108-2022 y se descargó/inspeccionó el libro CPE con celdas y huella. No se utilizaron blogs para establecer reglas.
- Revisión documental de trazabilidad, estados, backlog, decisiones y enlaces locales. Sin pruebas de software por no existir implementación autorizada.
- No inicialización, código, contenedores, migraciones, paquetes instalados, git init, CI, XML/firma ni llamadas a servicios de emisión/consulta fiscal. No se usaron credenciales ni certificados reales.
- No PR abierto, por ausencia de repositorio Git. V0.3 modifica solo secciones afectadas de los ocho Markdown existentes, incluida la eliminación conceptual del selector/tenants; añade la copia íntegra de la adenda como fuente. El XLSX oficial se conserva sin cambios.

Verificación v0.3 realizada: diferencias frente a v0.2 y consistencia del modelo/puertas revisadas; cero enlaces locales rotos y cero bloques cercados desbalanceados. Se mantienen **8 Markdown de planificación**, **14 RP**, **23 fuentes oficiales**, **20 MF**, **15 CF** y **8 decisiones D** con estado/responsable/hito. Las huellas de los cuatro originales y del XLSX coinciden con las previas; la adenda es copia fiel del origen. Sin render de diagramas, firma XML ni validador SUNAT; los chequeos documentales no acreditan dictamen ni aceptación fiscal.

## Acta de cierre — H0 v0.3 aprobado el 25/10/2026

| Elemento | Responsable | Estado actual |
|---|---|---|
| PRD de V1 propuesta y exclusiones | Propietario | Aceptados expresamente; instalación independiente y VPS administrado por nosotros. |
| D-01…D-08 al nivel de arranque | Responsables de la tabla | Aprobadas/condicionales/pendiente según tabla; no equivalen a aprobación global. |
| PSE, elegibilidad y recuperación sin ticket | Fiscal + representante + proveedor/orquestador | Pendientes declarados con puerta de activación/prestación real; no impiden por sí solos POC ficticia. |
| Flujos, backlog y riesgos | Propietario; responsables de validación conservados | Paquete aceptado con pendientes; no sustituye revisiones profesionales ni pruebas posteriores. |
| Fecha, versión y constancia de cierre H0 | Propietario | **25/10/2026, H0 v0.3, aprobación expresa en este chat (Decisión 1).** Sin atribuir firmas o dictámenes a terceros. |
| Autorización explícita de H1 | Propietario, tras cierre H0 | **Otorgada por separado (Decisión 2), solo POC ficticia local. H2 no autorizado.** |

H0 se cierra por aprobación expresa del propietario, preservando reservas y puertas del [plan de implementación](06_IMPLEMENTATION_PLAN.md). La evidencia técnica posterior corresponde a H1 y no acredita aceptación SUNAT ni permiso de H2.
