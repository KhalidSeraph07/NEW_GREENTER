# AGENTS.md — 13 reglas universales de ingeniería

## 1. Modularidad proporcional
Organiza el código por responsabilidades y funcionalidades del negocio, respetando las convenciones del lenguaje y del repositorio. Mantén próximas las piezas relacionadas. Separa módulos únicamente cuando reduzca el acoplamiento, facilite la comprensión o permita probar y mantener responsabilidades independientes. No multipliques archivos, carpetas o capas por estética.

## 2. Responsabilidad única, tamaño de archivos y legibilidad
Cada función, clase, componente o archivo debe tener un propósito identificable. Para archivos de código escritos manualmente, utiliza **unas 400 líneas como referencia orientativa**, nunca como meta ni límite obligatorio. Entre **401 y 500 líneas** puede ser perfectamente razonable si el código es cohesivo; por encima de **500**, revisa si conviene extraer responsabilidades; por encima de **800**, prioriza una revisión de mantenibilidad. **No dividas automáticamente ni rechaces código por superar esos números.** El código generado, migraciones, pruebas o archivos declarativos pueden necesitar criterios distintos. La regla tampoco impone un máximo de líneas por función: evita fragmentar una operación clara en muchas funciones triviales.

## 3. Nombres descriptivos y consistentes
Utiliza nombres que expresen la intención, los datos y los resultados. Respeta las convenciones de cada lenguaje y el estilo existente del proyecto. Evita abreviaturas oscuras y nombres genéricos cuando oculten el significado, pero no impongas prohibiciones arbitrarias de palabras. El código debe poder comprenderse, en gran medida, por su estructura y sus nombres.

## 4. Simplicidad sin sobreingeniería
Implementa la solución más sencilla que satisfaga los requisitos, los riesgos y las restricciones reales. No agregues patrones, servicios, repositorios, interfaces, fábricas, configuraciones o capas sin un beneficio concreto. No refactorices por estética ni para reducir artificialmente líneas. Prefiere las herramientas estándar y dependencias ya aprobadas; solicita autorización antes de incorporar dependencias nuevas o realizar actualizaciones mayores que alteren el riesgo del proyecto.

## 5. Evitar duplicación perjudicial
Centraliza reglas y operaciones que deban evolucionar conjuntamente, especialmente cálculos y validaciones del negocio. No fuerces una abstracción compartida entre procesos que se parecen superficialmente, pero tienen responsabilidades o cambios futuros diferentes. La reutilización debe reducir errores y mantenimiento, no producir acoplamiento innecesario.

## 6. Separación de responsabilidades e interfaces limpias
Mantén separadas, cuando corresponda, presentación, reglas de negocio, acceso a datos, integraciones y configuración, sin crear capas vacías. Los secretos y las decisiones de autorización nunca deben depender de la interfaz.

En la **interfaz**, prioriza claridad, jerarquía visual, accesibilidad y estados útiles. Evita banners, badges, ayudas, etiquetas y mensajes técnicos repetidos o invasivos. No elimines etiquetas de campos, validaciones, advertencias ni estados necesarios por buscar una apariencia minimalista.

En **Beta o pruebas**, identifica el ambiente fiscal efectivo de forma discreta pero inequívoca, especialmente antes de acciones que puedan confundirse con operaciones reales. En **Producción**, no muestres marcas de demostración, datos ficticios ni mensajes de depuración, pero conserva errores, estados y avisos importantes. Una aplicación desplegada en producción puede utilizar un ambiente fiscal de pruebas: en ese caso debe seguir advirtiéndolo. No desarrolles un sistema complejo de banderas visuales si basta con la configuración existente.

## 7. Manejo explícito de errores
No ocultes fallos relevantes ni devuelvas resultados exitosos ficticios. Trata, propaga o registra cada error en el nivel adecuado y con contexto suficiente para diagnosticarlo, evitando duplicar el mismo registro entre capas. Emplea los mecanismos idiomáticos de cada lenguaje. Los mensajes públicos y logs no deben revelar contraseñas, tokens, claves privadas ni otros secretos.

