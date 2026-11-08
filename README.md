# Gestión de Incidencias — Clínica UGA

Aplicación administrativa para el registro y control de las incidencias relacionadas con el área de informática en la Clínica UGA, C.A.

## Inicio

1. Inicia Apache y MySQL desde el panel de XAMPP.

2. Copia la carpeta del proyecto dentro de `htdocs` (por ejemplo, `C:\xampp\htdocs\gestion-incidencias`).

3. Abre `http://localhost/phpmyadmin`, selecciona **Importar** y carga el archivo `db/schema.sql`. Esto crea la base de datos `gestion_incidencias` y sus tablas. Hazlo solo la primera vez.

4. Copia `config/local.example.php` y cambia el nombre de esa copia a `local.php`, obteniendo: `config/local.php`. Abre dicho `config/local.php` y configura el host, puerto, nombre de la base de datos, usuario y contraseña de MySQL. Para XAMPP, los valores del ejemplo suelen funcionar sin cambios.

   También puedes configurar la conexión con las variables de entorno `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER` y `DB_PASSWORD`. Si existe `config/local.php`, sus valores tienen prioridad sobre esas variables; elige uno de los dos métodos.

5. Visita `http://localhost/gestion-incidencias/`. Si todavía no hay un administrador registrado, aparecerá un formulario para crear la primera cuenta. Después de crearla, ya no se podrá volver a usar ese formulario.

## Módulos

1. Inicio de Sesión
   - Sistema de inicio de sesión con correo y contraseña, además de un sistema de restablecimiento de contraseñas por parte del usuario `Administrador`.

2. Sistema de Notificaciones
   - Campana en el encabezado con contador de notificaciones pendientes, listado reciente y opción para marcar todas como leídas.
   - Avisos visibles en pantalla donde cada uno permite abrir la incidencia.

3. Incidencias
   - Listado de incidencias con buscador, filtros combinables y paginación de 15 elementos.
   - Creación de Incidencias con posibilidad de adjuntar hasta 5 archivos (5 MB por archivo). Los archivos se guardan en `storage/uploads`.
   - Comentarios públicos para participantes de la incidencia y notas internas visibles únicamente para técnicos.

4. Reportes y Métricas
   - Filtrado por periodo de creación.
   - Gráficos para el rendimiento según estado (torta), prioridad (torta), top categorías (barras) y tiempo promediode resolución.
   - Posibilidad de descarga de los gráficos en formato PDF, y de los registros en formato Excel.

5. Gestión de Usuarios
   - Panel para registrar y editar los usuarios que acceden a la aplicación.

6. Auditoría
   - Módulo de solo lectura disponible únicamente para el Administrador, con hasta 50 movimientos por página.
   - Registra la actividad de incidencias, comentarios, usuarios e inicios/cierres de sesión.
   - Conserva actor, fecha y entidad aunque una incidencia sea eliminada; no guarda contraseñas ni el contenido de comentarios, descripciones o soluciones.

## Permisología

1. Módulo de Incidencias
   - Administrador:
     - Puede ver y gestionar todas las incidencias.
     - Puede cambiar el estado de una incidencia (ABIERTA, EN PROGRESO, RESUELTA, CERRADA y RECHAZADA).

   - Técnico:
     - Puede ver y gestionar las incidencias que tenga asignado.
     - Puede cambiar el estado de una incidencia (RESUELTA y RECHAZADA)

   - Usuario:
     - Puede ver y editar sus propias incidencias

2. Módulo de Reportes e Incidencias

   Solo disponible para el usuario `Administrador`.

3. Módulo de Gestión de Usuarios

   Solo disponible para el usuario `Administrador`.

4. Sistema de Notificaciones
   - Se notifica al `Técnico` cuando se le asigna una incidencia.
   - Se notifica al `Usuario` cuando el estado de su incidencia cambia.
   - Se notifica al `Administrador` cuando el estado de cualquier incidencia cambia.
