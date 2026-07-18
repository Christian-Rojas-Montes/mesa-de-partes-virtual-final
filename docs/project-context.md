# Contexto del proyecto

## Nombre

Sistema Web de Mesa de Partes Virtual para el IESTP Pedro P. Díaz.

## Objetivo

Desarrollar un prototipo web que facilite el registro, revisión, derivación, atención y seguimiento de trámites documentarios, conservando la trazabilidad de las operaciones.

## Tecnologías

- Laravel y PHP para el backend.
- MySQL como base de datos propia del prototipo.
- Arquitectura Modelo-Vista-Controlador.
- Blade y Bootstrap 5 para las vistas.
- JavaScript y Vite para los recursos del frontend.
- PHPUnit para pruebas automatizadas.
- Git para control de versiones.

## Roles

- **Solicitante:** registra solicitudes, adjunta documentos, consulta estados y descarga respuestas.
- **Personal de Mesa de Partes:** revisa expedientes, formula observaciones, valida documentos y realiza derivaciones.
- **Responsable de área:** atiende los trámites derivados, registra acciones y adjunta respuestas.
- **Administrador:** gestiona usuarios, roles, áreas, tipos de trámite, reportes y parámetros del sistema.

## Estados del trámite

1. **Registrado:** la solicitud fue enviada correctamente.
2. **En revisión:** la documentación está siendo verificada.
3. **Observado:** existen requisitos que deben ser corregidos.
4. **Derivado:** el expediente fue enviado al área competente.
5. **En atención:** el área responsable está procesando el trámite.
6. **Atendido:** la respuesta fue registrada.
7. **Rechazado:** el trámite no procede y se consignó la justificación.
8. **Finalizado:** Mesa de Partes verificó la respuesta y cerró formalmente el expediente.

## Módulos previstos

- Autenticación, usuarios, roles y permisos.
- Áreas, tipos de trámite, requisitos y estados.
- Solicitudes y documentos.
- Revisión, observaciones y derivaciones.
- Atención y respuestas.
- Seguimiento, historial y notificaciones internas.
- Administración, búsquedas, reportes y estadísticas.
- Auditoría y configuración.
- Características básicas de PWA.

## Reglas de archivos

- Los documentos de los expedientes son privados y no deben almacenarse en una ruta pública.
- La carga debe validarse en el servidor según el tipo, tamaño y requisito definidos para cada trámite.
- Las descargas deben realizarse mediante una ruta controlada y una autorización previa.
- Los nombres almacenados deben evitar colisiones y no deben confiar en el nombre proporcionado por el usuario.
- Los documentos usados durante desarrollo y pruebas deben contener solamente datos ficticios.

## Restricciones del alcance

- No integrar bases de datos académicas institucionales.
- No validar automáticamente matrículas o notas.
- No incorporar firma digital certificada, pagos en línea ni WhatsApp.
- No integrar plataformas externas sin una aprobación posterior.
- No ofrecer registro completo de solicitudes sin conexión.
- No utilizar información ni documentos personales reales durante el desarrollo.
- El sistema es un prototipo académico y no una implementación institucional permanente.
