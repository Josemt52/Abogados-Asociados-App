## Estructura del frontend (carpeta `project`)

Este documento resume la estructura del frontend (carpeta `project`) del repositorio.
Incluye el árbol de archivos principales y una breve descripción de la finalidad de cada archivo/carpeta.

### Resumen rápido

- Framework: React + Vite
- Estilos: Tailwind CSS
- Lenguaje: TypeScript
- PUERTO de desarrollo por defecto: 5173 (Vite)

### Cómo levantar el frontend (resumen)

- Instala dependencias: usar tu gestor de paquetes (npm/yarn/pnpm) en la carpeta `project`.
- Ejecuta el script de desarrollo definido en `package.json` (habitualmente `dev`) y abre el navegador en `http://localhost:5173`.
- Nota: si ves la advertencia "Browserslist: caniuse-lite is outdated", puedes actualizar la base de datos con `npx browserslist@latest --update-db` o añadiendo un `postinstall` que la actualice.

---

### Árbol principal

project/
- package.json             # scripts y dependencias del frontend (Vite, React, Tailwind, etc.)
- index.html               # entrada HTML principal
- tsconfig.json            # configuración TypeScript
- vite.config.ts           # configuración de Vite
- postcss.config.js        # configuración de PostCSS/Tailwind
- tailwind.config.js       # configuración de Tailwind
- src/
  - main.tsx               # punto de entrada y montaje de React
  - index.css              # estilos globales (Tailwind)
  - App.tsx                # root App, rutas principales
  - vite-env.d.ts          # tipos para Vite/ambient
  - api/
    - index.ts             # cliente axios / funciones para llamadas al backend
  - components/
    - Layout/              # componente de layout (header / sidebar)
      - Layout.tsx         # header responsivo, navegación protegida
    - FileUploader/
      - FileUploader.tsx   # componente para subir y validar archivos (.docx), usa input oculto
    - ExpedienteForm/
      - ExpedienteForm.tsx # formulario para crear/editar expedientes
    - ProtectedRoute/
      - ProtectedRoute.tsx # wrapper para rutas que requieren autenticación
    - UI/
      - Button.tsx         # botón reutilizable (soporta `as="span"` para label-file)
      - LoadingSpinner.tsx # spinner de carga
      - Modal.tsx          # modal reutilizable
      - ProgressBar.tsx    # barra de progreso (subidas)
      - Table.tsx          # tabla reutilizable
  - hooks/
    - useAuth.ts           # hook de autenticación (contexto / token / login/logout)
    - useFetch.ts          # hook genérico para peticiones
    - useDocumentGeneration.ts
    - useEstadisticas.ts
  - pages/
    - Login/
      - Login.tsx          # página de login
    - RegistrarUsuario/
      - RegistrarUsuario.tsx # página/forma de registro de usuarios (usa API de backend)
    - Usuarios/
      - Usuarios.tsx       # página de gestión de usuarios (solo admin)
      - README.md          # documentación del componente Usuarios
    - Main/
      - Main.tsx           # página principal / dashboard
    - Expedientes/
      - Expedientes.tsx    # listado de expedientes
    - ExpedienteDetail/
      - ExpedienteDetail.tsx # detalle de expediente
  - utils/
    - fileDownload.ts      # util para descargar ficheros desde respuestas del backend

### Archivos importantes y qué hacen

- `package.json`:
  - Contiene scripts útiles: `dev` (arranque Vite), `build`, `preview` y pruebas (vitest).
  - Dependencias claves: `react`, `react-dom`, `axios`, `tailwindcss`, `vite`.

- `vite.config.ts`:
  - Configuración de resoluciones, alias y plugins para Vite.

- `src/main.tsx`:
  - Monta React en el DOM y aplica `BrowserRouter` (si está presente) y providers necesarios.

- `src/App.tsx`:
  - Define rutas y layout general de la aplicación, incluye rutas públicas y protegidas.

- `src/components/Layout/Layout.tsx`:
  - Header y navegación. Implementa comportamiento responsive (hamburger menu) y muestra botones según el estado de `useAuth`.

