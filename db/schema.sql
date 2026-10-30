-- =====================================================
-- BASE DE DATOS: gestion_incidencias
-- =====================================================
CREATE DATABASE IF NOT EXISTS gestion_incidencias
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE gestion_incidencias;

-- =====================================================
-- 1. TABLAS CATÁLOGO
-- =====================================================

CREATE TABLE rol (
    id_rol        INT AUTO_INCREMENT PRIMARY KEY,
    nombre        VARCHAR(30) NOT NULL UNIQUE,
    descripcion   VARCHAR(150)
);

CREATE TABLE departamento (
    id_departamento INT AUTO_INCREMENT PRIMARY KEY,
    nombre          VARCHAR(100) NOT NULL UNIQUE,
    activo          BOOLEAN NOT NULL DEFAULT TRUE
);

CREATE TABLE estado (
    id_estado   INT AUTO_INCREMENT PRIMARY KEY,
    nombre      VARCHAR(30) NOT NULL UNIQUE,   -- ABIERTA, EN_PROGRESO, RESUELTA, CERRADA, RECHAZADA
    color       VARCHAR(7),                     -- #RRGGBB para UI
    es_final    BOOLEAN NOT NULL DEFAULT FALSE  -- indica si ya no admite cambios
);

CREATE TABLE prioridad (
    id_prioridad INT AUTO_INCREMENT PRIMARY KEY,
    nombre       VARCHAR(20) NOT NULL UNIQUE,  -- BAJA, MEDIA, ALTA, CRITICA
    nivel        INT NOT NULL,                 -- 4=BAJA ... 1=CRITICA (orden)
    color        VARCHAR(7),
    horas_sla    INT                           -- horas objetivo de resolución
);

CREATE TABLE categoria (
    id_categoria INT AUTO_INCREMENT PRIMARY KEY,
    nombre       VARCHAR(80) NOT NULL UNIQUE,
    descripcion  VARCHAR(200),
    activo       BOOLEAN NOT NULL DEFAULT TRUE
);

-- =====================================================
-- 2. USUARIOS
-- =====================================================

CREATE TABLE usuario (
    id_usuario       INT AUTO_INCREMENT PRIMARY KEY,
    nombre           VARCHAR(100) NOT NULL,
    apellido         VARCHAR(100) NOT NULL,
    email            VARCHAR(150) NOT NULL UNIQUE,
    password_hash    VARCHAR(255) NOT NULL,
    telefono         VARCHAR(20),
    id_rol           INT NOT NULL,
    id_departamento  INT,
    activo           BOOLEAN NOT NULL DEFAULT TRUE,
    fecha_registro   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ultimo_acceso    DATETIME,
    CONSTRAINT fk_usuario_rol
        FOREIGN KEY (id_rol) REFERENCES rol(id_rol),
    CONSTRAINT fk_usuario_departamento
        FOREIGN KEY (id_departamento) REFERENCES departamento(id_departamento)
);

CREATE INDEX idx_usuario_email ON usuario(email);
CREATE INDEX idx_usuario_rol   ON usuario(id_rol);

-- =====================================================
-- 3. INCIDENCIA (tabla central)
-- =====================================================

CREATE TABLE incidencia (
    id_incidencia      INT AUTO_INCREMENT PRIMARY KEY,
    codigo             VARCHAR(20) NOT NULL UNIQUE,   -- ej: INC-2025-0001
    titulo             VARCHAR(200) NOT NULL,
    descripcion        TEXT NOT NULL,
    id_categoria       INT NOT NULL,
    id_prioridad       INT NOT NULL,
    id_estado          INT NOT NULL,
    id_reportante      INT NOT NULL,                  -- usuario que reporta
    id_tecnico         INT,                           -- técnico asignado (nullable)
    fecha_creacion     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_asignacion   DATETIME,
    fecha_resolucion   DATETIME,
    fecha_cierre       DATETIME,
    fecha_limite_sla   DATETIME,                      -- calculada según prioridad
    solucion           TEXT,                          -- resolución aplicada

    CONSTRAINT fk_inc_categoria
        FOREIGN KEY (id_categoria) REFERENCES categoria(id_categoria),
    CONSTRAINT fk_inc_prioridad
        FOREIGN KEY (id_prioridad) REFERENCES prioridad(id_prioridad),
    CONSTRAINT fk_inc_estado
        FOREIGN KEY (id_estado) REFERENCES estado(id_estado),
    CONSTRAINT fk_inc_reportante
        FOREIGN KEY (id_reportante) REFERENCES usuario(id_usuario),
    CONSTRAINT fk_inc_tecnico
        FOREIGN KEY (id_tecnico) REFERENCES usuario(id_usuario)
);

CREATE INDEX idx_inc_estado    ON incidencia(id_estado);
CREATE INDEX idx_inc_prioridad ON incidencia(id_prioridad);
CREATE INDEX idx_inc_tecnico   ON incidencia(id_tecnico);
CREATE INDEX idx_inc_reportante ON incidencia(id_reportante);
CREATE INDEX idx_inc_fecha     ON incidencia(fecha_creacion);

