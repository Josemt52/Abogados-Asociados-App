# 🚀 Guía de Inicio Rápido - Desarrollo

## Requisitos Previos

### Backend (Laravel)
- PHP 8.2+
- Composer
- MySQL
- Extensiones PHP: openssl, pdo, mbstring, tokenizer, xml, ctype, json, bcmath, fileinfo

### Frontend (React + Vite)
- Node.js 18+
- npm 8+

---

## 📦 Instalación Inicial

### 1. Backend Laravel

```bash
# Navegar al directorio del backend
cd backend-laravel

# Instalar dependencias
composer install

# Copiar archivo de configuración (si no existe)
cp .env.example .env

# Generar clave de aplicación
php artisan key:generate

# Generar clave JWT
php artisan jwt:secret

# Configurar base de datos en .env
# DB_DATABASE=abogados_asociados
# DB_USERNAME=root
# DB_PASSWORD=

# Ejecutar migraciones
php artisan migrate

# Ejecutar seeders (roles)
php artisan db:seed

# Crear usuario administrador
php artisan tinker
```

En tinker:
```php
\App\Models\User::create([
    'nombre' => 'Administrador',
    'username' => 'admin',
    'password' => Hash::make('admin123'),
    'rol_id' => 1
]);
exit
```

### 2. Frontend React

```bash
# Navegar al directorio del frontend
cd project

# Instalar dependencias
npm install

# Verificar archivo .env
# VITE_API_URL=http://localhost:8000
```

---

## 🏃 Iniciar Servidores de Desarrollo

### Terminal 1: Backend Laravel

```bash
cd backend-laravel
php artisan serve
```

El backend estará disponible en: **http://localhost:8000**

### Terminal 2: Frontend React

```bash
cd project
npm run dev
```

El frontend estará disponible en: **http://localhost:5173**

---

## 🔐 Credenciales de Prueba

**Usuario Administrador:**
- Username: `admin`
- Password: `admin123`

---

## 📋 Verificación de Funcionamiento

### 1. Verificar Backend
Abre en tu navegador o Postman:
```
GET http://localhost:8000/api/auth/me
```

Deberías recibir un error 401 (esperado sin token).

### 2. Verificar Frontend
Abre en tu navegador:
```
http://localhost:5173
```

Deberías ver la página de login.

### 3. Probar Login
1. Ingresa con las credenciales de prueba
2. Deberías ser redirigido al dashboard
3. Verifica que puedas navegar por las diferentes secciones

---

## 🛠️ Comandos Útiles

### Backend

```bash
# Limpiar caché
php artisan cache:clear
php artisan config:clear
php artisan route:clear

# Ver rutas disponibles
php artisan route:list

# Ejecutar migraciones frescas (CUIDADO: borra datos)
php artisan migrate:fresh --seed

# Ver logs
tail -f storage/logs/laravel.log
```

### Frontend

```bash
# Limpiar caché de node_modules
rm -rf node_modules package-lock.json
npm install

# Build para producción
npm run build

# Preview de producción
npm run preview

# Ejecutar tests
npm run test
```

---

## 🐛 Solución de Problemas Comunes

### Backend no inicia
- Verifica que el puerto 8000 no esté ocupado
- Verifica que MySQL esté corriendo
- Revisa los logs en `storage/logs/laravel.log`

### Frontend no conecta con Backend
- Verifica que el backend esté corriendo en el puerto 8000
- Verifica el archivo `.env` del frontend
- Abre la consola del navegador para ver errores de red

### Error de CORS
- Verifica `config/cors.php` en el backend
- Asegúrate de que `allowed_origins` incluya `http://localhost:5173`

### Error 401 al hacer peticiones
- Verifica que el token JWT esté configurado correctamente
- Intenta hacer logout y login nuevamente
- Verifica que el usuario exista en la base de datos

### Base de datos no conecta
- Verifica las credenciales en `.env`
- Asegúrate de que MySQL esté corriendo
- Verifica que la base de datos `abogados_asociados` exista

---

## 📚 Estructura de Directorios

```
Abogados-Asociados-App/
├── backend-laravel/          # Backend Laravel 11
│   ├── app/
│   │   ├── Http/Controllers/Api/
│   │   ├── Models/
│   │   └── Services/
│   ├── config/
│   ├── database/
│   ├── routes/
│   └── storage/
│
├── project/                  # Frontend React + TypeScript
│   ├── src/
│   │   ├── api/             # Servicios de API
│   │   ├── components/      # Componentes React
│   │   ├── hooks/           # Custom hooks
│   │   ├── pages/           # Páginas
│   │   └── utils/           # Utilidades
│   └── public/
│
└── backend/                  # Backend Java (legacy)
    └── ...
```

---

## 🎉 ¡Listo!

Ahora tienes el sistema completo funcionando:
- ✅ Backend Laravel con API REST
- ✅ Frontend React con TypeScript
- ✅ Autenticación JWT
- ✅ CRUD de expedientes
- ✅ Gestión de usuarios
- ✅ Generación de documentos (Word y PDF)
- ✅ Dashboard con estadísticas

**Siguiente paso:** Conecta el frontend con el backend y comienza a desarrollar las funcionalidades específicas de tu aplicación.
