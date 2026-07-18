# Respaldo y recuperación

## Alcance y seguridad

El comando `php artisan system:backup` genera un `tar.gz` fuera de `public` con la base de datos, documentos privados, migraciones y configuración no sensible. Incluye un manifiesto con tamaño y SHA-256 por archivo, además de un checksum SHA-256 externo del archivo comprimido.

No incluye `.env`, contraseñas, logs ni temporales. La contraseña de MySQL no aparece en argumentos: se entrega a `mysqldump` mediante `MYSQL_PWD` en el entorno exclusivo del subproceso. El archivo debe protegerse además con cifrado del volumen o del almacenamiento externo, control de acceso y copia fuera del servidor.

## Configuración

- `BACKUP_DISK`: disco Laravel privado. Por defecto `backup-local`, ubicado en `storage/app/backups`. Puede apuntar a un disco externo configurado por infraestructura.
- `BACKUP_RETENTION_COUNT`: cantidad de respaldos completos conservados. Nunca debe ser menor que uno.
- `MYSQLDUMP_BINARY`: nombre o ruta del ejecutable. No se codifica una ruta de Windows.
- `BACKUP_PROCESS_TIMEOUT`: tiempo máximo del volcado.
- `BACKUP_SCHEDULE_ENABLED` y `BACKUP_SCHEDULE_TIME`: habilitan la programación diaria. El servidor debe ejecutar `php artisan schedule:run` cada minuto mediante cron o el programador equivalente.

Antes de producción, el responsable debe comprobar permisos del directorio/disco, espacio libre, cifrado en reposo, alertas y una copia externa o inmutable.

## MySQL/MariaDB

El servidor necesita una versión compatible de `mysqldump` accesible por `MYSQLDUMP_BINARY`. La cuenta debe tener permisos mínimos de lectura necesarios. El comando usa argumentos separados, `--single-transaction`, `--quick`, `--skip-lock-tables` y `--no-tablespaces`.

Si `mysqldump` no está disponible, no se crea un respaldo parcial: el proceso falla y queda registrado. La alternativa autorizable es un snapshot consistente administrado por la plataforma de base de datos o una réplica respaldada por infraestructura. Ese archivo debe incorporarse después al mismo control de checksums y recuperación; no se recomienda exportar tablas desde peticiones web.

## Operación

```text
php artisan system:backup --dry-run
php artisan system:backup
php artisan system:backup --verify=mesa-partes-production-AAAAMMDD-HHMMSS.tar.gz
php artisan system:backup --restore-temp=mesa-partes-production-AAAAMMDD-HHMMSS.tar.gz
```

`--dry-run` no crea archivos ni registros. `--verify` compara el checksum externo, extrae en un directorio temporal aislado y valida cada archivo contra el manifiesto. `--restore-temp` hace la misma validación y conserva el contenido en `storage/app/backup-restore`; no modifica la base ni los documentos activos.

Cada ejecución real registra fecha, tipo, tamaño, checksum, responsable, resultado, error sanitizado y ubicación lógica en `backup_logs`.

## Procedimiento de recuperación

1. Declarar la incidencia, identificar el punto de recuperación y obtener autorización explícita del responsable institucional.
2. Copiar el respaldo seleccionado a un entorno aislado y ejecutar `--verify`.
3. Ejecutar `--restore-temp` y revisar el manifiesto, el dump y los documentos extraídos.
4. Crear una base de datos nueva y aislada. Para MySQL, importar manualmente `database/database.sql` usando credenciales administradas fuera del repositorio. Para SQLite, usar una copia del archivo extraído.
5. Validar migraciones, conteos, relaciones, códigos de seguimiento, checksums documentales y acceso privado en el entorno aislado.
6. Probar inicio de sesión, permisos, descargas y flujos críticos con cuentas ficticias o controladas.
7. Programar ventana de mantenimiento y tomar un respaldo final del estado que será reemplazado.
8. Solo con una segunda confirmación explícita, infraestructura reemplaza la base y documentos. La aplicación no automatiza este paso.
9. Ejecutar pruebas de humo, registrar responsables, horarios, checksums y resultado; conservar evidencia de la recuperación.

Nunca restaurar directamente sobre producción desde el comando Artisan. Nunca copiar `.env` dentro del respaldo ni publicar el archivo comprimido.
