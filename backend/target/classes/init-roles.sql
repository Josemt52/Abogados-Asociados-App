-- Script de inicialización para roles por defecto
-- Ejecutar este script si la tabla roles está vacía

-- Insertar roles por defecto si no existen
INSERT INTO roles (nombre) 
SELECT 'admin' 
WHERE NOT EXISTS (SELECT 1 FROM roles WHERE nombre = 'admin');

INSERT INTO roles (nombre) 
SELECT 'usuario' 
WHERE NOT EXISTS (SELECT 1 FROM roles WHERE nombre = 'usuario');

-- Verificar que los roles se insertaron correctamente
SELECT * FROM roles;