- `src/components/FileUploader/FileUploader.tsx`:
  - Controla la selección de archivos mediante un input oculto y validación de tipo/size. Usa un botón/label para abrir el selector.

- `src/components/UI/Button.tsx`:
  - Componente reutilizable que ahora soporta renderizarse como `span` (prop `as`) para poder usarse como `label` que activa inputs ocultos.

- `src/hooks/useAuth.ts`:
  - Lógica de autenticación del lado cliente (almacenamiento de token, comprobación de sesión, renovación, logout).
  - **Interface User actualizada**: ahora usa `nombre` en lugar de `email` para coincidir con el modelo del backend.
  - Estructura del objeto User:
    ```typescript
    interface User {
      id: string;
      nombre: string;      // Nombre completo del usuario
      username: string;     // Usuario para login
      rol: { 
        id: number; 
        nombre: string;     // "admin" o "usuario" (minúsculas)
      };
    }
    ```

- `src/pages/RegistrarUsuario/RegistrarUsuario.tsx`:
  - Formulario de registro que consume el endpoint del backend para crear usuarios.
  - **Cambios importantes**:
    - ❌ Campo `email` eliminado completamente
    - ✅ Campo `nombre` (Nombre Completo) agregado
    - Validación de nombre completo requerido
    - Envía datos al backend en formato: `{nombre, username, password, rol}`
  - Asegúrate de que el backend esté arriba (roles inicializados) antes de probar.

- `src/pages/Usuarios/Usuarios.tsx`:
  - Página administrativa para gestionar usuarios del sistema (solo accesible para admins).
  - **Características**:
    - ✅ Búsqueda en tiempo real por nombre, usuario o rol
    - ✅ Tabla con columnas: ID, Nombre, Usuario, Rol, Acciones
    - ✅ Modal de edición con campos: nombre, username, rol, password (opcional)
    - ✅ Modal de confirmación para eliminar
    - ✅ Validación de permisos: solo usuarios con `rol.nombre === "admin"` pueden acceder
  - Usa el componente `Table` y modales para edición/eliminación.
  - **Cambios importantes**:
    - ❌ Columna y campo `email` eliminados
    - ✅ Columna `nombre` agregada (muestra nombre completo)
    - ✅ Filtrado actualizado para buscar por nombre, username y rol

### Tests

- `tests/` contiene pruebas con Vitest para componentes como `ExpedienteForm` y listas. Ejecuta `vitest` para correrlas.

---

## Cambios Importantes en Gestión de Usuarios (Octubre 2025)

### 🔄 Corrección Completa del Sistema - "Acceso Restringido" Resuelto

#### ⚠️ Problema Original:
- Backend tenía campo `email` en modelo `Usuario.java` que **NO existía en la base de datos**
- Roles en BD con mayúsculas ("Administrador") vs backend esperando minúsculas ("admin")
- Usuarios admin veían mensaje "Acceso Restringido" incorrectamente

#### ✅ Solución Implementada:

**1. Eliminación completa del campo `email`**

El campo `email` ha sido completamente eliminado de frontend y backend:

**Backend - Antes:**
```java
@Entity
public class Usuario {
    private Integer id;
    private String nombre;
    private String username;
    private String email;      // ❌ ELIMINADO
    private String password;
    private Rol rol;
}
```

**Backend - Ahora:**
```java
@Entity
public class Usuario {
    private Integer id;
    private String nombre;     // ✅ Nombre completo
    private String username;
    private String password;
    @ManyToOne
    private Rol rol;
}
```

**Frontend - Antes:**
```typescript
interface User {
  id: string;
  username: string;
  email: string;      // ❌ ELIMINADO
  rol: { id: number; nombre: string };
}
```

**Frontend - Ahora:**
```typescript
interface User {
  id: string;
  nombre: string;     // ✅ NUEVO - Nombre completo
  username: string;
  rol: { id: number; nombre: string };
}
```

**2. Roles actualizados a minúsculas**

Los roles ahora se manejan en **minúsculas** para coincidir con el backend:

