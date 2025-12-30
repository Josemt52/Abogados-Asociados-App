# Integración Frontend-Backend Laravel

## ✅ Configuración Completada

### 1. API Client (Axios)
Se ha configurado un cliente Axios con:
- **Base URL**: `http://localhost:8000/api` (desarrollo)
- **Interceptores de Request**: Añade automáticamente el token JWT
- **Interceptores de Response**: Maneja errores globalmente y muestra notificaciones

### 2. Módulos de API Creados

#### `src/api/axios.ts`
Cliente Axios configurado con interceptores para:
- Añadir token JWT automáticamente
- Manejar errores 401 (sesión expirada)
- Manejar errores 403 (sin permisos)
- Manejar errores 422 (validación)
- Mostrar notificaciones de error automáticamente

#### `src/api/auth.ts`
Servicios de autenticación:
- `login(credentials)` - Iniciar sesión
- `logout()` - Cerrar sesión
- `me()` - Obtener usuario actual
- `refresh()` - Refrescar token

#### `src/api/expedientes.ts`
Servicios de expedientes:
- `getAll()` - Listar todos los expedientes
- `getById(id)` - Obtener expediente por ID
- `create(data)` - Crear expediente
- `update(id, data)` - Actualizar expediente
- `delete(id)` - Eliminar expediente
- `uploadFile(id, file)` - Subir archivo
- `downloadFile(id)` - Descargar archivo
- `generateWord(id)` - Generar documento Word
- `generatePdf(id)` - Generar documento PDF

#### `src/api/usuarios.ts`
Servicios de usuarios (solo ADMIN):
- `getAll()` - Listar usuarios
- `getById(id)` - Obtener usuario
- `create(data)` - Crear usuario
- `update(id, data)` - Actualizar usuario
- `delete(id)` - Eliminar usuario

#### `src/api/estadisticas.ts`
Servicios de estadísticas:
- `getDashboard()` - Obtener estadísticas del dashboard
- `getExpedientesPorEstado()` - Estadísticas por estado
- `getExpedientesPorTipo()` - Estadísticas por tipo

#### `src/api/contacto.ts`
Servicios de contacto:
- `create(data)` - Enviar mensaje (público)
- `getAll()` - Listar mensajes (admin)
- `getById(id)` - Ver mensaje (admin)
- `delete(id)` - Eliminar mensaje (admin)

---

## 🚀 Cómo Usar los Servicios

### Ejemplo: Login
```typescript
import { authAPI } from '../api';

const handleLogin = async () => {
  try {
    const { user, token } = await authAPI.login({
      username: 'admin',
      password: 'admin123'
    });
    
    // El token se guarda automáticamente en localStorage
    // Los errores se manejan automáticamente
    console.log('Usuario:', user);
  } catch (error) {
    // El error ya fue mostrado por el interceptor
  }
};
```

### Ejemplo: Listar Expedientes
```typescript
import { expedientesAPI } from '../api';

const loadExpedientes = async () => {
  try {
    const expedientes = await expedientesAPI.getAll();
    console.log('Expedientes:', expedientes);
  } catch (error) {
    // Error manejado automáticamente
  }
};
```

### Ejemplo: Crear Expediente
```typescript
import { expedientesAPI } from '../api';
import toast from 'react-hot-toast';

const createExpediente = async () => {
  try {
    const newExpediente = await expedientesAPI.create({
      numero_expediente: 'EXP-2024-001',
      nombre_cliente: 'Juan Pérez',
      tipo_caso: 'Civil',
      estado_actual: 'EN_PROCESO',
      fecha_inicio: '2024-01-15',
      descripcion: 'Caso de divorcio'
    });
    
    toast.success('Expediente creado exitosamente');
    return newExpediente;
  } catch (error) {
    // Error manejado automáticamente
  }
};
```

### Ejemplo: Descargar Archivo
```typescript
import { expedientesAPI } from '../api';

const downloadFile = async (expedienteId: number) => {
  try {
    const blob = await expedientesAPI.downloadFile(expedienteId);
    
    // Crear URL temporal y descargar
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `expediente_${expedienteId}.pdf`;
    link.click();
    window.URL.revokeObjectURL(url);
  } catch (error) {
    // Error manejado automáticamente
  }
};
```

### Ejemplo: Generar PDF
```typescript
import { expedientesAPI } from '../api';

const generatePdf = async (expedienteId: number) => {
  try {
    const blob = await expedientesAPI.generatePdf(expedienteId);
    
    // Descargar PDF
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `expediente_${expedienteId}.pdf`;
    link.click();
    window.URL.revokeObjectURL(url);
  } catch (error) {
    // Error manejado automáticamente
  }
};
```

---

## 🔧 Configuración de Variables de Entorno

### Desarrollo (`.env`)
```env
VITE_API_URL=http://localhost:8000
```

### Producción (`.env.production`)
```env
VITE_API_URL=https://expedientes.abogadosyasociados.net.pe
```

---

## 📝 Notas Importantes

### 1. Autenticación JWT
- El token se guarda automáticamente en `localStorage` con la clave `auth_token`
- Se añade automáticamente a todas las peticiones en el header `Authorization: Bearer {token}`
- Si el token expira (401), se limpia automáticamente y se redirige al login

### 2. Manejo de Errores
Todos los errores se manejan automáticamente:
- **401**: Sesión expirada → Logout automático
- **403**: Sin permisos → Notificación de error
- **404**: No encontrado → Notificación de error
- **422**: Error de validación → Muestra el primer error
- **500+**: Error del servidor → Notificación genérica

### 3. Tipos TypeScript
Todos los servicios están completamente tipados con TypeScript para mejor autocompletado y detección de errores.

### 4. Archivos y Blobs
Para descargar archivos o generar documentos:
1. El servicio retorna un `Blob`
2. Crear una URL temporal con `URL.createObjectURL()`
3. Crear un link `<a>` y hacer click programáticamente
4. Limpiar la URL con `URL.revokeObjectURL()`

---

## 🎯 Próximos Pasos

1. **Actualizar componentes existentes** para usar los nuevos servicios de API
2. **Probar la integración** con el backend Laravel
3. **Ajustar tipos** si es necesario según las respuestas reales del backend
4. **Implementar refresh token** si es necesario para sesiones largas

---

## 🐛 Troubleshooting

### Error de CORS
Si recibes errores de CORS, verifica que el backend Laravel tenga configurado:
```php
// config/cors.php
'allowed_origins' => ['http://localhost:5173'],
```

### Token no se envía
Verifica que el token esté guardado en localStorage:
```javascript
console.log(localStorage.getItem('auth_token'));
```

### Errores 401 constantes
El token puede estar expirado o ser inválido. Intenta hacer logout y login nuevamente.
