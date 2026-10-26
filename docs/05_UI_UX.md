# UI/UX — H0, versión 0.3

**24/10/2026 · Alcance conceptual, sin mockups ni frontend.** Fuente: Plan Maestro §§1, 3–4, H4–H6; AGENTS regla 6; [PRD](01_PRD.md) y [flujos](02_APP_FLOW.md). Navegación y textos finales sujetos a los casos aprobados.

## Navegación mínima propuesta

| Área | Contenido útil | Prioridad |
|---|---|---|
| Acceso | Inicio/cierre de sesión y usuarios/roles de la instalación del cliente | Base segura H2. |
| Comprobantes | Lista, filtros necesarios, borrador/formulario y detalle | Factura mínima H2; completar por hitos. |
| Comunicaciones e incidencias | Resúmenes/bajas, ticket, resultado, plazos y siguiente acción | H3–H5 según flujo. Puede integrarse en lista/detalle si basta. |
| Configuración de empresa | Datos fiscales, ambiente, series, estado de certificado y permisos | Lo necesario para emitir en H2; completar asistencia H6. |
| Consulta del receptor | Acceso reservado, visualización, descarga e impresión de sus documentos | MF-19; V1, mecanismo pendiente D-08. |

La adenda define una instalación por cliente y un RUC inicial: no hay selector global de empresas/clientes ni consola SaaS compartida. No se obliga a dashboard decorativo, CRM, catálogo de productos, menú de cobros o portal administrativo independiente. Se conservan pantallas, accesibilidad y estados útiles ya descritos.

## Emisión y revisión

El formulario debe identificar claramente empresa, tipo de documento, receptor, fecha, moneda, condición de pago, líneas y totales pertinentes al caso. Las opciones fuera de alcance no aparecen como capacidades disponibles. Mantener etiquetas y errores por campo; conservar lo escrito ante una validación fallida.

Antes de confirmar se muestran los datos fiscales relevantes, importe, destinatario y ambiente efectivo. Prevenir doble clic mejora la experiencia, pero la protección real contra duplicación pertenece al servidor. Tras confirmar, el usuario recibe un identificador y estado consultable; una pantalla de éxito no sustituye la evidencia fiscal.

El detalle reúne contenido, archivos, resultado, historial útil y acciones elegibles. Un comprobante preparado no vuelve a un formulario editable que sobrescriba su historia. Las correcciones se explican y se vinculan con el original.

## Estados y mensajes

| Situación respaldada | Texto orientativo | Acción útil |
|---|---|---|
| Borrador | «Borrador» | Editar o revisar. |
| Preparado pendiente de envío | «Preparado. Pendiente de envío a SUNAT» | Consultar avance; no volver a emitir. |
| Boleta pendiente de comunicación | «Pendiente de informar a SUNAT» | Ver resumen y plazo. |
| Ticket sin resultado | «SUNAT recibió la comunicación. Resultado en proceso» | Consultar seguimiento. |
| Timeout o respuesta perdida | «No se pudo confirmar el resultado ante SUNAT» | Ver incidencia/conciliación; no mostrar rechazo. |
| Aceptación documentada | «Aceptado» con acceso a evidencia aplicable | Ver archivos/entrega. |
| Aceptación con observación | «Aceptado con observaciones» | Leer observación saneada y acción que corresponda. |
| Rechazo confirmado | «Rechazado por SUNAT» con motivo comprensible | Revisar procedimiento permitido. |
| Error técnico sin rechazo fiscal | «No se completó el envío» o descripción de dependencia | Atender error o seguir recuperación. |
| Baja confirmada | Situación de baja con comunicación/evidencia vinculadas | Consultar original e historial. |

Los textos son propuestas de interfaz. Mostrar aceptación con observaciones solo si el tipo y la evidencia lo sustentan. En boletas, distinguir resultado de comunicación y situación informada del documento. El rechazo del adquirente, cuando aplique, tampoco se confunde con rechazo de validación SUNAT.

## Ambiente, confidencialidad y accesibilidad

Identificar «SUNAT: pruebas» de forma discreta y permanente donde pueda confundirse con operación real, especialmente al confirmar. Si la aplicación está desplegada en producción pero usa SUNAT Beta, conserva esa indicación. El modo real evita marcas ficticias y depuración, manteniendo advertencias operativas necesarias.

Certificados y claves nunca se vuelven a mostrar completos. No incluir XML crudo, stack traces ni credenciales en mensajes generales. Reservar evidencias técnicas saneadas a usuarios autorizados. Acceso del receptor comprueba identidad/propiedad incluso si comparte o altera el enlace.

Etiquetas asociadas, navegación por teclado, foco visible y foco en errores, contraste suficiente y estados que no dependan únicamente del color. Tablas, filtros, formularios y descargas deben funcionar con teclado. H6/H7 comprobarán estos comportamientos, sin inventar una certificación de accesibilidad ya lograda.

## Decisiones pendientes

Mecanismo de autenticación del receptor y entrega electrónica/impresa; perfiles mínimos; operaciones tributarias habilitadas; formato de representación y elementos obligatorios (incluidos QR/leyendas cuando corresponda) contra anexos vigentes; idioma y dispositivos realmente usados por el piloto. No construir una pantalla para una regla fiscal todavía pendiente ni prometer notificaciones externas por un canal no aprobado.
