# STACK_INIT.md — Inicio técnico del emisor SUNAT (Día 0)

> **Revisión:** 24 de octubre de 2026.  
> **Propósito:** preparar una base local limpia, segura y reproducible para una aplicación independiente de **emisión y gestión de comprobantes electrónicos en Perú**.  
> **Uso:** ejecutar una sola vez al iniciar un repositorio nuevo. En proyectos existentes, inspeccionar y adaptar; **no reinicializar, sobrescribir ni borrar datos**.  
> **Reglas de desarrollo:** respetar `AGENTS.md`. Este documento no sustituye el PRD, la matriz tributaria ni los criterios de aceptación.

---

## 1. Stack propuesto: decisiones y flexibilidad

| Tecnología | Estado | Regla |
| --- | --- | --- |
| Docker Compose | **Acordado** | Base del entorno local; añadir servicios conforme se necesiten. |
| PostgreSQL | **Acordado** | Persistencia principal. Usar una versión soportada y actualizada. |
| PHP + Laravel | **Backend previsto** | Confirmar rama y versión **parcheada**, compatible y estable antes de instalar. |
| Greenter | **Motor fiscal previsto** | Incorporar cuando se definan contratos y pruebas fiscales; verificar dependencias reales. |
| Vue 3 + Inertia.js | **Recomendación provisional** | Decidir al diseñar los flujos de interfaz. No crear un frontend separado por inercia. |
| Redis + Horizon | **Diferido** | Agregar cuando se necesiten trabajadores/colas; PostgreSQL seguirá siendo la fuente persistente de trabajos fiscales. |
| Servidor web, workers, scheduler, almacenamiento privado | **Por etapas** | Implementar al existir una capacidad concreta y una prueba verificable. |
| Versiones, puertos, nombres y estructura | **No fijados globalmente** | Registrar los valores efectivos cuando se instalen; evitar elecciones irreversibles prematuras. |

**Arquitectura preferida:** monolito modular. No introducir microservicios, Kubernetes ni capas vacías sin un requisito demostrable.

**Regla importante:** este archivo **no ordena instalar Laravel, PHP, Greenter ni Redis en el Día 0**. Su función inicial es preparar PostgreSQL con Compose y establecer controles para cuando se incorporen esos componentes.

---

## 2. Alerta de seguridad verificada: PHP (21–24 octubre 2026)

El **21/10/2026** PHP publicó correcciones para dos fallos críticos de **ejecución remota de código sin autenticación** que afectan a instalaciones PHP que atienden solicitudes web, incluidas PHP-FPM y otras modalidades:

- **CVE-2026-109618:** corrupción de memoria durante manejo de memoria insuficiente (OOM).
- **CVE-2026-109619:** acceso a memoria liberada debido a estados internos persistentes entre solicitudes.
- Ambos recibieron **CVSS v4.0 9,2**. La explotación puede ejecutar código con los privilegios del proceso PHP, incluso antes de que se procese la lógica de Laravel. No implica acceso `root` automáticamente.

| Rama | Mínima versión oficial que corrige ambos CVE |
| --- | --- |
| PHP 8.5 | **8.5.12** |
| PHP 8.4 | **8.4.27** |
| PHP 8.3 | **8.3.36** |
| PHP 8.2 | **8.2.35** |

**No implementar ni publicar PHP con una versión vulnerable conocida.** PHP 8.1 o anterior no es una opción recomendable para este proyecto nuevo. Una configuración restrictiva, WAF o contenedor **no sustituye el parche**.

**Aviso adicional del 23/10/2026:** PHP informó **CVE-2026-115560**, relacionado con un vector de RCE bajo una combinación inusual de ajustes de sesión. Mientras no se publique una corrección aplicable, PHP recomienda configurar `session.upload_progress.enabled=0`. Cuando exista una imagen PHP para la aplicación, aplicar esta directiva **antes del procesamiento de solicitudes** mediante configuración PHP efectiva (por ejemplo, `php.ini` de PHP-FPM), reiniciar los procesos y comprobar el valor. No confiar en cambiarlo tardíamente con `ini_set()`.

