# Preparación para despliegue

Este documento prepara el sistema para un alojamiento Laravel con dominio y HTTPS, sin depender de un proveedor. No autoriza ni ejecuta un despliegue.

## Requisitos

- Linux recomendado, PHP 8.2 o superior y Composer 2.
- Extensiones PHP: Ctype, cURL, DOM/XML, Fileinfo, Filter, Hash, JSON, Mbstring, OpenSSL, PDO MySQL, Session, Tokenizer, Phar y Zlib.
- MySQL 8 o MariaDB compatible, con `utf8mb4`, zona horaria coherente y usuario de privilegios mínimos.
- Node.js compatible con `package-lock.json` y npm para compilar Vite. Node no es necesario durante la ejecución si los assets ya fueron compilados.
- `mysqldump` compatible para respaldos MySQL/MariaDB.
- Worker de colas, cron/scheduler, SMTP y almacenamiento persistente fuera de `public`.
- PHP configurado con `upload_max_filesize = 5M`, `post_max_size = 32M` y `max_file_uploads = 5`, en concordancia con la validación global del sistema. `public/.user.ini` aplica estos valores en alojamientos CGI/FastCGI compatibles; en otros servidores deben configurarse en su `php.ini` o pool PHP-FPM.

Ejecutar antes de preparar una entrega: `composer check-platform-reqs --no-dev` y `php artisan deployment:check --health`.

## Estructura y permisos

El document root del dominio debe apuntar exclusivamente a `public/`, nunca a la raíz del repositorio. El usuario del servidor web necesita lectura del código y escritura solamente en `storage/` y `bootstrap/cache/`. Los documentos privados viven en el disco `private`; los respaldos en `BACKUP_DISK`. Ambos deben persistir entre versiones y no deben exponerse con enlaces públicos.

No usar rutas absolutas del equipo de desarrollo. Configurar discos Laravel o montajes persistentes del proveedor. La carpeta `public/storage` solo corresponde a archivos expresamente públicos y no debe apuntar a documentos de expedientes.

## Variables

Copiar `.env.example` como `.env` únicamente en el servidor y completar valores mediante el gestor de secretos. Nunca versionarlo ni incluirlo en respaldos.

Obligatorias: `APP_KEY`, `APP_URL`, conexión de base, `MAIL_FROM_ADDRESS`, sesión, caché, cola, discos privado/respaldo y correo cuando esté habilitado. En producción: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://...`, `SESSION_SECURE_COOKIE=true`, `SESSION_HTTP_ONLY=true` y `SESSION_SAME_SITE=lax`.

`APP_URL` contiene el dominio asignado; el código no contiene dominios fijos. Configurar `SESSION_DOMAIN` solo si se necesita compartir sesión entre subdominios. No confiar proxies globalmente: configurar proxies confiables únicamente cuando el balanceador real y sus rangos estén confirmados. El proxy debe enviar correctamente `X-Forwarded-Proto` para generar URLs HTTPS.

## Preparación y build

En un directorio de nueva versión, con un `.env` protegido:

```text
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan deployment:check
```

El build genera `public/build/manifest.json`. Verificar que manifest PWA, iconos, service worker y assets compilados sean accesibles por HTTPS. Al cambiar recursos PWA, incrementar la versión de caché del service worker.

## Base de datos y activación

Antes de migrar, crear y verificar un respaldo. Revisar `php artisan migrate --pretend` en un entorno equivalente. Durante una ventana autorizada, el comando de referencia es `php artisan migrate --force`; **no ejecutarlo automáticamente ni desde esta preparación**. Después, el comando de referencia `php artisan optimize` crea cachés de producción.

Probar una cuenta controlada, permisos, registro, descargas privadas, correo y health check antes de abrir tráfico.

## Colas, scheduler, sesiones y caché

Ejecutar workers supervisados y reiniciarlos de forma ordenada al activar una versión. Para cola de base de datos, conservar las tablas `jobs` y `failed_jobs`. Definir reintentos, timeout y monitoreo.

Configurar cron para ejecutar cada minuto:

```text
* * * * * cd /ruta/de/la-aplicacion && php artisan schedule:run >> /dev/null 2>&1
```

Usar sesiones y caché persistentes (`database` o Redis). No usar `array`, `cookie` como sesión principal ni `null` en producción. Mantener logs rotados, fuera de `public`, con permisos restringidos y sin secretos.

## Correo

Configurar SMTP o un transporte compatible mediante variables, TLS, remitente institucional y credenciales secretas. Probar entrega, colas y enlaces HTTPS. Los correos no deben adjuntar documentos sensibles.

## HTTPS, dominio y PWA

Crear DNS hacia la infraestructura autorizada, emitir un certificado válido, redirigir HTTP a HTTPS y habilitar HSTS solo después de confirmar que todo el dominio funciona exclusivamente por HTTPS. Mantener cookies Secure/HttpOnly y contenido mixto en cero.

El service worker solo puede cachear offline, iconos y assets versionados. Nunca paneles, APIs, expedientes, documentos ni respuestas. Comprobar instalación y actualización en un perfil limpio del navegador.

## Health check y backups

`GET /comprobacion` devuelve únicamente estados booleanos de aplicación, base, storage y cola, con HTTP 200 o 503; no revela configuración. Aplicar monitoreo con frecuencia moderada. `/up` sigue disponible como comprobación mínima del framework.

Programar `php artisan system:backup`, copiar respaldos a almacenamiento externo cifrado y probar periódicamente `--verify` y `--restore-temp`. Consultar `docs/backup-and-recovery.md`.

## Rollback

1. No borrar la versión anterior al activar la nueva.
2. Si falla la aplicación, retirar tráfico o activar mantenimiento, volver el enlace/directorio de release al código anterior y reiniciar workers.
3. Ejecutar `php artisan optimize:clear` y regenerar cachés con la configuración anterior.
4. No revertir migraciones destructivamente por rutina. Si el esquema nuevo es incompatible, restaurar en un entorno aislado el respaldo verificado y obtener aprobación explícita antes de sustituir producción.
5. Validar health check, permisos, documentos, colas y flujos críticos; documentar la incidencia y los checksums utilizados.