## 8. Comentarios y documentación con propósito
**No comentes lo obvio.** Si el código, sus nombres y su estructura explican una operación, no añadas comentarios que narren cada `if`, asignación, bucle o retorno. Documenta sobre todo **por qué** existe una decisión no evidente: invariantes, restricciones, compatibilidad, riesgos, contratos, efectos secundarios y casos límite. Para una API pública o una operación compleja, documenta entradas, salidas y errores cuando aporte claridad. Antes de explicar código confuso mediante un comentario, intenta simplificar el código. Mantén actualizada la documentación que realmente se utilice y elimina comentarios obsoletos.

## 9. Seguridad proporcional, integrada y verificable
Protege los límites de confianza aplicables a cada cambio, sin crear cuatro sistemas, carpetas o capas físicas obligatorias:

- **Exposición:** publica únicamente rutas y recursos necesarios; nunca expongas `.env`, certificados, claves, repositorios, respaldos, archivos privados o diagnósticos internos. Desactiva la depuración pública en producción.
- **Acceso:** verifica identidad, permisos y propiedad del recurso **en el servidor**, no solo ocultando botones. Usa sesiones o tokens seguros, protección CSRF cuando corresponda y límites de intentos proporcionados al riesgo.
- **Datos:** valida entradas y archivos, previene inyecciones, protege secretos, cifra información sensible cuando corresponda y emplea transacciones e idempotencia en operaciones críticas. Nunca mezcles datos de empresas o usuarios sin autorización. Conserva respaldos y prueba su restauración.
- **Infraestructura:** aplica HTTPS/TLS, permisos mínimos, puertos necesarios, separación entre entornos, dependencias mantenidas, despliegues controlados, monitoreo y recuperación según la criticidad.

No elimines una protección real para simplificar. No declares seguro un sistema porque el código compila, las pruebas pasan o la interfaz oculta una opción; verifica los controles pertinentes.

## 10. Pruebas que demuestren comportamiento
Prueba resultados importantes, errores, límites, concurrencia, permisos e invariantes relevantes. Prioriza pruebas reproducibles que detecten defectos reales sobre la cantidad de archivos de test o un porcentaje de cobertura aislado. No modifiques, relajes ni deshabilites pruebas únicamente para obtener resultados verdes. Indica qué se ejecutó, qué pasó y qué quedó sin verificar. Nunca uses afirmaciones de éxito sin evidencia.

## 11. Cambios acotados y protección de Git
Cada intervención debe perseguir un objetivo concreto y producir diferencias revisables. No mezcles nuevas funcionalidades, refactorizaciones extensas y cambios cosméticos sin necesidad y autorización. Antes de mover, sustituir o eliminar componentes, revisa consumidores, contratos y efectos secundarios. No ejecutes comandos destructivos de Git ni descartes cambios locales ajenos sin autorización explícita. Protege bases de datos y datos existentes: utiliza migraciones adecuadas al entorno y nunca restablezcas o borres datos por conveniencia.

## 12. Trazabilidad y revisión real
Al terminar una tarea, explica brevemente qué cambió, por qué, cuáles son los archivos o contratos afectados, qué pruebas se ejecutaron, cuáles fueron sus resultados y qué riesgos siguen abiertos. Utiliza commits y revisiones coherentes con el flujo del repositorio. El revisor debe inspeccionar los cambios reales y sus evidencias, no aprobar únicamente el informe del agente implementador.

## 13. Preservar el comportamiento al refactorizar
Antes de una refactorización, comprende el comportamiento existente, sus dependencias y sus pruebas. Cuando el área sea crítica, añade pruebas de caracterización si resultan útiles. Cambia una responsabilidad por vez y verifica antes y después. No alteres contratos, datos, resultados ni efectos secundarios sin autorización. Si se solicita retirar una funcionalidad, identifica y conserva las piezas que todavía usan otras partes del sistema. No reabras trabajo aprobado salvo defecto demostrado, cambio de requisito o riesgo justificado.
