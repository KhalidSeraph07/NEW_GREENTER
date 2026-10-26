# H5-A — NC 01 y ND 02 de factura, exclusivamente ficticias

Autorización expresa del propietario, 26/10/2026. [D-02/AF-06, exclusiones y reservas fiscales](12_H5_PREPARACION_FISCAL.md). H5-A abierto, rama/PR Draft; no fusionar ni iniciar H5-B/H6 sin aprobación. Sin SUNAT/Beta/producción ni identidades, credenciales o certificados fiscales reales.

| Entrega | Dependencia | Criterio verificable |
|---|---|---|
| A1 Preparación NC/ND | Recorte aprobado | Factura aceptada simulada propia, otorgamiento ficticio explícito, un ítem/PEN; NC exacta o incremento ND; correlativo por tipo/serie y referencia congelada, XML/firma/ZIP privados, outbox en mismo commit. |
| A2 Ciclo simulado | A1 | Reserva durable, respuestas correlacionadas; incertidumbre sin reenvío; reintentos acotados solo ante prueba ficticia de no procesamiento; recuperación por registro local claramente ficticio; D+3 desde la nota. |
| A3 Acceso e historia | A1/A2 | Formularios mínimos con propiedad/CSRF, descargas privadas y eventos; nunca modificar documento original ni sumar notas incompatibles. |
| A4 Evidencia/regresión | A1–A3 | Carrera NC contra ND, replay, SIGKILL, fallos/negativos, integridad histórica y evidencias; H1–H4 completos y CI remoto del HEAD del PR. |

Se reutiliza el cálculo entero H2 sin alterarlo, Greenter instalado y los patrones de reserva/cercado/consulta de H3. Las notas tienen registros y almacenamiento propios: `invoices` admite solo 01 y el reconciliador H3 solo conoce sus archivos. No se extraen abstracciones de motores correctos ni se alteran sus tablas, migraciones, numeración o pruebas. Continúa el bloqueo de correlativo durante firma; capacidad y tratamiento de huérfanos siguen H7.

Primera entrega: una nota persistida por original, incluso rechazada/incierta; ningún rechazo libera automáticamente la referencia. Replay devuelve la misma nota; rollback previo al commit no consume identidad ni reserva. Otorgamiento se declara explícitamente **ficticio** en la nota, sin reescribir históricos. No acredita entrega real H6. No hay bajas, correcciones RC, parciales, otros motivos, notas de boleta ni nuevos RC.

Resultados, comandos, CI y pendientes se completarán con pruebas ejecutadas; este plan no acredita resultados aún.
