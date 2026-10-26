# PRD V1 — H0, versión 0.3

**24/10/2026 · Borrador para aprobación.** Fuente de producto: [Plan Maestro](../DOCS_GREENTER/PLAN_MAESTRO_AGENTES_EMISOR_SUNAT.md), §§1–5 y 8. Reglas fiscales y su nivel de certeza: [matriz fiscal](MATRIZ_FISCAL.md). Decisiones y firma: [control H0](00_H0_REVISION_Y_APROBACIONES.md). Este documento no está firmado ni autoriza H1.

## Problema y resultado esperado

Una empresa elegible necesita emitir y gestionar sus comprobantes desde una aplicación independiente, entender su situación real ante SUNAT y conservar la evidencia necesaria para corregir incidentes y recuperar la operación. La V1 debe poder usarse sin intervención habitual de un desarrollador. No depende de YaradaFact ni de un ERP cliente.

Decisión D-01 del propietario, incorporada tras su confirmación expresa de la [adenda operativa](../DOCS_GREENTER/ADENDA_H0_MODELO_OPERATIVO.md): **instalación independiente por cliente, un VPS administrado técnicamente por el proveedor y un RUC emisor inicial por instalación**. Usuarios, PostgreSQL, documentos, configuración y secretos separados. Sustituye la orientación anterior a SaaS multiempresa compartido; no se crea multitenencia global, cobros SaaS ni licenciamiento complejo en V1. No se ha contratado ni creado ningún VPS.

Perfil recomendado: Régimen General o RMT, emisión nacional en soles, operaciones comunes con IGV y contado. RUC, series, actividad y casuística del primer emisor siguen pendientes. La revisión tributaria corresponde a un profesional competente; el representante autorizado valida modalidad y credenciales del cliente. SEE del contribuyente y uso de certificado/credenciales autorizadas del cliente quedan **condicionados a elegibilidad y dictamen regulatorio sobre la prestación efectiva**; la separación de instalaciones no acredita exención PSE. H0 continúa en revisión; una futura POC H1 utilizará únicamente datos y credenciales ficticios.

## Actores y necesidades

| Actor | Necesidad / límite |
|---|---|
| Propietario del producto | Decide alcance, costos, responsables, piloto y liberación; aprueba cambios de riesgo. |
| Responsable de empresa | Mantiene configuración y autorizaciones; conoce series y obligaciones de su empresa. |
| Operador emisor | Prepara documentos, confirma emisión, consulta resultados y atiende errores dentro de sus permisos. |
| Responsable de soporte | Diagnostica incidentes con información mínima y acceso autorizado, trazable y acotado; no recibe acceso global por defecto. |
| Proveedor / administrador técnico | Administra VPS, despliegues, custodia técnica, respaldos y soporte por cliente; opera el software según la adenda, sujeto al encuadre regulatorio. El cliente confirma operaciones y decisiones tributarias. |
| Adquirente/receptor | Obtiene sus comprobantes mediante entrega y consulta reservada, sin acceder a otros clientes. |
| Revisor fiscal y revisor técnico | Validan reglas/evidencias y controles de su competencia; no son necesariamente roles de la aplicación. |

Los perfiles funcionales no obligan a crear seis roles configurables ni un motor de permisos. La asignación mínima se decidirá con el piloto.

## Requisitos trazables

**PM** significa requisito del Plan Maestro; **FISCAL** referencia una obligación documentada; **PROPUESTA** necesita aprobación. Los requisitos PM son la dirección del plan, no prueba de aprobación de toda la casuística.

| ID | Necesidad y comportamiento V1 | Fuente / estado | Aceptación observable |
|---|---|---|---|
| RP-01 | Aplicación autónoma con identidad y configuración fiscal de empresa | PM §§1, 3; MF-01–02 | Empresa autorizada configura lo necesario desde interfaz; activación real exige evidencias. |
| RP-02 | Factura nacional del alcance aprobado | PM §§1, H2–H3; MF-03–04, MF-15–18 | Caso acordado produce identidad única, archivos y resultado respaldado por evidencia del ambiente usado. |
| RP-03 | Boleta nacional y comunicación asociada | PM H4; MF-05–10 | Documento y resumen elegido se vinculan; el ticket y su CDR pueden seguirse sin estados ficticios. |
| RP-04 | NC y ND asociadas de motivos expresamente aprobados | PM H5; MF-13–16 | Motivo, documento previo, receptor e importes elegibles; rechaza lo no soportado. |
| RP-05 | Bajas cuando proceden y correcciones trazables | PM H5; MF-11–14 | Distingue baja de numeración no otorgada y modificación de operación; conserva original. |
| RP-06 | Consulta de historial, archivos y estado real | PM §§1, 4, H6 | Usuario autorizado encuentra el documento y evidencia; distingue falta de respuesta de rechazo. |
| RP-07 | Entrega y acceso del receptor | FISCAL MF-19; PM H4/H6 | Destinatario puede leer, descargar e imprimir lo que le corresponde durante el plazo aplicable. |
| RP-08 | Recuperación e incidencias | PM §§4, 6; MF-08–10 | Reintento/consulta no cambia identidad; incidentes no resueltos permanecen visibles y asignados. |
| RP-09 | Usuarios/roles locales y separación por instalación/cliente | Adenda §§1–3; PM §§1, 3–4; AGENTS 9 | Sin selector global de clientes ni BD compartida; acceso a recurso o archivo ajeno denegado. |
| RP-10 | Custodia técnica por el proveedor y evidencia histórica | Adenda §2; PM §§0, 3–4; AGENTS 7 y 9 | Secretos cifrados, claves separadas, acceso administrativo autorizado/auditado y revocación; no afirmar custodia exclusiva del cliente ni alterar históricos. |
| RP-11 | Conservación, respaldo y recuperación por cliente | Adenda §§3–4; PM H7; MF-20 | Respaldo cifrado separado del VPS, base/archivos/material de recuperación coherentes y restauración demostrada antes de liberar. |
| RP-12 | Interfaz clara, accesible y con ambiente fiscal inequívoco | PM §3/H6; AGENTS 6 | Operador reconoce acción, errores y ambiente efectivo antes de emitir; navegación por teclado. |
| RP-13 | Operación comercial controlada | PM H7–H8 y §8 | Sin defectos críticos; piloto real autorizado, soporte y aceptación humana documentados. |
| RP-14 | Recorte inicial de casuística | PROPUESTA D-02/D-07 | Se muestra la lista exacta aprobada; ninguna operación no soportada se acepta silenciosamente. |

