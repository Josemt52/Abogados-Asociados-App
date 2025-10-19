-- Eliminar la base de datos si ya existe (opcional)
DROP DATABASE IF EXISTS expediente;

-- Crear la base de datos
CREATE DATABASE Abogados_Asociados;
USE Abogados_Asociados;

-- Tabla de Roles
CREATE TABLE roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL UNIQUE
);

-- Tabla de Usuarios
CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100),
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,  -- Aún sin bcrypt
    rol_id INT NOT NULL,
    FOREIGN KEY (rol_id) REFERENCES roles(id) ON DELETE CASCADE
);

-- Tabla de Expedientes
CREATE TABLE expedientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero VARCHAR(50) NOT NULL UNIQUE,  -- Obligatorio
    materia VARCHAR(100),
    juzgado VARCHAR(100),
    especialista VARCHAR(100),
    tercero VARCHAR(100),
    demandado VARCHAR(100),
    demandante VARCHAR(100),
    estado_actual TEXT,  -- Estado del expediente (puede ser HTML)
    archivo BOOLEAN DEFAULT FALSE,  -- Indica si tiene archivo adjunto
    nombre_archivo VARCHAR(255)  -- Nombre del archivo adjunto
);

-- Tabla de Histórico de Estados (para resoluciones y cambios de estado)
CREATE TABLE historico_estados (
    id INT AUTO_INCREMENT PRIMARY KEY,
    expediente_id INT NOT NULL,
    numero_resolucion INT NOT NULL,
    descripcion TEXT,
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (expediente_id) REFERENCES expedientes(id) ON DELETE CASCADE
);

-- Tabla de Archivos (almacena los datos binarios de archivos adjuntos)
CREATE TABLE archivos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    expediente_id INT NOT NULL,  -- Referencia al ID del expediente (no al número)
    tipo_archivo VARCHAR(100),  -- MIME type del archivo
    nombre_archivo VARCHAR(255) NOT NULL,
    documento_data LONGBLOB,  -- Datos binarios del archivo (hasta 10MB)
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (expediente_id) REFERENCES expedientes(id) ON DELETE CASCADE,
    INDEX idx_expediente_id (expediente_id)
);

-- Insertar roles básicos
INSERT INTO roles (nombre) VALUES ('Administrador'), ('Usuario'), ('Secretario');

-- Insertar usuario de prueba
INSERT INTO usuarios (nombre, username, password, rol_id) 
VALUES ('Juan Pérez', 'admin', 'admin123', 1);

-- Insertar un expediente de prueba
INSERT INTO expedientes (numero, materia, juzgado, especialista, tercero, demandado, demandante, estado_actual, archivo, nombre_archivo)
VALUES ('EXP-2025-001', 'Civil', 'Juzgado Nº 1', 'Carlos Gómez', 'Empresa X', 'Luis Martínez', 'Pedro Rodríguez', 'En proceso', FALSE, NULL);

-- Insertar una resolución en el histórico de estados
INSERT INTO historico_estados (expediente_id, numero_resolucion, descripcion)
VALUES (1, 1, 'Primera resolución del expediente.');


