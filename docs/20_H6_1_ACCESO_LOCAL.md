# H6-1 — autenticación y permisos locales, pendiente de revisión

Base aprobada `85b88ae04bc86c7a1177c7373e1559cdf221c9c4`. Rama `codex/h6-1-sesiones-permisos`. Solo H6-1 autorizado: no cerrar ni fusionar sin aprobación expresa. H5 sigue cerrado ficticiamente con sus observaciones.

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
