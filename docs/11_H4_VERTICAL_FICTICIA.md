# H4 — Boleta y RC exclusivamente ficticios

D-07 aprobada para V1; propietario autoriza esta vertical ficticia en rama/PR Draft, sin fusión ni cierre automáticos. H5, envío individual, notas, anulaciones, GRE, contingencia impresa y comunicaciones/identidad/credenciales fiscales reales prohibidos. [Reglas y reservas fiscales D-07](10_D07_BOLETAS.md).

| Entrega | Dependencia | Aceptación verificable |
|---|---|---|
| H4-01 Boleta 03 | D-07 y autorización | Entrada restringida y cálculo H2, B000 independiente de F000, XML UBL 2.1/firma/ZIP privados, replay sin nueva numeración. |
| H4-02 RC inmutable | H4-01 | Un emisor/propietario/fecha por lote, miembros explícitos y únicos, identidad/bytes congelados, UBL 2.0/estructura 1.1, outbox en el commit; D+7 simulado. |
| H4-03 Ticket y consulta | H4-02 | Reserva durable antes del simulador; ticket ficticio no es aceptación; 98/0/99 y constancia ficticia correlacionados; incertidumbre sin reenvío, consultas acotadas, recuperación solo con ticket conservado. |
| H4-04 Evidencias y regresión | H4-01–03 | Concurrencia/idempotencia/fallos de procesos/consulta tardía y ticket perdido; control de propiedad/CSRF; H1/H2/H3 intactos, CI y preservación de filas/archivos preexistentes. |

Se conserva el motor de facturas sin cambios. H4 usa su validador/cálculo puro y los modelos Greenter instalados, con registros/archivos propios para no mezclar outbox ni reconciliación de facturas. Reutiliza los patrones probados de reserva, correlación, replay y conservación poscommit; sin infraestructura nueva. Boleta y RC retienen bloqueo de numeración durante firma/escritura como en el ensayo anterior; no se promete capacidad.

Recorte de altas: un miembro no puede incorporarse en otro RC, incluso si el primero fue rechazado; su corrección requeriría alcance posterior explícito. Esta restricción conservadora del ensayo no se presenta como prohibición SUNAT. Un lote incierto nunca se reconstruye ni se reemplaza para sortear la incertidumbre. El simulador solo consulta por ticket; sin lookup de RC/UUID para recuperar uno perdido.

## Resultados

Implementación y evidencias pendientes. H4 abierto; H5 no autorizado.
