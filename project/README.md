# Sistema Frontend - Abogados Asociados

Una aplicación web moderna desarrollada con React + Vite que reemplaza la UI Java existente, manteniendo toda la lógica del backend para la gestión de expedientes jurídicos y generación de documentos.

## 🚀 Características

- **Frontend Moderno**: React 18 + TypeScript + Vite
- **Gestión de Estado**: React Context + Custom Hooks
- **UI/UX**: Tailwind CSS + Componentes reutilizables
- **Routing**: React Router DOM con rutas protegidas
- **API**: Axios con interceptores y manejo de errores
- **Archivos**: Upload/Download con progress indicators
- **Tests**: Vitest + React Testing Library
- **Documentos**: Generación de Word/PDF integrada con backend

## 📋 Requisitos

- Node.js 18+ 
- NPM 8+
- Backend API corriendo en puerto 8080

## 🛠️ Instalación y Configuración

### 1. Clonar e instalar dependencias

```bash
git clone <repository-url>
cd abogados-asociados-frontend
npm install
```

### 2. Configurar variables de entorno

Crear archivo `.env` en la raíz del proyecto:

```env
# API Configuration
VITE_API_URL=http://localhost:8080

# App Configuration  
VITE_APP_NAME=Abogados Asociados
VITE_MAX_FILE_SIZE=10485760
```

### 3. Iniciar en modo desarrollo

```bash
npm run dev
```

La aplicación estará disponible en `http://localhost:5173`

### 4. Build para producción

```bash
npm run build
npm run preview
```

## 🏗️ Arquitectura del Proyecto

```
src/
├── api/                    # Configuración Axios y endpoints
├── components/             # Componentes reutilizables
│   ├── UI/                # Componentes base (Button, Modal, Table)
│   ├── Layout/            # Layout principal
│   ├── ExpedienteForm/    # Formulario de expedientes
│   └── FileUploader/      # Carga de archivos
├── hooks/                 # Custom hooks
│   ├── useAuth.ts         # Hook de autenticación
│   ├── useFetch.ts        # Hook para llamadas API
│   └── useDocumentGeneration.ts # Hook para generación docs
├── pages/                 # Páginas principales
│   ├── Login/
│   ├── Main/
│   ├── Expedientes/
│   ├── ExpedienteDetail/
│   └── RegistrarUsuario/
├── utils/                 # Utilidades
└── App.tsx               # Componente raíz
```

## 🔌 API Endpoints

### Expedientes
- `GET /api/expedientes` - Lista de expedientes
- `GET /api/expedientes/:id` - Detalle de expediente
- `POST /api/expedientes` - Crear expediente
- `PUT /api/expedientes/:id` - Actualizar expediente
- `DELETE /api/expedientes/:id` - Eliminar expediente

### Archivos
- `POST /api/expedientes/:id/archivo` - Subir archivo (multipart/form-data)
- `GET /api/expedientes/:id/archivo/:archivoId/download` - Descargar archivo

### Documentos
- `POST /api/expedientes/:id/word` - Generar documento Word
- `POST /api/expedientes/:id/pdf` - Generar PDF
- `POST /api/expedientes/:id/word/resolucion` - Añadir resolución

### Usuarios
- `GET /api/usuarios` - Lista usuarios (temporal para auth)
- `POST /api/usuarios` - Registrar usuario

## 🧪 Testing

```bash
# Ejecutar tests
npm run test

# Tests con coverage
npm run test:coverage

# Tests en modo watch
npm run test:watch
```

### Ejemplos de tests incluidos:
- `tests/Expedientes.test.tsx` - Componente principal de expedientes
- `tests/ExpedienteForm.test.tsx` - Formulario de expedientes
- `tests/setup.ts` - Configuración global de tests

## 📱 Funcionalidades Principales

### 1. Gestión de Expedientes
- ✅ Lista con paginación y búsqueda
- ✅ Crear/Editar expedientes con validación
- ✅ Vista detalle completa
- ✅ Eliminación con confirmación

### 2. Manejo de Archivos
- ✅ Upload con drag & drop
- ✅ Validación de tamaño y tipo
- ✅ Barra de progreso
- ✅ Descarga directa

### 3. Generación de Documentos
- ✅ Generar documento Word
- ✅ Generar PDF
- ✅ Añadir resoluciones
- ✅ Descarga automática

### 4. Autenticación
- ✅ Login con validación
- ✅ Rutas protegidas
- ✅ Persistencia de sesión
- ✅ Registro de usuarios

## 🎨 Componentes UI

### Componentes Base
- `Button` - Botón con variantes y estados
- `Modal` - Modal responsive con tamaños
- `Table` - Tabla con paginación y ordenamiento
- `LoadingSpinner` - Indicador de carga
- `ProgressBar` - Barra de progreso

### Componentes Especializados
- `ExpedienteForm` - Formulario completo de expedientes
- `FileUploader` - Carga de archivos con validación
- `Layout` - Layout principal con sidebar

## 🔧 Configuración Adicional

### ESLint y TypeScript
El proyecto incluye configuración completa de ESLint y TypeScript para desarrollo profesional.

### Tailwind CSS
Configuración optimizada con clases de utilidad para desarrollo rápido y consistente.

### Axios Interceptors
- Manejo automático de tokens de autenticación
- Interceptor de errores con redirección automática
- Toast notifications integradas

## 🚀 Despliegue

### Build Estático
```bash
npm run build
# Archivos en /dist listos para servir
```

### Empaquetado con Electron (Opcional)
Para crear ejecutable de escritorio:

```bash
# Instalar Electron
npm install -D electron electron-builder

# Configurar package.json con scripts de Electron
# Crear archivo main.js para proceso principal
# Build y empaquetar
npm run electron:build
```

## 📋 Criterios de Aceptación (QA)

### ✅ Funcionalidades Completas
- [x] CRUD de expedientes funcional
- [x] Subir y descargar archivos Word
- [x] Generar PDF con información completa
- [x] Registro de usuarios funcional
- [x] Sin errores en consola en producción
- [x] Tests unitarios pasando

### 🔍 Validaciones
- [x] Campos obligatorios con validación
- [x] Números de expediente únicos
- [x] Límites de tamaño de archivo
- [x] Manejo de errores HTTP

### 🎯 UX/UI
- [x] Loading indicators
- [x] Toasts de confirmación
- [x] Responsive design
- [x] Accesibilidad básica

## 🐛 Troubleshooting

### Backend no disponible
La app maneja automáticamente cuando el backend no está disponible, mostrando mensajes apropiados.

### Archivos grandes
Configurar `VITE_MAX_FILE_SIZE` según necesidades del servidor.

### CORS Issues
Verificar configuración CORS en el backend para permitir requests desde `http://localhost:5173`

## 🤝 Contribución

1. Fork del proyecto
2. Crear feature branch (`git checkout -b feature/nueva-funcionalidad`)
3. Commit cambios (`git commit -am 'Agregar nueva funcionalidad'`)
4. Push a branch (`git push origin feature/nueva-funcionalidad`)
5. Crear Pull Request

## 📄 Licencia

Este proyecto es privado y confidencial de Abogados Asociados.

## 📞 Soporte

Para soporte técnico o preguntas sobre la implementación, contactar al equipo de desarrollo.

---

**Versión:** 2.0  
**Última actualización:** Enero 2025