**Compatibilidad excepcionalmente importante para emisión fiscal:** se reportó una **regresión confirmada de `SimpleXML`** en PHP **8.4.27 y 8.5.12**, que puede provocar fallos de memoria en ciertos ciclos de referencias. Los reportes indican que **8.3.36 no presenta esa regresión específica**. Por tanto:

1. **PHP 8.3.36** es un **candidato prudente temporal** para evaluar Laravel + Greenter, no una versión definitiva obligatoria. PHP 8.3 conserva soporte de seguridad hasta diciembre de 2027.
2. No seleccionar 8.4.27/8.5.12 a ciegas: comprobar si ya se publicó el arreglo de `SimpleXML` y ejecutar pruebas reales de generación, firma, lectura, serialización y procesamiento de XML/CDR.
3. **Nunca retroceder a PHP 8.4.26 o 8.5.11** para evitar esa regresión: dichas versiones carecen de la corrección de RCE.
4. Revisar nuevamente avisos y versiones **el día que se construya la imagen**. Esta tabla es una referencia con fecha, no un catálogo que permanecerá vigente automáticamente.

**Fuentes verificables:**

- PHP: https://www.php.net/archive/2026.php
- Aviso CERT-EU: https://www.cert.europa.eu/publications/security-advisories/2026-018
- Versiones PHP soportadas: https://www.php.net/supported-versions.php
- Regresión `SimpleXML`: https://github.com/php/php-src/issues/24488
- Seguimiento imágenes PHP: https://github.com/docker-library/php/issues/1706

---

## 3. Política ligera de dependencias y contenedores

Al incorporar **cada** componente:

1. Revisar versión con soporte, correcciones de seguridad publicadas, compatibilidad con las demás dependencias y problemas conocidos recientes. Priorizar comunicados del mantenedor.
2. Evitar versiones `alpha`, `beta`, `RC`, EOL y versiones con vulnerabilidades graves sin corregir en entornos reales. Registrar excepciones justificadas antes de continuar.
3. Conservar `composer.lock` y el archivo de bloqueo del gestor frontend **cuando existan**. Ejecutar `composer audit` y la auditoría apropiada del gestor JS al incorporar dependencias; revisar sus resultados, no ocultarlos.
4. Usar imágenes oficiales o de procedencia confiable. Evitar `latest` indiscriminado. Registrar la **versión real de PHP dentro de cada contenedor** (web, CLI y workers) y las imágenes/digests desplegados; el tag por sí solo no prueba que un contenedor en ejecución esté parcheado.
5. Actualizar imágenes de forma controlada, reconstruir y **recrear** los contenedores afectados; verificar nuevamente `php -v`, las extensiones, la configuración y las pruebas. Un simple reinicio no actualiza una imagen antigua.
6. Para producción, usar imágenes aprobadas e identificables por digest en despliegues reproducibles; establecer un procedimiento explícito para reemplazarlas con parches, no congelarlas indefinidamente.
7. No instalar dependencias ni diseñar contenedores adicionales solo para satisfacer una lista de herramientas.

**Cuando se inicialice PHP/Laravel, comprobar antes de avanzar:** compatibilidad Laravel–PHP–Greenter, versión efectiva y parcheada, `session.upload_progress.enabled=0` mientras aplique, extensiones necesarias, pruebas de XML y ausencia de regresiones bloqueantes.

---

## 4. Alcance del Día 0

**Hacer únicamente:**

1. Inspeccionar la carpeta, Git y archivos existentes, sin destruir trabajo.
2. Preparar `.gitignore` y `.env.example` **sin secretos**.
3. Crear `compose.yaml` con PostgreSQL y un volumen persistente.
4. Validar configuración, arranque, conectividad y que la base no se publique en interfaces externas.
5. Registrar decisiones pendientes; crear commit solo con autorización.

**No hacer todavía:** tablas fiscales, numeración, certificados, credenciales SOL, módulos comerciales, login, colas, integración SUNAT o configuraciones tributarias no aprobadas. Tampoco instalar Laravel, Greenter o herramientas por anticipación.

