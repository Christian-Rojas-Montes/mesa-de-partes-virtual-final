# Sistema Web de Mesa de Partes Virtual

Versión candidata del sistema documentario del Instituto de Educación Superior Tecnológico Público “Pedro P. Díaz”. Permite publicar trámites, registrar solicitudes digitales, híbridas y presenciales, realizar seguimiento, gestionar recepción física, notificaciones, citas, convalidaciones y procesos documentarios de titulación.

## Tecnologías y requisitos

- PHP 8.2 o superior y extensiones requeridas por Laravel.
- Laravel 12, Blade y Bootstrap 5.
- MySQL/MariaDB; SQLite se utiliza en pruebas cuando corresponde.
- Composer 2, Node.js compatible con Vite 6 y npm.
- Servidor web cuyo document root apunte a `public/`.

## Instalación y configuración local

```bash
composer install
npm ci
cp .env.example .env
php artisan key:generate
```

Configure en `.env` la URL, base de datos, correo, colas, almacenamiento y opciones de respaldo. No copie secretos al repositorio. Luego ejecute:

```bash
php artisan migrate
php artisan db:seed
php artisan storage:link
php artisan serve
```

Los seeders crean catálogos base y cuentas ficticias únicamente en desarrollo. Sus identificadores están descritos en [docs/development-users.md](docs/development-users.md); las claves se administran fuera de la documentación y deben renovarse antes de cualquier despliegue.

## Ejecución, pruebas y build

```bash
composer run dev
php artisan test
npm run dev
npm run build
```

## Roles y módulos

Los roles base son Solicitante, Mesa de Partes, Responsable de área y Administrador. El acceso se controla en servidor mediante middleware y Policies. Los módulos abarcan catálogo configurable y público, solicitudes, recepción física, seguimiento, atención y derivación, notificaciones y citas, reportes, convalidaciones, titulación, seguridad documental, PWA y respaldos.

Consulte la [arquitectura](docs/architecture.md), el [flujo de solicitudes](docs/request-workflow.md), los [roles y permisos](docs/roles-and-permissions.md) y el [inventario técnico](docs/technical-inventory.md).

## PWA, respaldos y despliegue

La PWA cachea exclusivamente recursos públicos seguros y no permite enviar solicitudes sin conexión. Consulte [verificación PWA](docs/pwa-verification.md), [respaldo y recuperación](docs/backup-and-recovery.md) y [despliegue](docs/deployment.md).

## Seguridad

Los documentos se almacenan fuera de `public`, las descargas pasan por autorización y se auditan. Nunca se deben versionar `.env`, credenciales, datos personales ni documentos de usuarios. Consulte [docs/security.md](docs/security.md).

## Documentación

Los manuales de [usuario](docs/user-manual.md) y [administración](docs/admin-manual.md), junto con el resto de documentos de `docs/`, describen la versión candidata. El documento académico EFSRT no forma parte de esta actualización.

## Licencia

El código declara licencia MIT en `composer.json`. Los nombres, contenidos y recursos institucionales conservan las condiciones que determine su titular.
