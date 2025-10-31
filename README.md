# Componente Usuarios

## Descripción

El componente `Usuarios.tsx` es una página administrativa que permite gestionar los usuarios del sistema. Solo es accesible para usuarios con rol de administrador.

## Ubicación

`project/src/pages/Usuarios/Usuarios.tsx`

## Características

### 1. **Control de Acceso**
- Solo usuarios con rol `admin` pueden acceder
- Muestra mensaje de "Acceso Restringido" para usuarios sin permisos
- Valida permisos usando el hook `useAuth`

### 2. **Listado de Usuarios**
- Tabla con columnas: ID, Usuario, Email, Rol, Acciones
- Búsqueda en tiempo real por usuario, email o rol
- Indicadores visuales para roles (admin/usuario)

### 3. **Edición de Usuarios**
- Modal para editar información del usuario
- Campos: username, email, rol, contraseña (opcional)
- Validación de datos antes de enviar
- Toast notifications para éxito/error

### 4. **Eliminación de Usuarios**
- Modal de confirmación antes de eliminar
- Previene eliminación del usuario actual
- Mensaje de advertencia sobre acción irreversible

## Dependencias

### Hooks
- `useAuth` - Autenticación y datos del usuario actual
- `useFetch` - Obtención de datos de usuarios del backend

### Componentes UI
- `Table` - Tabla reutilizable para mostrar usuarios
- `Button` - Botones de acción
- `Modal` - Modales para editar y eliminar

### Iconos (lucide-react)
- `Search`, `Edit`, `Trash2`, `UserPlus`, `Shield`, `Mail`, `User`

### API
- `api.get('/api/usuarios')` - Obtener lista de usuarios
- `api.put('/api/usuarios/:id')` - Actualizar usuario
- `api.delete('/api/usuarios/:id')` - Eliminar usuario

## Estructura de Datos

### Usuario
```typescript
interface User {
  id: number;
  username: string;
  email: string;
  rol: {
    id: number;
    nombre: string; // 'admin' | 'usuario'
  };
}
```

### Datos de Edición
```typescript
interface EditUserData {
  username: string;
  email: string;
  rolId: number;
  password?: string; // Opcional, solo si se cambia
}
```

## Uso

### Navegación
- Acceso desde el menú lateral: `/usuarios`
- Botón "Nuevo Usuario" redirige a: `/usuarios/registrar`

### Búsqueda
```typescript
// La búsqueda filtra por:
- Nombre de usuario (case-insensitive)
- Email (case-insensitive)
- Nombre del rol (case-insensitive)
```

### Editar Usuario
1. Click en icono de edición (lápiz)
2. Modal se abre con datos pre-cargados
3. Modificar campos necesarios
4. Contraseña es opcional (dejar vacío para mantener actual)
5. Click en "Guardar Cambios"

### Eliminar Usuario
1. Click en icono de eliminación (papelera)
2. Modal de confirmación aparece
3. Confirmar eliminación
4. Usuario es eliminado del sistema

## Validaciones

- Email debe ser válido
- Username es requerido
- Rol debe ser seleccionado (1=admin, 2=usuario)
- No se puede eliminar el usuario actual
- Contraseña es opcional al editar

## Estilos

Usa Tailwind CSS con:
- Colores del tema principal (blue-700, gray-50, etc.)
- Badges de rol con colores distintivos (purple=admin, blue=usuario)
- Efectos hover en botones de acción
- Diseño responsive (compatible con móvil y desktop)

## Endpoints del Backend

Asegúrate de que el backend tenga implementados estos endpoints:

```
GET    /api/usuarios           - Listar todos los usuarios
GET    /api/usuarios/:id       - Obtener usuario por ID
PUT    /api/usuarios/:id       - Actualizar usuario
DELETE /api/usuarios/:id       - Eliminar usuario
```

### Formato de actualización (PUT)
```json
{
  "username": "string",
  "email": "string",
  "rol": "admin" | "usuario",
  "password": "string (opcional)"
}
```

## Integración con el Sistema

### App.tsx
```tsx
<Route path="usuarios" element={<Usuarios />} />
```

### Layout.tsx
```tsx
const navigation = [
  // ...
  { name: 'Usuarios', href: '/usuarios', icon: Users },
  // ...
];
```

### API (index.ts)
```tsx
export const usuariosAPI = {
  getAll: async () => { /* ... */ },
  getById: async (id: number) => { /* ... */ },
  update: async (id: number, userData: any) => { /* ... */ },
  delete: async (id: number) => { /* ... */ }
};
```

## Mensajes de Usuario

- **Éxito actualización**: "Usuario actualizado correctamente"
- **Éxito eliminación**: "Usuario eliminado correctamente"
- **Error genérico**: Muestra mensaje del backend o "Error en la operación"
- **Acceso denegado**: "No tienes permisos para acceder a esta sección"

## Mejoras Futuras

- [ ] Paginación para grandes cantidades de usuarios
- [ ] Filtros adicionales (por rol, estado, fecha de creación)
- [ ] Exportación de lista de usuarios (CSV/Excel)
- [ ] Historial de cambios por usuario
- [ ] Desactivar usuarios en lugar de eliminarlos
- [ ] Roles personalizados con permisos granulares

## Troubleshooting

### "Acceso Restringido" aunque soy admin
- Verifica que `user.rol.nombre.toLowerCase() === 'admin'`
- Revisa localStorage: `user.rol.nombre` debe ser exactamente "admin"

### No se muestran usuarios
- Confirma que el backend devuelve un array en `/api/usuarios`
- Revisa la consola del navegador para errores
- Verifica que el token de autenticación sea válido

### Error al editar/eliminar
- Confirma que el backend acepta PUT/DELETE en `/api/usuarios/:id`
- Verifica que el formato de datos enviados sea correcto
- Revisa respuestas del backend en Network tab

## Ejemplos de Uso

### Crear nuevo administrador
1. Ir a "Usuarios" → "Nuevo Usuario" (o `/usuarios/registrar`)
2. Llenar formulario con rol "admin"
3. Guardar

### Cambiar rol de usuario a admin
1. Ir a "Usuarios"
2. Buscar usuario
3. Click en editar (lápiz)
4. Cambiar rol a "Admin"
5. Guardar

### Resetear contraseña de usuario
1. Editar usuario
2. Ingresar nueva contraseña en campo "Nueva Contraseña"
3. Guardar