**Cierre del Día 0:** PostgreSQL local reproducible, repositorio ordenado y evidencia concreta. **No equivale a sistema fiscal funcional ni seguro para producción.**

---

## 5. Estructura mínima sugerida

```text
proyecto/
├── AGENTS.md
├── STACK_INIT.md
├── compose.yaml
├── .env.example
├── .gitignore
└── README.md
```

No crear directorios vacíos para módulos hipotéticos. Cuando se inicialice Laravel, partir de su estructura oficial; modularizar al existir necesidades reales.

### Exclusiones mínimas de Git

Agregar o ampliar `.gitignore` sin sobrescribir exclusiones existentes:

```gitignore
.env
.env.*
!.env.example
**/.env
**/.env.*
!**/.env.example
/vendor/
/node_modules/
/dist/
/build/
/public/build/
*.log
*.p12
*.pfx
*.pem
*.key
*.dump
*.sql.gz
.DS_Store
```

Jamás versionar certificados reales, claves privadas, contraseñas SOL, XML/CDR privados de clientes o respaldos de producción. Para pruebas se permiten **datos sintéticos** debidamente identificados.

---

## 6. `.env.example` inicial (solo desarrollo)

```dotenv
# Copiar a .env; establecer contraseña local antes de arrancar.
POSTGRES_IMAGE=postgres:16-alpine
POSTGRES_DB=emisor_dev
POSTGRES_USER=emisor_dev
POSTGRES_PASSWORD=
POSTGRES_HOST_PORT=5432
```

`postgres:16-alpine` es una referencia configurable del Día 0, no una versión de parche congelada. Revisar vulnerabilidades de la imagen y actualizarla de forma controlada. El `.env` local no debe subirse al repositorio ni reutilizarse como mecanismo definitivo de secretos para producción.

---

## 7. `compose.yaml` mínimo — PostgreSQL local

```yaml
services:
  postgres:
    image: ${POSTGRES_IMAGE:-postgres:16-alpine}
    restart: unless-stopped
    environment:
      POSTGRES_DB: ${POSTGRES_DB:?Definir POSTGRES_DB en .env}
      POSTGRES_USER: ${POSTGRES_USER:?Definir POSTGRES_USER en .env}
      POSTGRES_PASSWORD: ${POSTGRES_PASSWORD:?Definir POSTGRES_PASSWORD en .env}
    ports:
      - "127.0.0.1:${POSTGRES_HOST_PORT:-5432}:5432"
    volumes:
      - postgres_data:/var/lib/postgresql/data
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U \"$$POSTGRES_USER\" -d \"$$POSTGRES_DB\""]
      interval: 5s
      timeout: 5s
      retries: 10

volumes:
  postgres_data:
```

- Publicar el puerto **solo en `127.0.0.1`** para desarrollo local; cuando una app en Compose necesite PostgreSQL, utilizar la red interna del Compose, no publicar la base a internet.
- Volumen persistente: **nunca** ejecutar `docker compose down -v` ni eliminar volúmenes/datos sin autorización expresa.
- `healthcheck` demuestra respuesta básica del servidor, no autenticación válida, consistencia de datos ni posibilidad de restauración.
- Cambiar variables iniciales no actualiza usuarios/contraseñas de un volumen PostgreSQL ya inicializado.
- En producción se deberá diseñar almacenamiento, secretos, backups, restauración, aislamiento, monitoreo y acceso administrativo por separado; **este compose de desarrollo no es un despliegue listo para internet**.

### Verificación del Día 0

1. Copiar `.env.example` a `.env` y escribir una contraseña local no vacía en `POSTGRES_PASSWORD`.
2. Ejecutar `docker compose config` y verificar las variables; **no compartir la salida completa si contiene contraseñas**.
3. Ejecutar `docker compose pull postgres` para obtener actualizaciones revisadas de la imagen, y `docker compose up -d postgres`.
4. Ejecutar `docker compose ps` y revisar estado, puertos publicados y logs sin secretos.
5. Ejecutar `docker compose exec postgres pg_isready -U emisor_dev -d emisor_dev` solo con los valores de ejemplo; de lo contrario, usar los valores configurados.
6. Verificar una consulta de lectura con autenticación real; `pg_isready` por sí solo no comprueba las credenciales.
7. Inspeccionar `git status --short` y comprobar que no se agregaron secretos ni respaldos.
8. Reportar comandos realmente ejecutados, fallos y pendientes sin afirmar verificaciones que no ocurrieron.