-- =====================================================
-- 4. COMENTARIOS
-- =====================================================

CREATE TABLE comentario (
    id_comentario  INT AUTO_INCREMENT PRIMARY KEY,
    id_incidencia  INT NOT NULL,
    id_usuario     INT NOT NULL,             -- autor del comentario
    texto          TEXT NOT NULL,
    es_interno     BOOLEAN NOT NULL DEFAULT FALSE,  -- visible solo a técnicos
    fecha          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_com_incidencia
        FOREIGN KEY (id_incidencia) REFERENCES incidencia(id_incidencia)
        ON DELETE CASCADE,
    CONSTRAINT fk_com_usuario
        FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario)
);

CREATE INDEX idx_com_incidencia ON comentario(id_incidencia);

-- =====================================================
-- 5. ADJUNTOS
-- =====================================================

CREATE TABLE adjunto (
    id_adjunto     INT AUTO_INCREMENT PRIMARY KEY,
    id_incidencia  INT NOT NULL,
    id_comentario  INT,                      -- opcional: si va dentro de un comentario
    nombre_archivo VARCHAR(255) NOT NULL,
    ruta           VARCHAR(500) NOT NULL,
    tipo_mime      VARCHAR(100),
    tamano_bytes   BIGINT,
    id_usuario     INT NOT NULL,             -- quien lo subió
    fecha_subida   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_adj_incidencia
        FOREIGN KEY (id_incidencia) REFERENCES incidencia(id_incidencia)
        ON DELETE CASCADE,
    CONSTRAINT fk_adj_comentario
        FOREIGN KEY (id_comentario) REFERENCES comentario(id_comentario)
        ON DELETE CASCADE,
    CONSTRAINT fk_adj_usuario
        FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario)
);

-- =====================================================
-- 6. HISTORIAL / AUDITORÍA
-- =====================================================

CREATE TABLE historial_incidencia (
    id_historial   INT AUTO_INCREMENT PRIMARY KEY,
    id_incidencia  INT NOT NULL,
    id_usuario     INT NOT NULL,             -- quién hizo el cambio
    accion         VARCHAR(50) NOT NULL,     -- CREACION, CAMBIO_ESTADO, ASIGNACION, COMENTARIO, etc.
    valor_anterior VARCHAR(255),
    valor_nuevo    VARCHAR(255),
    fecha          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_hist_incidencia
        FOREIGN KEY (id_incidencia) REFERENCES incidencia(id_incidencia)
        ON DELETE CASCADE,
    CONSTRAINT fk_hist_usuario
        FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario)
);

CREATE INDEX idx_hist_incidencia ON historial_incidencia(id_incidencia);

-- =====================================================
-- 7. NOTIFICACIONES
-- =====================================================

CREATE TABLE notificacion (
    id_notificacion INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario      INT NOT NULL,
    id_incidencia   INT,
    mensaje         VARCHAR(500) NOT NULL,
    leida           BOOLEAN NOT NULL DEFAULT FALSE,
    fecha           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_not_usuario
        FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario)
        ON DELETE CASCADE,
    CONSTRAINT fk_not_incidencia
        FOREIGN KEY (id_incidencia) REFERENCES incidencia(id_incidencia)
        ON DELETE CASCADE
);

CREATE INDEX idx_not_usuario ON notificacion(id_usuario, leida);

-- =====================================================
-- 8. DATOS INICIALES (SEED)
-- =====================================================

INSERT INTO rol (nombre, descripcion) VALUES
('USUARIO', 'Usuario final que reporta incidencias'),
('TECNICO', 'Técnico que atiende y resuelve incidencias'),
('ADMINISTRADOR', 'Administrador del sistema');

INSERT INTO estado (nombre, color, es_final) VALUES
('ABIERTA',     '#3498DB', FALSE),
('EN_PROGRESO', '#F1C40F', FALSE),
('RESUELTA',    '#2ECC71', FALSE),
('CERRADA',     '#95A5A6', TRUE),
('RECHAZADA',   '#E74C3C', TRUE);

INSERT INTO prioridad (nombre, nivel, color, horas_sla) VALUES
('BAJA',    4, '#2ECC71', 120),
('MEDIA',   3, '#F1C40F', 48),
('ALTA',    2, '#E67E22', 24),
('CRITICA', 1, '#E74C3C', 4);

INSERT INTO categoria (nombre, descripcion) VALUES
('Red',      'Problemas de conectividad'),
('Hardware', 'Fallas físicas de equipos'),
('Software', 'Errores de aplicaciones'),
('Acceso',   'Permisos y contraseñas'),
('Otro',     'No clasificada');

INSERT INTO departamento (nombre) VALUES
('Sistemas'), ('Recursos Humanos'), ('Ventas'), ('Contabilidad');