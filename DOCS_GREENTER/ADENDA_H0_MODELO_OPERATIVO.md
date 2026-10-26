# Adenda H0 — Instalación independiente y operación administrada

**Fecha:** 24/10/2026  
**Estado:** decisiones del propietario para incorporar al paquete H0; no constituye aprobación final de H0 ni autorización de H1.  
**Base revisada:** `docs.zip` (H0 v0.1) y el informe textual de H0 v0.2 del orquestador. La v0.2 completa no se ha inspeccionado aquí.

## 1. Decisión vinculante para D-01

- El producto se distribuye **por instalación independiente y por cliente**; no como SaaS multiempresa compartido.
- **Una instalación = un RUC emisor inicial**, con usuarios/roles propios, PostgreSQL, documentos, configuración y secretos separados. No crear subsistema global de tenants, cobros SaaS ni licenciamiento complejo en V1.
- Cada cliente usa un **VPS independiente administrado técnicamente por nosotros**. La titularidad contractual del VPS, los costos y las cláusulas de salida se concretarán antes del primer piloto real; el proveedor específico no se decide en H0.
- El producto utiliza **SEE del contribuyente** según elegibilidad confirmada, con certificado y credenciales autorizadas del propio cliente, sujeto a dictamen regulatorio sobre la prestación efectiva.
- El piloto técnico de H1 usará **datos y credenciales ficticios**; ninguna emisión real queda aprobada.

## 2. Matriz operativa de responsabilidades

| Función | Proveedor de software / operador del VPS (nosotros) | Empresa cliente / contador |
|---|---|---|
| VPS, SO, HTTPS, firewall, contenedores y BD | Instalar, endurecer, actualizar y monitorizar | Aprobar servicio, costos y accesos pactados |
| Alta de empresa y usuarios | Proveer controles y asistencia técnica | Designar usuarios, roles y responsables autorizados |
| RUC, régimen, series y operaciones | Validación técnica conforme a reglas aprobadas | Acreditar exactitud, autorizaciones y tratamiento fiscal |
| Certificado y SOL | Recibir/guardar cifrados, limitar accesos, rotar bajo autorización | Proveer/autorizar certificados y credenciales válidas, revocarlos si corresponde |
| Generación, firma, envío, CDR | Operar software y diagnosticar fallos técnicos con trazas saneadas | Confirmar operaciones y atender decisiones tributarias |
| SUNAT indisponible o respuesta incierta | Alertar, preservar evidencias, impedir reenvíos ciegos, escalar y recuperar cuando sea seguro | Participar en conciliación y decidir actuaciones tributarias |
| XML, ZIP, CDR, PDF, registros | Almacenamiento privado, integridad, respaldos y restauración | Titularidad de documentos y obligaciones legales de conservación |
| Consulta segura del receptor | Proveer mecanismo autenticado y disponible | Definir proceso de entrega/acceso y atender solicitudes |
| Parches, despliegues, reversión | Ejecutar procesos controlados, sin poner en riesgo la evidencia | Aprobar ventanas que afecten su operación |
| Soporte e incidentes | Diagnóstico, comunicación, escalamiento, recuperación | Reportar incidencias y asignar contacto responsable |
| Baja del servicio | Exportar datos/archivos y revocar accesos según contrato | Designar receptor de entrega y confirmar recepción |

**Custodia:** el proveedor administra infraestructura que puede contener secretos; no afirmar custodia exclusiva del cliente. El acceso a secretos no debe ser rutinario: cifrado autenticado, claves separadas de BD y archivos, autorización, permisos mínimos, logs sin secretos y registro de acciones administrativas. No crear criptografía propia.

## 3. Infraestructura operativa mínima

- Un Docker Compose por instalación; servicio web, base PostgreSQL con volumen persistente y trabajadores solo cuando el flujo de emisión lo requiera.
- HTTPS, puertos mínimos, cuentas técnicas de privilegio mínimo, acceso SSH restringido y autenticación fuerte para administración.
- PostgreSQL y archivos XML/ZIP/CDR/PDF en almacenamiento privado; nada fiscal expuesto como archivos estáticos públicos.
- Respaldo **cifrado y separado del VPS**, cubriendo BD + archivos + materiales necesarios de recuperación de claves, con procedimiento documentado de restauración. Un snapshot no sustituye automáticamente un respaldo verificado.
- Monitoreo y alertas mínimos: disponibilidad, espacio, vencimiento de certificados, fallos de backup, trabajos bloqueados, documentos próximos a vencer y errores de comunicación SUNAT.
- Actualizaciones con copias previas, migraciones controladas y posibilidad de reversión **sin perder ni reescribir comprobantes emitidos**.
- No añadir Kubernetes, consola central para todos los clientes, VPN corporativa compleja, HA multi-región o microservicios sin necesidad demostrada.