---

## 8. Cuando se incorpore PHP/Laravel (fase posterior, no Día 0)

**Punto de control obligatorio antes de exponer PHP por HTTP:**

- Seleccionar una versión **soportada, parcheada y sin regresiones bloqueantes**; comprobar en el contenedor efectivo, no únicamente en el Dockerfile.
- Configurar PHP-FPM y/o runtime elegido de forma segura. Para el aviso del 23/10/2026, establecer `session.upload_progress.enabled=0` en configuración cargada antes de recibir solicitudes y confirmar su valor efectivo; reevaluar cuando exista parche oficial.
- Ejecutar pruebas sintéticas de XML (generación, parseo, serialización, firma y ZIP), pensando en las dependencias y operaciones reales de Greenter; verificar regresiones `SimpleXML` relevantes.
- Mantener PHP y sus workers con privilegios mínimos. No montar el socket Docker en el servidor web ni exponer puertos de depuración. En producción usar TLS, logs sin secretos y directorios privados para certificados/archivos fiscales.
- Proteger acceso y separación entre empresas **del lado servidor**. No equiparar ocultar botones con autorización.
- Establecer límites adecuados para entradas y cargas; validar archivos de certificado por tipo, tamaño, almacenamiento y controles de acceso. Un WAF o límite de solicitudes no reemplaza parches del intérprete.
- Confirmar que PHP de procesos CLI/worker/web usa imágenes y configuración aprobadas; actualizar los contenedores afectados en conjunto.
- Ejecutar pruebas funcionales y de seguridad antes de declarar una imagen apta para despliegue.

---

## 9. Evolución posterior del producto

1. **Backend y modelo:** confirmar requisitos, Laravel/versión y migraciones basadas en entidades aprobadas. Sin `migrate:fresh` ni migraciones destructivas sobre datos reales.
2. **Interfaz:** validar Vue/Inertia tras conocer flujos de emisión, historial, administración y errores. En Beta, avisos discretos pero inequívocos; en producción, sin ruido de depuración y sin ocultar estados fiscales necesarios.
3. **Motor fiscal:** aislar Greenter mediante una interfaz interna pequeña, con datos y archivos sintéticos. No reconstruir UBL/firmas si una librería mantenida lo resuelve.
4. **Envios:** definir numeración atómica, idempotencia, transacciones, outbox persistente y estados inciertos antes de los reintentos. Un timeout de SUNAT **no es un rechazo**.
5. **Servicios auxiliares:** añadir Redis/Horizon, workers, scheduler y almacenamiento privado solo cuando estén asociados a tareas demostrables.
6. **Producción:** plan propio de secretos, respaldos comprobados, recuperación, monitorización, dominio, HTTPS y despliegues reversibles. Distinguir **entorno de aplicación** de **ambiente fiscal SUNAT**.

Registrar decisiones breves y sus motivos en README o en un registro acotado. No producir documentos extensos ni infraestructura por ceremonia.

---

## 10. Criterio de aprobación y reporte

El implementador debe reportar:

- Archivos creados/modificados y el alcance real.
- Estado de Git y comprobación de ausencia de secretos versionados.
- Versiones e imágenes **efectivas** de los componentes que realmente se instalaron.
- Resultado de `docker compose config`, `docker compose ps` y prueba autenticada de PostgreSQL.
- Avisos de seguridad revisados y pendientes de actualización (sin instalar PHP en Día 0).
- Decisiones abiertas: versiones/frameworks, interfaz, colas, certificados, alojamiento y seguridad de producción.

**Aprobación Día 0:** infraestructura local mínima verificada, sin secretos expuestos, sin pérdida de datos y sin decisiones arquitectónicas prematuras. Cada componente incorporado después tendrá su propia aceptación técnica y de seguridad.
