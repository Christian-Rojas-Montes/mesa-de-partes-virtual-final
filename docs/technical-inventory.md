# Inventario técnico de la versión candidata

Inventario generado desde el repositorio. Para obtener el detalle actualizado de rutas ejecute `php artisan route:list`.

## Rutas

Hay 151 rutas registradas. Los grupos principales son: `/`, autenticación y recuperación; `/tramites`; `/panel` (116 rutas administrativas, de solicitante, atención, catálogo, titulación y configuración); `/expedientes`; `/consulta-expedientes`; `/notificaciones`; `/reportes`; comprobación de salud y rutas controladas de almacenamiento.

## Modelos y tablas

Los 43 modelos se agrupan en usuarios/roles/áreas, catálogo configurable, solicitudes y documentos, historial/atención, recepción/citas/recojo, auditoría/respaldo, convalidaciones mediante catálogo y seguimiento, y titulación (`Title*`, `ApplicationWork*`, `ProfessionalExam*`). El inventario de tablas está detallado en [database.md](database.md).

## Servicios

Existen 31 servicios. Cubren autenticación, administración de usuarios y catálogo, condiciones y formularios dinámicos, catálogo público, registro/transición/consulta/atención/derivación de solicitudes, almacenamiento y descarga privada, recepción física, notificación/comunicación, historial, convalidación, titulación y sus modalidades, auditoría, respaldo y preparación de despliegue.

## Jobs y comandos

No hay clases propias en `app/Jobs`. Las tablas de cola estándar están disponibles. Comandos propios:

- `catalog:sync-institutional`: sincronización institucional controlada.
- `system:backup`: respaldo, simulación y verificación según opciones.
- `deployment:check`: validación segura de preparación del entorno.

## Seeders

`RoleSeeder`, `AreaSeeder`, `StatusSeeder`, `ProcedureTypeSeeder`, `DevelopmentUserSeeder`, `DatabaseSeeder` e `InstitutionalCatalogSeeder`.

## Pruebas

Hay 32 archivos bajo `tests/`: autenticación, catálogos, base de datos, paneles, administración, registro dinámico, modalidades, seguimiento, atención, derivación, recepción presencial, historial, notificaciones, flujo completo, reportes, convalidaciones, titulación, seguridad documental, PWA, respaldo y despliegue. `tests/TestCase.php` proporciona la base común.
