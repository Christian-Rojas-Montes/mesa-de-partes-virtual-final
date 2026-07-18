# Reglas permanentes del proyecto

Estas instrucciones se aplican a todo el repositorio del Sistema Web de Mesa de Partes Virtual para el Instituto de Educación Superior Tecnológico Público "Pedro P. Díaz".

## Reglas de trabajo

1. Trabajar sobre el sistema existente.
2. Inspeccionar antes de modificar.
3. Aplicar cambios incrementales y mínimos.
4. No eliminar funcionalidades existentes.
5. No reemplazar carpetas completas.
6. No recrear Laravel.
7. No actualizar PHP, Laravel, Composer, Node.js o dependencias sin justificarlo.
8. No modificar `vendor` ni `node_modules` manualmente.
9. No editar migraciones antiguas ya utilizadas.
10. Crear migraciones nuevas para cambios en la base de datos.
11. No ejecutar `migrate:fresh` en la base funcional.
12. No borrar datos existentes.
13. Utilizar transacciones en operaciones documentarias críticas.
14. Mantener historial y auditoría.
15. Utilizar Form Requests.
16. Utilizar Policies o middleware.
17. Mantener controladores breves.
18. Mantener la lógica de negocio en servicios.
19. No exponer documentos privados.
20. No almacenar documentos sensibles en `public`.
21. No mostrar datos médicos o personales en reportes generales.
22. No escribir rutas absolutas de Windows.
23. Mantener compatibilidad con Linux.
24. Mantener compatibilidad con MySQL.
25. No escribir `localhost` ni el dominio definitivo dentro del código.
26. Utilizar `route()`, `asset()`, `url()`, `config()` y `Storage`.
27. Mantener la configuración en `.env` y archivos `config`.
28. No publicar secretos.
29. Mantener compatibilidad con HTTPS.
30. No almacenar paneles privados en caché PWA.
31. Ejecutar pruebas después de cada cambio.
32. Ejecutar `npm run build` cuando se modifique frontend.
33. No hacer commits automáticamente.
34. Informar todos los archivos modificados.
35. Detenerse al terminar la fase solicitada.
36. No actualizar el documento EFSRT durante el desarrollo.
37. Separar los cambios funcionales de los cambios estéticos.

## Reglas complementarias existentes

- Trabajar solamente en la fase solicitada y no avanzar automáticamente a otra fase.
- No introducir dependencias sin justificar su necesidad y compatibilidad.
- No usar información personal real; emplear solamente datos ficticios durante el desarrollo y las pruebas.
- Utilizar Blade y Bootstrap 5 para la interfaz. No incorporar React, Vue ni Tailwind.
- Mantener la arquitectura MVC, nombres descriptivos y convenciones oficiales de Laravel.
- No integrar bases de datos institucionales ni servicios externos fuera del alcance aprobado.
- No crear migraciones, modelos o módulos funcionales sin que la fase correspondiente haya sido solicitada.
- Comunicar cualquier prueba que no pueda ejecutarse.

## Fuentes funcionales válidas

La información funcional se resolverá según esta jerarquía, de mayor a menor autoridad:

1. Información confirmada por personal responsable del instituto.
2. Documentos oficiales institucionales vigentes.
3. Página institucional de trámites.
4. Documento oficial de Convalidaciones.
5. Reglamento de Titulación 2022.
6. Fotografías antiguas del tablero: descartadas.
7. Fotografías mejoradas mediante IA: descartadas.

Las fuentes descartadas no deben utilizarse para definir requisitos, montos, fechas, modalidades ni reglas de negocio.
