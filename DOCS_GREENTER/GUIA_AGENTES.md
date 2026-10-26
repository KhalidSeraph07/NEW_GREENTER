# Instrucciones para agentes

## `STACK_INIT.md` — Inicialización única
Se utiliza **una sola vez**, al crear el proyecto desde cero. Define cómo preparar el entorno inicial. Una vez inicializado, **no debe volver a ejecutarse** en el trabajo diario ni sobre un proyecto existente, salvo autorización explícita. No debe sobrescribir configuraciones, archivos ni datos existentes.

## `AGENTS.md` — Reglas permanentes
Debe mantenerse en la **raíz del proyecto**. Es la referencia vigente para todo agente que participe en su desarrollo, revisión o mantenimiento. Cada agente debe **leerlo al comenzar su tarea y respetarlo durante todo el trabajo**, también en sesiones nuevas.

**Orden de uso:** en un proyecto nuevo, consultar `AGENTS.md` y ejecutar `STACK_INIT.md` solo para la inicialización autorizada. En adelante, trabajar conforme a `AGENTS.md` sin repetir la inicialización.

> Estos archivos son instrucciones de trabajo: no ejecutan tareas ni mantienen agentes activos por sí mismos.