| Antes | Ahora |
|-------|-------|
| "Administrador" | "admin" |
| "Usuario" | "usuario" |
| "Secretario" | "secretario" |

**Validación de acceso actualizada:**
```typescript
// En useAuth.ts y componentes protegidos
const isAdmin = user?.rol?.nombre?.toLowerCase() === 'admin';
```

**3. Componentes actualizados**

| Componente | Cambios |
|-----------|---------|
| `useAuth.ts` | Interface User sin `email`, con `nombre` |
| `RegistrarUsuario.tsx` | ❌ Campo email eliminado<br>✅ Campo "Nombre Completo" agregado<br>✅ Validación de nombre requerido |
| `Usuarios.tsx` | ❌ Columna email eliminada<br>✅ Columna "Nombre" agregada<br>✅ Búsqueda por nombre, username, rol<br>✅ Modal de edición actualizado |
| `Layout.tsx` | ✅ Corrección `user.usuario` → `user.username` |

### 📊 Estructura de Datos Actualizada

**Login Response (`/api/auth/login`):**
```json
{
  "user": {
    "id": "1",
    "nombre": "Juan Pérez",
    "username": "admin",
    "rol": {
      "id": 1,
      "nombre": "admin"
    }
  },
  "token": "mock-jwt-token"
}
```

**Registro de Usuario Request (`POST /api/usuarios`):**
```json
{
  "nombre": "María García",
  "username": "maria",
  "password": "password123",
  "rol": "usuario"
}
```

**Actualizar Usuario Request (`PUT /api/usuarios/:id`):**
```json
{
  "nombre": "Juan Pérez Actualizado",
  "username": "admin",
  "rol": "admin",
  "password": "nueva_password"  // Opcional
}
```

**Lista de Usuarios Response (`GET /api/usuarios`):**
```json
[
  {
    "id": 1,
    "nombre": "Juan Pérez",
    "username": "admin",
    "rol": {
      "id": 1,
      "nombre": "admin"
    }
  },
  {
    "id": 2,
    "nombre": "María García",
    "username": "maria",
    "rol": {
      "id": 2,
      "nombre": "usuario"
    }
  }
]
```

### 🔄 Migración de Base de Datos

**Archivo creado: `migracion_usuarios.sql`**

Si ya tienes datos existentes, ejecuta este script:

```sql
USE Abogados_Asociados;

-- 1. Actualizar roles a minúsculas
UPDATE roles SET nombre = 'admin' WHERE nombre = 'Administrador';
UPDATE roles SET nombre = 'usuario' WHERE nombre = 'Usuario';
UPDATE roles SET nombre = 'secretario' WHERE nombre = 'Secretario';

-- 2. Asegurar nombre completo en usuario admin
UPDATE usuarios 
SET nombre = 'Juan Pérez' 
WHERE username = 'admin' AND nombre IS NULL;

-- 3. Verificar resultado
SELECT u.id, u.nombre, u.username, r.id as rol_id, r.nombre as rol_nombre
FROM usuarios u
INNER JOIN roles r ON u.rol_id = r.id;
```

**⚠️ IMPORTANTE:** NO es necesario eliminar columna `email` porque nunca existió en el schema SQL.

### 🚀 Pasos para Aplicar los Cambios

1. **Ejecutar migración SQL:**
   ```bash
   # Opción A: Desde MySQL Workbench
   # Abrir migracion_usuarios.sql y ejecutar

   # Opción B: Desde consola
   mysql -u root -p Abogados_Asociados < migracion_usuarios.sql
   ```

2. **Reiniciar backend:**
   ```bash
   cd backend
   mvn clean spring-boot:run
   ```

3. **Verificar en consola del backend:**
   ```
   ✓ Rol 'admin' creado automáticamente
   ✓ Rol 'usuario' creado automáticamente
   ✓ Inicialización de roles completada
   ```

4. **Probar el sistema:**
   - Login: `admin` / `admin123`
   - Ir a menú "Usuarios"
   - ✅ NO debe aparecer "Acceso Restringido"
   - ✅ Debe mostrar tabla con usuarios

