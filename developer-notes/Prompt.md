# Gestión de Incidencias

## Descripción del Proyecto

La aplicación es para la Clínica UGA, Valera, Venezuela. Es una aplicación administrativa para el registro de incidencias con las computadoras, impresoras, routers, etc. Basicamente, un usuario registra un problema y el tecnico de sistemas lo atiende y responde a la incidencia.

## Layout

- Header
- Menú lateral izquierdo (ocultable)
- contenido

## Paleta de colores

- Azul marino, blanco y grises claros

## Arquitectura

- Modelo Vista Controlador (MVC)
- Programación Orientada a Objetos (POO)

## Tecnologías

- HTML, CSS, JavaScript, PHP y MySQL.
- Chart.js para las gráficas.

## Consideraciones

- La aplicación tiene que hacerse lo más simple posible
- Las gráficas de Chart.js deben usarse a través de su CDN, para no tener que instalar dependencias.
- La base de datos ya está creada y lanzada. Se está utilizando Xampp para el backend y la base de datos

## Módulos de la Aplicación

1. Login
   - Usuario y Contraseña (con botón para aparecer y ocultar la contraseña)
   - Sistema de: ¿Olvidaste tu contraseña?
   - El login es lo primero que debe aparecer al entrar en la aplicación.
   - Los usuarios no pueden crearse una cuenta, eso lo realiza es el administrador a través de Gestión de Usuarios

2. Home

   Como hay diferentes roles (Usuario, Tecnico y Administrador). La sección de home cambia en base al tipo de usuario.
   - Usuario: Debe tener 3 opciones, Nueva incidencia, Mis incidencias (es el módulo de incidencias, pero puede ver unicamente las de ese usuario).

   - Tecnico: Debe tener 1 opcion: Incidencias (donde podrá ver las incidencias que tenga asignadas).

   - Administrador: Debe 3 opciones: Incidencias (donde podrá verlas todas y gestionar todas), Gestión de Usuarios, Reportes y Métricas

3. Nueva Incidencia (aquí es de donde se crean las incidencias):
   - Titulo, Categoria (select), Prioridad (input de tipo radio, para baja, media, alta y critica), Descripcion (textarea) y Adjuntar Archivo(s).

   - Debe tener un botón de Guardar y Cancelar

4. Incidencias:

   Aquí se listan todas las incidencias, en esencia es un section, con un panel de control, una tabla y un footer:
   - En el panel de control:
     - Botón de Nueva Incidencia (redirige al formulario de Nueva Incidencia)
     - Buscador (busca por el título de la incidencia)
     - Filtrar (4 filtros a la vez: Estado, Prioridad, Categoría, Fecha)

   - En la tabla:
     - Columnas ID, Título, Estado, Prioridad, Fecha y Acción (Editar y Eliminar)

   - En el footer:
     - Paginado (botón de Anterior y Siguiente), máximo 15 registros a la vez, y debe decir el total de registros, o sea: 1-15 de 67, 16 a 30 de 67, etc.

5. Reporte y Estadística
   - Incidencias por estado (gráfico de )
   - Incidencias por prioridad (gráfico de torta)

   - Tiempo promedio de resolución (no sé que tipo de grafico podria servir, tu elige)
   - Top categorías más reportadas (gráfico de barras)

6. Gestión de Usuarios (Módulo solo disponible para el usuario Administrador)
   - Botón de Nuevo Usuario (redirige al formulario de Nuevo Usuario)
   - Tabla con 5 columnas: ID, Nombre, Email, Rol y Acción (Editar y Eliminar)

## Permisología

- Rol Usuario:
  - Puede ver sus incidencias y crear incidencias

- Rol Administrador:
  - Puede ver todas las incidencias y crear sus incidencias
  - Puede gestionar usuarios
  - Puede ver el módulo de Reporte e incidencias

- Rol Técnico:
  - Puede ver sus incidencias y gestionarlas (o sea colocar la solución)
