# Estado de la Migración: Spring Boot a Laravel 11

Este documento detalla el progreso actual, qué componentes están listos y qué falta por implementar para completar la migración.

## ✅ Completado

### Configuración del Proyecto
- [x] Proyecto Laravel 11 instalado y configurado.
- [x] Base de datos MySQL conectada (`abogados_asociados`).
- [x] Dependencias instaladas: `php-open-source-saver/jwt-auth` (Auth), `phpoffice/phpword` (Word), `barryvdh/laravel-dompdf` (PDF).
- [x] Configuración de JWT (`config/jwt.php` y `config/auth.php`).

### Base de Datos
- [x] Migraciones creadas y ejecutadas para todas las tablas:
  - `roles`, `usuarios`, `expedientes`, `archivos`, `contacts`, `services`.
- [x] Esquema ajustado para coincidir con la base de datos SQL existente.
- [x] Seeder de Roles (`ADMIN`, `USUARIO`) creado.

### Backend Core
- [x] **Modelos Eloquent**: `User` (con JWT), `Role`, `Expediente`, `Archivo`, `Contact`, `Service`.
- [x] **Autenticación**: `AuthController` implementado (login, logout, me, refresh).
- [x] **Expedientes**: `ExpedienteController` implementado (CRUD completo, subida y descarga de archivos).

### Controladores
- [x] **UsuarioController**: CRUD completo con validaciones y hash de contraseñas.
- [x] **ContactController**: Endpoints públicos y privados para mensajes de contacto.
- [x] **EstadisticasController**: Dashboard con estadísticas de expedientes, usuarios y mensajes.
- [x] **DocumentoController**: Generación de documentos Word y PDF.

### Servicios
- [x] **WordDocumentService**: Generación de documentos Word usando PhpWord.
- [x] **PDFDocumentService**: Generación de documentos PDF usando DomPDF.
- [x] Vista Blade para PDF (`resources/views/pdf/expediente.blade.php`).

### Middleware y Seguridad
- [x] **RoleMiddleware**: Middleware para proteger rutas por rol (ADMIN).
- [x] Rutas protegidas configuradas correctamente.

---

## 🚧 Pendiente de Implementar

### 1. Configuración CORS
Verificar `config/cors.php` para asegurar que permite peticiones desde tu frontend (ej. `http://localhost:5173`).

### 2. Usuario Administrador Inicial
Crear un usuario administrador para poder iniciar sesión:

```bash
php artisan tinker
```

```php
\App\Models\User::create([
    'nombre' => 'Administrador',
    'username' => 'admin',
    'password' => Hash::make('admin123'),
    'rol_id' => 1 // ADMIN
]);
```

### 3. Pruebas
- Probar todos los endpoints con Postman o similar.
- Verificar que la autenticación JWT funciona correctamente.
- Probar la generación de documentos Word y PDF.
- Verificar que el middleware de roles funciona correctamente.

---

## 📋 Resumen de Endpoints Disponibles

### Públicos
- `POST /api/auth/login` - Login
- `POST /api/contacto` - Enviar mensaje de contacto

### Autenticados
- `POST /api/auth/logout` - Logout
- `POST /api/auth/refresh` - Refrescar token
- `GET /api/auth/me` - Obtener usuario actual
- `GET /api/expedientes` - Listar expedientes
- `POST /api/expedientes` - Crear expediente
- `GET /api/expedientes/{id}` - Ver expediente
- `PUT /api/expedientes/{id}` - Actualizar expediente
- `DELETE /api/expedientes/{id}` - Eliminar expediente
- `POST /api/expedientes/{id}/archivo` - Subir archivo
- `GET /api/expedientes/{id}/archivo/download` - Descargar archivo
- `GET /api/expedientes/{id}/word` - Generar Word
- `GET /api/expedientes/{id}/pdf` - Generar PDF
- `GET /api/estadisticas` - Dashboard de estadísticas
- `GET /api/estadisticas/expedientes-por-estado` - Estadísticas por estado
- `GET /api/estadisticas/expedientes-por-tipo` - Estadísticas por tipo

### Solo ADMIN
- `GET /api/usuarios` - Listar usuarios
- `POST /api/usuarios` - Crear usuario
- `GET /api/usuarios/{id}` - Ver usuario
- `PUT /api/usuarios/{id}` - Actualizar usuario
- `DELETE /api/usuarios/{id}` - Eliminar usuario
- `GET /api/contacto` - Listar mensajes de contacto
- `GET /api/contacto/{id}` - Ver mensaje de contacto
- `DELETE /api/contacto/{id}` - Eliminar mensaje de contacto

---

## Instrucciones para Continuar

1. **Verificar CORS**: Revisar `config/cors.php` y ajustar según necesites.

2. **Crear usuario administrador**:
   ```bash
   php artisan tinker
   ```
   ```php
   \App\Models\User::create([
       'nombre' => 'Administrador',
       'username' => 'admin',
       'password' => Hash::make('admin123'),
       'rol_id' => 1
   ]);
   ```

3. **Ejecutar el servidor**:
   ```bash
   php artisan serve
   ```

4. **Probar la API**: Usa Postman o tu frontend para probar los endpoints.