## Alcance propuesto para decidir, no asumido

Se conserva el perfil preliminar indicado arriba como base de revisión. Factura a receptor con RUC y boleta a consumidor final; NC inicialmente por anulación de operación y devolución, y ND por aumento de valor/costo permitido siguen siendo **propuestas sujetas a validar cada motivo y su código**. La inspección del libro oficial en v0.2 no aprueba automáticamente motivos, impuestos ni operaciones del piloto.

Para boletas, D-07 mantiene el resumen diario como candidato a ruta única, **sin ratificación fiscal definitiva para la modalidad del emisor y la prestación administrada**. La alternativa individual se ha localizado; no se implementarán ambas por defecto. Las notas seguirán el procedimiento aprobado según documento y modalidad.

Se propone capturar los datos del receptor y las líneas del documento sin exigir previamente un catálogo de clientes o productos. La existencia de historial de receptores no autoriza construir un CRM. El acceso del receptor se resolverá con el mecanismo mínimo que satisfaga MF-19 y seguridad.

Este documento define V1 propuesta; no acredita aún que un cliente concreto encaje. Antes de emitir realmente deben confirmarse actividad, régimen, bienes/servicios, tributos, pago, moneda y series; volumen/capacidad se justifican antes de comprometer la operación. No se exigen esos datos reales para una POC H1 ficticia. Si el piloto necesita crédito, detracciones u otra obligación indispensable, se revisa el recorte; no se omite una obligación para encajar el negocio.

## Exclusiones

El Plan excluye de V1 salvo decisión expresa: ERP, contabilidad/declaraciones, inventario avanzado, omnicanalidad, POS complejo, cobros SaaS, GRE 2.0, exportación avanzada, detracciones complejas, honorarios y cobertura universal de regímenes. SIRE identificado en la revisión normativa no se añade como módulo.

Además se propone aplazar crédito/cuotas, anticipos, gratuidades, moneda extranjera, regímenes/beneficios especiales, impuestos especiales y correcciones excepcionales de receptor/descripción hasta validar necesidad y reglas. Estas son **exclusiones propuestas del producto**, no prohibiciones tributarias. No excluir retenciones, percepciones o SPOT indispensables para una operación real sin ajustar el piloto y su aprobación fiscal.

## Casos de uso esenciales

1. Responsable configura un emisor y acredita su habilitación; operador solo puede emitir en una empresa autorizada.
2. Operador prepara borrador, ve totales, ambiente y destinatario; confirma una vez. Una solicitud repetida conserva la misma operación lógica.
3. Sistema prepara y persiste la emisión; entrega el resultado real y permite consultar evidencia. Si hay incertidumbre, informa y concilia.
4. Responsable sigue boletas/resúmenes, resultados y plazos; resuelve incidencias sin regenerar documentos históricos.
5. Operador solicita corrección o baja; el sistema ofrece únicamente procedimientos elegibles del alcance aprobado.
6. Receptor consulta y descarga sus documentos; soporte investiga incidentes mediante acceso autorizado.

Los pasos y excepciones se especifican en [flujos](02_APP_FLOW.md). No se presupone una API pública para integradores, aplicaciones móviles, envío masivo de correo ni WhatsApp.

La administración incluye alta, soporte, despliegues y salida controlada. La adenda §2 es la matriz de responsabilidades: proveedor mantiene controles y recuperación; cliente/representante y profesional competente acreditan datos y autorizaciones y deciden actuaciones tributarias. Antes del piloto real se acordarán titularidad/pago del VPS, datos, accesos, soporte, retención, exportación y baja del servicio. No se promete SLA ni RPO/RTO sin demostración.

## Aceptación comercial y límites medibles

La V1 será aceptable cuando el piloto pueda operar todos los casos aprobados desde la interfaz, distinguir aceptación/rechazo/incertidumbre, recuperar evidencias y acceder a soporte; además debe superar los escenarios críticos del Plan §6 y la aceptación fiscal aplicable. Una factura aislada de H2 no representa V1 terminada.

Volumen esperado, concurrencia, almacenamiento, disponibilidad, RPO y RTO quedan para propuesta justificada antes de comprometer la operación; no son necesarios para ensayar compatibilidad ficticia en H1. No se fijan presupuesto, capacidad, servicios adicionales, porcentajes ni fechas de entrega. La integridad, el aislamiento y la persistencia segura son condiciones del Plan, aun sin métricas de rendimiento acordadas.

## Firma de alcance

Propietario: **pendiente**. Revisor fiscal: **pendiente**. Fecha y versión aprobadas: **pendientes**. Casos y exclusiones finales: **pendientes D-02**. Aprobación de este PRD no constituye autorización para iniciar H1 ni para usar datos, credenciales o certificados reales.
