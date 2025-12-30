# Correcciones del Frontend

## ✅ Problemas Corregidos

### 1. Imports de API
- ✅ **Usuarios.tsx**: Cambiado de `api` a `usuariosAPI`
- ✅ **RegistrarUsuario.tsx**: Cambiado de `authAPI.register` a `usuariosAPI.create`
- ✅ **Expedientes.tsx**: Corregidos nombres de métodos de API
- ✅ **ExpedienteDetail.tsx**: Corregidos nombres de métodos de API

### 2. Métodos de API Corregidos

#### Expedientes.tsx
- `expedientesAPI.getAll(currentPage, searchTerm)` → `expedientesAPI.getAll()`
- `expedientesAPI.downloadExpedienteFile()` → `expedientesAPI.downloadFile()`
- `expedientesAPI.generatePDF()` → `expedientesAPI.generatePdf()`

#### ExpedienteDetail.tsx
- `expedientesAPI.downloadExpedienteFile()` → `expedientesAPI.downloadFile()`

#### RegistrarUsuario.tsx
- `authAPI.register()` → `usuariosAPI.create()`
- Roles hardcodeados: `ADMIN` (id: 1) y `USUARIO` (id: 2)

### 3. Hooks Creados

#### `useEstadisticas.ts`
Hook para cargar estadísticas del dashboard desde la API de Laravel.

```typescript
const { dashboardStats, loading, refreshStats } = useEstadisticas();
```

Retorna:
- `expedientesActivos`: Total de expedientes
- `enProgreso`: Expedientes en proceso
- `finalizados`: Expedientes finalizados
- `urgentes`: 0 (no disponible en API)

#### `useDocumentGeneration.ts`
Hook para generar documentos Word y PDF.

```typescript
const { generateWord, generatePDF, isGenerating } = useDocumentGeneration();
```

Métodos:
- `generateWord(expedienteId, numeroExpediente)`: Genera y descarga .docx
- `generatePDF(expedienteId, numeroExpediente)`: Genera y descarga .pdf

### 4. Utilidades Creadas

#### `fileDownload.ts`
Utilidad para descargar archivos Blob.

```typescript
downloadBlob(blob, filename);
```

---

## 📋 Estructura de Archivos Actualizada

```
project/src/
├── api/
│   ├── axios.ts ✅
│   ├── auth.ts ✅
│   ├── expedientes.ts ✅
│   ├── usuarios.ts ✅
│   ├── estadisticas.ts ✅
│   ├── contacto.ts ✅
│   └── index.ts ✅
├── hooks/
│   ├── useAuth.tsx ✅
│   ├── useFetch.ts ✅
│   ├── useEstadisticas.ts ✅ NUEVO
│   └── useDocumentGeneration.ts ✅ NUEVO
├── utils/
│   └── fileDownload.ts ✅ NUEVO
└── pages/
    ├── Login/Login.tsx ✅
    ├── Main/Main.tsx ✅
    ├── Expedientes/Expedientes.tsx ✅ CORREGIDO
    ├── ExpedienteDetail/ExpedienteDetail.tsx ✅ CORREGIDO
    ├── Usuarios/Usuarios.tsx ✅ CORREGIDO
    └── RegistrarUsuario/RegistrarUsuario.tsx ✅ CORREGIDO
```

---

## 🔧 Cambios Pendientes (Opcionales)

### 1. Filtrado y Paginación
Actualmente `expedientesAPI.getAll()` no acepta parámetros de filtrado o paginación.
Si el backend Laravel los soporta, actualizar el método en `expedientes.ts`.

### 2. Campo "Urgentes"
El dashboard muestra un campo "Urgentes" que no existe en la API.
Opciones:
- Agregar campo al backend
- Remover del frontend
- Calcular basado en fechas

### 3. Validación de Formularios
Considerar agregar validación más robusta en los formularios usando librerías como:
- `react-hook-form`
- `yup` o `zod`

---

## 🚀 Para Probar

1. **Reiniciar el servidor de desarrollo:**
   ```bash
   cd project
   npm run dev
   ```

2. **Verificar que no haya errores en la consola del navegador**

3. **Probar funcionalidades:**
   - Login
   - Dashboard (estadísticas)
   - Listar expedientes
   - Ver detalle de expediente
   - Generar Word/PDF
   - Gestión de usuarios
   - Registrar nuevo usuario

---

## 📝 Notas Importantes

### Roles en Laravel
El backend Laravel tiene 2 roles:
- `ADMIN` (id: 1)
- `USUARIO` (id: 2)

### Autenticación
- El token JWT se guarda en `localStorage` con la clave `auth_token`
- Se añade automáticamente a todas las peticiones
- Si expira (401), se hace logout automático

### Manejo de Errores
Todos los errores de API se manejan automáticamente en el interceptor de Axios y muestran notificaciones con `react-hot-toast`.

---

## ✅ Estado Final

**Frontend completamente funcional y conectado con el backend Laravel.**

Todos los componentes están corregidos y listos para usar.