## 4. Condiciones comerciales y de operación que deben registrarse

Antes del piloto real, firmar o dejar aceptado: titular de VPS y de datos, quién paga infraestructura, alcance del soporte, horarios/contacto de incidencias, procedimiento de alta/baja, autorizaciones administrativas, custodia/rotación de secretos, política de retención, respaldo/restauración, exportación al terminar contrato y objetivos **realistas y probados** de recuperación (RPO/RTO). No prometer SLA ni tolerancia a fallos no demostrados.

## 5. Cambios puntuales a H0

| Decisión/documento | Cambio necesario | ¿Bloquea H1 ficticio? |
|---|---|---|
| **D-01 / PRD** | Reemplazar SaaS compartido por instalación aislada administrada por nosotros; un RUC inicial | No, una vez registrado |
| **D-03 / Matriz fiscal** | Verificar SEE y encuadre PSE por actividades reales; registrar dictamen y responsable | No para pruebas ficticias; sí para prestación/producción real |
| **D-04 / TRD** | Compose por cliente; arquitectura sin multitenencia global; versiones sujetas a H1 | No |
| **D-05 / TRD/Plan** | Outbox persistente, trabajadores cuando hagan falta, nada de colas extra decorativas | No |
| **D-06 / TRD/PRD** | Precisar proveedor como custodio técnico con acceso privilegiado; cifrado, acceso, revocación y recuperación | Definir el modelo en H0; demostrar controles antes de piloto real |
| **D-08 / PRD/Flujos/Plan** | Backups por cliente, consulta segura del receptor, conservación, salida/exportación y responsabilidades | Definir responsable y principio en H0; implementar antes de producción |
| **D-02 / PRD/Mat. fiscal** | Concretar el piloto fiscal (actividad, régimen, operaciones, NC/ND) sin inventarlo | No para POC técnica; sí para alcance comercial firmado |
| **D-07 / Mat. fiscal/Flujos** | Validar ruta de boletas y ticket perdido; conservar procedimientos manuales fail-closed | No para H1 ficticio; sí antes de liberar el flujo de boletas |
| **Backend Schema** | Revisar `tenant_id` y restricciones concebidas para BD compartida; no quitar unicidad por RUC/serie/documento ni auditoría | Solo si hay contradicción conceptual |
| **UI/UX** | Ajustar referencias a selector SaaS de empresas si existen; no rediseñar pantallas correctas | No |
| **Implementation Plan** | Añadir tarea de despliegue por cliente, restauración y retirada controlada; puertas H7/H8 | No |
| **00_H0_REVISION...** | Actualizar acta D-01, D-06, D-08, pendientes y responsables; no declarar H0 cerrado automáticamente | Sí: registrar decisiones y aceptación |

## 6. Frontera regulatoria pendiente

La instalación separada y el uso del certificado del contribuyente **no constituyen por sí solos una exención PSE**. Solicitar revisión competente con descripción de quién genera, firma, envía, recibe y custodia, además del grado de actuación del proveedor en nombre del emisor. No publicar ni operar emisión real comercial mientras falte la validación aplicable. Referencias: https://cpe.sunat.gob.pe/aliados/pse y https://www.sunat.gob.pe/legislacion/oficios/2019/informe-oficios/i161-2019-7T0000.pdf .

## 7. Cierre de H0 propuesto (sujeto a aprobación)

1. D-01 aprobada como modelo independiente administrado; actualización consistente en documentos afectados.
2. D-06 y D-08 establecen **responsables y garantías mínimas**, sin seleccionar ahora cada servicio o precio.
3. D-02 determina el alcance de la **V1 propuesta** y explicita la falta de perfil concreto del primer emisor; cualquier ampliación se decide antes del piloto real.
4. PSE, elegibilidad, series y casos tributarios del cliente real mantienen un **bloqueo a H8/producción** claramente asignado; no se finge aprobación legal ni técnica.
5. La recuperación sin ticket contiene un procedimiento conservador de incidente y una puerta específica antes de activar ese flujo en producción.
6. El orquestador entrega diff corto y tabla D-01 a D-08 (aprobada/condicional/pendiente con responsable y hito límite).
7. **H0 no se cierra ni H1 se ejecuta hasta aprobación explícita del propietario.**

## 8. Instrucción ejecutable para el orquestador

Aplicar únicamente los cambios anteriores al paquete documental H0 v0.2, sin reescrituras completas ni nuevas implementaciones. Separar las decisiones necesarias para una POC H1 con datos ficticios de las exigencias legales y operativas del piloto real. Presentar informe de diferencias y pendientes; solicitar autorización expresa para cerrar H0 y, en una decisión independiente, para iniciar H1.
