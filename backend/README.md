# Backend - Abogados Asociados

## Cambios Recientes

### ✅ Mejoras en el Registro de Usuarios

1. **Inicialización automática de roles**: Al arrancar la aplicación, se crean automáticamente los roles `admin` y `usuario` si no existen (ver `DataInitializer.java`).

2. **Mejor manejo de errores**: El endpoint `POST /api/usuarios` ahora devuelve mensajes de error claros:
   - Validación de campos obligatorios (username, email, password, rol)
   - Verificación de username duplicado
   - Mensaje claro si el rol no existe
   - Respuestas HTTP 400 con JSON descriptivo

3. **Script SQL opcional**: Si prefieres insertar roles manualmente, ejecuta `src/main/resources/init-roles.sql`

## Ejecutar el Backend

### Requisitos
- Java 17 o superior
- Maven 3.6+
- MySQL 8.0+ (o la BD configurada en `application.properties`)

### Pasos

1. **Configurar variables de entorno** (CRÍTICO):
   
   Crea un archivo `.env` en la carpeta `backend/` copiando `.env.example`:
   ```bash
   cp backend/.env.example backend/.env
   ```
   
   Edita `backend/.env` y configura los valores reales:
   ```properties
   # Base de datos
   DB_URL=jdbc:mysql://localhost:3306/abogados_asociados?useSSL=false&allowPublicKeyRetrieval=true
   DB_USERNAME=tu_usuario_mysql
   DB_PASSWORD=tu_password_mysql
   
   # JWT Secret (IMPORTANTE: En producción usa un valor aleatorio seguro)
   # Genera uno con: openssl rand -base64 32
   JWT_SECRET=tu-secret-key-muy-seguro-minimo-256-bits
   JWT_EXPIRATION=86400000
   ```
   
   > **⚠️ NUNCA SUBAS EL ARCHIVO .env AL REPOSITORIO**

2. **Asegúrate de que MySQL esté corriendo** y la base de datos existe:
   ```sql
   CREATE DATABASE IF NOT EXISTS abogados_db;
   ```

2. **Configura la conexión** en `src/main/resources/application.properties`:
   ```properties
   spring.datasource.url=jdbc:mysql://localhost:3306/abogados_db
   spring.datasource.username=tu_usuario
   spring.datasource.password=tu_password
   ```

3. **Ejecuta con Maven** (desde la carpeta `backend`):
   ```powershell
   mvn spring-boot:run
   ```
   O genera el JAR y ejecútalo:
   ```powershell
   mvn clean package -DskipTests
   java -jar target/backend-1.0.0.jar
   ```

4. **Verifica los logs** al arrancar. Deberías ver:
   ```
   ✓ Rol 'admin' creado automáticamente
   ✓ Rol 'usuario' creado automáticamente
   ✓ Inicialización de roles completada
   ```

5. **El servidor arranca en**: `http://localhost:8080` (puerto por defecto)

## Endpoints Principales

### Autenticación
- `POST /api/auth/login` - Login de usuario
  ```json
  {
    "username": "Jose",
    "password": "Morganella.12334"
  }
  ```

### Usuarios
- `POST /api/usuarios` - Crear usuario
  ```json
  {
    "username": "Jose",
    "email": "josemigueltejada.meza@gmail.com",
    "password": "Morganella.12334",
    "rol": "admin"
  }
  ```
  Roles válidos: `"admin"` o `"usuario"`

- `GET /api/usuarios` - Listar todos los usuarios
- `GET /api/usuarios/{id}` - Obtener un usuario por ID
- `PUT /api/usuarios/{id}` - Actualizar usuario
- `DELETE /api/usuarios/{id}` - Eliminar usuario

### Expedientes
- `GET /api/expedientes` - Listar expedientes
- `POST /api/expedientes` - Crear expediente
- `GET /api/expedientes/{id}` - Obtener expediente
- `PUT /api/expedientes/{id}` - Actualizar expediente
- `DELETE /api/expedientes/{id}` - Eliminar expediente

### Archivos
- `POST /api/expedientes/{expedienteId}/archivos` - Subir archivo a expediente
- `GET /api/expedientes/{expedienteId}/archivos` - Listar archivos de expediente
- `DELETE /api/archivos/{id}` - Eliminar archivo

## Solución de Problemas

### Error: "Rol no encontrado"
- **Causa**: La tabla `roles` está vacía
- **Solución**: Reinicia la aplicación. El `DataInitializer` creará los roles automáticamente. O ejecuta manualmente `init-roles.sql`.

### Error: "Table 'abogados_db.roles' doesn't exist"
- **Causa**: JPA no ha creado las tablas
- **Solución**: Verifica `application.properties`:
  ```properties
  spring.jpa.hibernate.ddl-auto=update
  ```
  Reinicia la app para que Hibernate cree las tablas.

### Puerto 8080 ocupado
- Cambia el puerto en `application.properties`:
  ```properties
  server.port=8081
  ```

### CORS errors desde el frontend
- El backend ya tiene CORS configurado en `CorsConfig.java` para `http://localhost:5173`
- Si usas otro puerto en el frontend, ajusta `CorsConfig.java`

## TODO / Mejoras Pendientes

- [x] ~~Encriptar contraseñas con BCrypt~~ - ✅ Implementado
- [x] ~~Implementar JWT real en lugar de mock token~~ - ✅ Implementado  
- [x] ~~Externalizar secrets y credenciales~~ - ✅ Implementado
- [ ] Implementar refresh tokens
- [ ] Añadir validación de email con regex en el backend
- [ ] Añadir auditoría (createdAt, updatedAt en entidades)
- [ ] Implementar rate limiting para prevenir brute force
- [ ] Migrar a Flyway para control de versiones de BD
