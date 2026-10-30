# H6-1 — cerrado con observaciones, exclusivamente ficticio y local

**29/10/2026 · Cierre aprobado expresamente por el propietario** sobre `5bdb5531a2fcb495bc1903c31afeb2ef95ab370e`, del [PR #7](https://github.com/KhalidSeraph07/NEW_GREENTER/pull/7), base `85b88ae04bc86c7a1177c7373e1559cdf221c9c4`. PR marcado Ready for Review y fusionado mediante merge commit `d903ae1bb2aafab38de89c4985de04b1f0ece005`, sin squash ni rebase. H5 conserva su cierre ficticio con observaciones. **H6-1 cerrado no equivale a H6 completo ni autoriza sus siguientes entregas.**

| Acción | ADMIN activo | OPERADOR activo |
|---|---|---|
| Preparar/procesar/consultar/descargar flujos HTTP existentes | Solo documentos propios | Solo documentos propios |
| Crear y desactivar cuentas | Sí; nunca el último ADMIN activo | No |
| Configuración ficticia de solo lectura | Sí | Sí |
| Documentos de otro usuario, acceso compartido/receptor o configuración fiscal editable | No | No |

Sesiones nativas, sin HTTP Basic alternativo ni registro público/remember-me. Login regenera ID/token; logout invalida sesión/token; cada solicitud verifica cuenta activa y rol. CSRF, límites por IP y cuenta/IP, cabeceras incluso en errores, entorno local/testing y host localhost/127.0.0.1. Cookie HttpOnly/SameSite=Lax y vida 30 minutos; Secure=false únicamente para HTTP loopback. No habilita exposición remota.

Migración 000014: agrega `role=OPERADOR` e `is_active=true`, correspondientes al acceso previo, sin promover a ADMIN ni alterar atributos históricos. Creación/desactivación bajo el mismo bloqueo PostgreSQL; roles limitados por CHECK. Sin edición de roles, borrado o sobrescritura de credenciales. Se deshabilita el rehash automático al iniciar sesión para conservar hashes anteriores; política futura de actualización de credenciales requiere revisión. No cambia motores fiscales, numeraciones, snapshots, reservas ni evidencias.

## Reproducción local

Desde la raíz, con Docker disponible y la configuración local existente:

```powershell
# Una sola captura antes de migrar/probar; no sobrescribe snapshots previos.
docker compose run --rm php php tests/h6-preservation.php before
docker compose run --rm php php artisan migrate --force
docker compose run --rm -e DB_DATABASE=emisor_h2_test php php artisan migrate --force
docker compose run --rm php vendor/bin/phpunit --testsuite "H6-1 acceso local"
docker compose run --rm php vendor/bin/phpunit
docker compose up -d web
python tests/h6-http-smoke.py --output-dir storage/audit/h6-1/http-UNICO
docker compose run --rm php php tests/h6-preservation.php after
```

El primer ADMIN se prepara **solo por el responsable autorizado**, con un correo nuevo y nombre explícitos:

```powershell
docker compose run --rm php php artisan h6:bootstrap-admin --email=admin.local@example.invalid --name="Responsable local"
```

El comando solicita contraseña oculta y confirmación, sin valor predeterminado, argumento de contraseña ni promoción de cuentas existentes; rechaza ejecución no interactiva y un segundo bootstrap. Cambiar el correo/nombre del ejemplo no autoriza credenciales fiscales reales. La implementación no crea un ADMIN de desarrollo automáticamente. Para habilitar cuentas siguientes se usa /accounts con ADMIN; /settings es solo lectura.

## Evidencias y límites

Evidencias locales separadas en `storage/audit/h6-1/`; el informe del PR enlaza CI del HEAD exacto y distingue resultados locales/remotos. Pruebas de acceso H2–H5 adaptadas a login/sesiones manteniendo propiedad, CSRF, idempotencia y privacidad; el ensayo HTTP conserva 71 controles previos y añade controles H6. Preservación compara todas las filas previas y las rutas protegidas: para usuarios compara columnas históricas, excluyendo únicamente las dos columnas añadidas. No demuestra restauración.

**Decisión pendiente sobre propiedad:** conservar `user_id` como propietario. Antes de acceso compartido, proponer una identidad de instalación/empresa emisora, autor responsable separado y permisos explícitos de lectura/operación; mapear históricos sin cambiar su autor ni conceder acceso por ADMIN. No implementado ni necesario para H6-1. El receptor corresponde a H6-4.

Conservar fiscal/SEE/PSE/B-01/2957 y otorgamiento real (profesional fiscal/representante antes de actividad real), seguridad/operación/firma transaccional/restauración y 65 candidatos huérfanos/fixture inválida/causa de enumeración desconocida (técnico/operador/propietario, H7 y puertas previas), y licencia XMldsig (propietario/asesoría antes de distribución), conforme docs/19. H6-2/3/4, H7/H8, SUNAT/Beta/producción e identidades/certificados/credenciales fiscales reales permanecen prohibidos. Sin aptitud para producción ni cierre automático.

## Registro de cierre y pendientes conservados

Antes de fusionar se verificó HEAD exacto, [CI Push 40984235129](https://github.com/KhalidSeraph07/NEW_GREENTER/actions/runs/40984235129) y [CI PR 40984240539](https://github.com/KhalidSeraph07/NEW_GREENTER/actions/runs/40984240539) completed/success, PR mergeable/clean, sin solicitudes, revisiones ni hilos pendientes. La aprobación técnica humana consta en la instrucción del propietario; no se inventa una revisión formal en GitHub. El merge conserva exactamente el árbol del HEAD aprobado y tiene padres `85b88ae04bc86c7a1177c7373e1559cdf221c9c4` y `5bdb5531a2fcb495bc1903c31afeb2ef95ab370e`.

Evidencias aceptadas: ambos CI, **401 pruebas / 2.582 aserciones, 26 comprobaciones H1 y 83 controles HTTP**; local específico, 17 pruebas / 144 aserciones y 83 controles HTTP. Preservación local final de 23.833 filas previas, incluidas 2.244 cuentas sin elevación, y 18.777 archivos protegidos, registrada en `storage/audit/h6-1/registro-final.json`. La ejecución exploratoria local de una versión intermedia (396/2.567) se distingue del HEAD final. El CI posterior al merge y al registro documental se verifica por separado y se enlaza en el informe final, con evidencia en `storage/audit/h6-1/cierre/`.

| Pendiente | Responsable | Puerta conservada |
|---|---|---|
| Recuperación operativa de credenciales, incluido acceso administrativo perdido | Técnico + propietario | Diseñar y probar bajo autorización posterior antes de depender de ese procedimiento en operación; no resuelto por bootstrap ni login. |
| Revisión de cuentas/permisos y política de credenciales para uso comercial | Técnico/seguridad + propietario | Antes de exposición o uso comercial; H7 y cualquier trabajo adicional requieren autorización. |
| Acceso compartido: empresa emisora, empleado responsable y permisos documentales | Propietario + técnico | Propuesta y aprobación expresa antes de implementar; conservar `user_id` y denegación entre usuarios mientras tanto. |

Todas las exclusiones, observaciones, responsables y puertas anteriores permanecen vigentes. Este cierre modifica solo documentación: no ejecuta migraciones ni pruebas locales que agreguen fixtures, ni altera código, credenciales, cuentas, documentos o evidencias históricas. H6-2/3/4, H7/H8 y comunicaciones/identidades/certificados/credenciales fiscales reales continúan sin autorización. No se declara aptitud para producción.
