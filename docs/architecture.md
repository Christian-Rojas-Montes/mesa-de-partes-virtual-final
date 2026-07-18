# Arquitectura

La aplicación es un monolito Laravel 12 con interfaz Blade y Bootstrap 5. Las rutas reciben solicitudes HTTP, los Form Requests validan entradas, middleware y Policies autorizan acciones, los controladores coordinan casos de uso y los servicios contienen la lógica documentaria. Eloquent persiste datos en MySQL y las vistas presentan resultados sin exponer detalles internos.

## Capas

- Presentación: Blade, componentes reutilizables, CSS/JavaScript compilado con Vite y PWA pública.
- Aplicación: controladores breves, servicios de catálogo, registro, atención, comunicaciones, reportes, titulación, respaldo y seguridad documental.
- Dominio y persistencia: modelos Eloquent, transacciones para operaciones críticas, snapshots e historial inmutable.
- Seguridad: autenticación de sesión, roles, Policies, CSRF, validación, almacenamiento privado y auditoría.

## Integraciones internas

Las notificaciones internas son obligatorias; el correo y las colas son configurables. El scheduler puede ejecutar respaldos. No existen Jobs propios en esta versión: Laravel gestiona las notificaciones en el modo configurado.

## Decisiones

No se ejecuta código almacenado en base de datos. Campos y condiciones dinámicas se procesan mediante listas blancas. Los estados globales se conservan y los procesos especializados usan eventos, hitos o etapas. Los documentos privados nunca se sirven directamente desde `public/`.
