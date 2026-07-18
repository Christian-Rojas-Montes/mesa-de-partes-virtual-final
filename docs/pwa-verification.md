# Comprobación de la PWA en Chrome

## Preparación local

1. Ejecuta `npm run build`.
2. Inicia la aplicación con `php artisan serve`.
3. Abre `http://127.0.0.1:8000`. Chrome permite service workers en `localhost` y `127.0.0.1`. En producción debe utilizarse HTTPS.
4. Si se modificó el service worker, abre DevTools, entra en **Application > Storage**, selecciona **Clear site data** y recarga la página.

## Manifest e instalación

1. Abre DevTools con `F12`.
2. Entra en **Application > Manifest**.
3. Comprueba:
   - nombre `Sistema Web de Mesa de Partes Virtual`;
   - nombre corto `Mesa de Partes`;
   - `start_url` y `scope` con valor `/`;
   - modo `standalone`;
   - color de tema `#16324f`;
   - iconos normal y adaptable sin errores.
4. Espera el botón **Instalar** de la interfaz o utiliza el icono de instalación de la barra de direcciones de Chrome.
5. Confirma que la aplicación se abre en una ventana independiente. Para repetir la prueba, desinstálala y limpia los datos del sitio.

El botón interno solo aparece cuando Chrome emite el evento `beforeinstallprompt`. Puede no aparecer si la aplicación ya está instalada, si se descartó recientemente o si no se cumplen los criterios de instalación.

## Service worker y funcionamiento sin conexión

1. En DevTools, abre **Application > Service workers**.
2. Comprueba que `/sw.js` figure como **activated and running** y que su ámbito sea `/`.
3. En **Application > Cache storage**, verifica que `mpv-static-v1` contenga solamente:
   - página informativa offline;
   - manifest;
   - iconos;
   - recursos compilados ubicados en `/build/assets/` cuando hayan sido solicitados.
4. Activa **Offline** en el panel del service worker o selecciona **Network > Offline**.
5. Navega o recarga una página. Debe aparecer **Sin conexión a Internet** y explicar que los trámites requieren conexión.
6. Confirma que no es posible enviar el formulario de una solicitud sin conexión y que no se guarda para un envío posterior.
7. Revisa Cache Storage y confirma que no aparecen rutas de panel, documentos, respuestas, notificaciones ni expedientes privados.

## Diseño adaptable

Usa **Toggle device toolbar** en DevTools y comprueba, como mínimo:

| Vista | Tamaño sugerido | Comprobaciones |
| --- | --- | --- |
| Escritorio | 1440 × 900 | Menú lateral visible, tablas dentro del contenido y barra superior completa. |
| Tablet | 768 × 1024 | Menú lateral convertido en offcanvas, tarjetas en columnas adaptadas y formularios legibles. |
| Teléfono | 390 × 844 | Barra superior sin desbordamiento, tarjetas con relleno reducido, botones envueltos y tablas con desplazamiento horizontal. |

En las tres vistas comprueba navegación con teclado, foco visible, etiquetas de formularios y que el aviso de instalación no cubra acciones esenciales.
