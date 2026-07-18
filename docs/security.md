# Seguridad

## Controles vigentes

- Autenticación de sesión, control de usuarios activos, roles, middleware y Policies.
- Protección CSRF, escape de Blade, validación con Form Requests y consultas Eloquent parametrizadas.
- Protección contra IDOR mediante autorización del recurso y alcance por rol/área.
- Campos dinámicos y condiciones limitados a tipos y reglas permitidos; no se usa `eval`.
- Archivos validados por MIME y tamaño, nombre interno controlado, checksum y almacenamiento privado.
- Descargas sensibles autorizadas, auditadas y con cabeceras `no-store`/`noindex` y nombre seguro.
- Exportaciones filtradas por permisos, con datos personales minimizados y auditoría.
- Correos y notificaciones sin adjuntos ni contenido sensible innecesario.
- PWA limitada a recursos estáticos públicos; no cachea paneles, expedientes, documentos o APIs privadas.

## Operación

Mantener `APP_DEBUG=false`, HTTPS, cookies seguras, logs restringidos y secretos únicamente en variables de entorno. Ejecutar periódicamente `composer audit` y `npm audit`; no aplicar correcciones forzadas sin evaluación. La propuesta de retención está en [document-retention-proposal.md](document-retention-proposal.md) y no autoriza borrado automático.

Los incidentes deben documentarse por canal interno sin incluir credenciales ni documentos personales.