### 🐛 Troubleshooting

**Si sigue apareciendo "Acceso Restringido":**

1. **Verificar localStorage en DevTools (F12):**
   ```javascript
   // En la consola del navegador:
   console.log(JSON.parse(localStorage.getItem('user')));
   
   // Debe mostrar:
   {
     "id": "1",
     "nombre": "Juan Pérez",
     "username": "admin",
     "rol": {
       "id": 1,
       "nombre": "admin"  // ⚠️ DEBE ser minúscula
     }
   }
   ```

2. **Si `rol.nombre` NO es "admin" (minúscula):**
   - Ejecutar script `migracion_usuarios.sql`
   - Cerrar sesión en la app
   - Volver a iniciar sesión

3. **Si el objeto `user` no tiene `rol.nombre`:**
   - Verificar respuesta del endpoint `/api/auth/login` en Network tab
   - Confirmar que backend devuelve objeto Rol completo

**Errores al registrar usuarios:**
- Verificar que backend tenga roles inicializados
- Ejecutar `migracion_usuarios.sql`
- Confirmar que se envía `nombre` (no `email`) en el request

### ✅ Checklist de Validación

- [x] Campo `email` eliminado del backend (Usuario.java, DTO)
- [x] Campo `email` eliminado del frontend (interfaces, formularios)
- [x] Campo `nombre` agregado y usado correctamente
- [x] Roles en minúsculas ("admin", "usuario", "secretario")
- [x] Validación de roles funciona (`user.rol.nombre === 'admin'`)
- [x] Endpoints POST/PUT actualizados
- [x] Componente Usuarios.tsx corregido (tabla sin email)
- [x] RegistrarUsuario.tsx corregido (formulario sin email)
- [x] Script de migración SQL creado y documentado
- [x] Schema SQL actualizado en `schema_abogados.sql`
- [x] 0 errores de TypeScript en frontend
- [x] 0 errores de compilación en backend (BUILD SUCCESS)

### 📝 Archivos Modificados

**Backend (6 archivos):**
- ✅ `src/main/java/.../models/Usuario.java` - Campo email eliminado
- ✅ `src/main/java/.../dto/UserRegistrationRequest.java` - Email → Nombre
- ✅ `src/main/java/.../controllers/UsuarioController.java` - Endpoints actualizados
- ✅ `src/main/java/.../config/DataInitializer.java` - Roles en minúsculas
- ✅ `schema_abogados.sql` - Roles actualizados
- ✅ `migracion_usuarios.sql` - Script de migración (NUEVO)

**Frontend (4 archivos):**
- ✅ `src/hooks/useAuth.ts` - Interface User actualizada
- ✅ `src/pages/RegistrarUsuario/RegistrarUsuario.tsx` - Formulario sin email
- ✅ `src/pages/Usuarios/Usuarios.tsx` - Tabla y búsqueda actualizadas
- ✅ `src/components/Layout/Layout.tsx` - Corrección de username

**Documentación (2 archivos):**
- ✅ `RESUMEN_CORRECCION_USUARIOS.md` - Guía completa (NUEVO)
- ✅ `README.md` - Actualizado con cambios
- ✅ `FRONTEND_STRUCTURE.md` - Este archivo actualizado

---

## Recomendaciones rápidas después de tocar el frontend

- Si ves la advertencia de Browserslist, actualiza la base de datos con `npx browserslist@latest --update-db`.
- Para probar subida de archivos, abre la UI, navega al formulario que contiene `FileUploader` y prueba seleccionar un `.docx`.
- Para probar el registro de usuarios, arranca primero el backend (necesitas que `DataInitializer` o el script SQL inserten los roles `admin` y `usuario`).

---

Si quieres, puedo:

- Generar automáticamente una versión más detallada (con fragmentos de código y ejemplos de props) para componentes concretos.
- Crear un diagrama de dependencias entre componentes principales.
- Añadir un `README` dentro de `project/src/components` con guías de uso para los componentes reutilizables.

Indica cuál quieres que haga a continuación.
