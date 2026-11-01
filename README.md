# Gestión de Incidencias — Clínica UGA

Aplicación administrativa para el registro y control de las incidencias relacionadas con el área de informática en la Clínica UGA, C.A.

## Inicio

1. Inicia Apache y MySQL desde el panel de XAMPP.

2. Copia `config/local.example.php` como `config/local.php` si la conexión difiere de los valores locales predeterminados. Ajusta allí host, puerto, nombre de la base, usuario y contraseña. También se aceptan `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER` y `DB_PASSWORD` como variables de entorno.

3. Abre `http://localhost:8080/gestion-incidencias/`. La primera visita presenta el formulario para crear el primer administrador; utiliza una contraseña de al menos 12 caracteres. Después de crear esa cuenta, el formulario de configuración queda bloqueado.

4. Para subir archivos, PHP debe tener habilitadas las extensiones `fileinfo` y `pdo_mysql`; Apache debe permitir `.htaccess`. Los adjuntos se guardan bajo `storage/uploads` y se descargan únicamente tras comprobar permisos.

## Módulos

- Inicio de sesión:
  - Inicio de sesión con correo y contraseña, además de un sistema de restablecimiento de contraseñas por parte del usuario `Administrador`.

- Incidencias:
  - Listado de incidencias con buscador, filtros combinables y paginación de 15 elementos.
  - Creación de Incidencias con posibilidad de adjuntar hasta 5 archivos (5 MB por archivo).

- Reportes y Métricas:
  - Filtro por periodo de creación.
  - Gráficos para el rendimiento según estado (torta), prioridad (torta), top categorías (barras) y tiempo promediode resolución.
  - Posibilidad de descarga de los gráficos en formato PDF, y de los registros en formato Excel.

- Gestión de Usuarios:

## Permisología

### Módulo de Incidencias

- Administrador:
  - Puede ver y gestionar todas las incidencias

- Técnico:
  - Puede ver y gestionar las incidencias que tenga asignado

- Usuario:
  - Puede ver, editar y asignar un técnico a sus propias incidencias

### Módulo de Reportes e Incidencias

Solo disponible para el usuario `Administrador`.

### Módulo de Gestión de Usuarios

Solo disponible para el usuario `Administrador`.
