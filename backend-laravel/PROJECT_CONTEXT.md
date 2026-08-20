# Contexto del Proyecto — Abogados Asociados

## Descripción General

Sistema de gestión jurídica para el manejo de expedientes legales de un estudio de abogados en Perú. La aplicación permite crear, gestionar y dar seguimiento a expedientes jurídicos, generando documentos Word y PDF a partir de plantillas, y consolidando resoluciones en un documento consolidado por expediente.

**Stack tecnológico:**
- **Backend:** Laravel 11 (PHP 8.2+)
- **Frontend:** Vue 3 + TypeScript + Vite + Tailwind CSS
- **Base de datos:** MySQL
- **Autenticación:** JWT (php-open-source-saver/jwt-auth)
- **Procesamiento de documentos:** PHPWord, LibreOffice (headless), DomPDF, FPDI/FPDF

---

## Arquitectura

```
abogados-asociados-app/
├── app/
│   ├── Exceptions/                  # Excepciones personalizadas
│   ├── Http/
│   │   ├── Controllers/Api/         # API REST controllers
│   │   └── Middleware/               # Middleware de roles
│   ├── Models/                      # Modelos Eloquent
│   └── Services/                    # Servicios de dominio
├── database/
│   ├── migrations/                   # Migraciones de la BD
│   └── seeders/                      # Seeders (roles, usuarios)
├── resources/
│   ├── css/                          # Estilos globales Tailwind
│   ├── js/                           # SPA Vue 3
│   │   ├── api/                      # Cliente API (axios)
│   │   ├── components/               # Componentes reutilizables
│   │   ├── composables/              # Composables Vue
│   │   ├── pages/                    # Páginas de la SPA
│   │   ├── router/                   # Vue Router
│   │   └── utils/                    # Utilidades
│   └── views/                        # Blade (SPA entry)
├── routes/
│   ├── api.php                       # Rutas API protegidas con JWT
│   └── web.php                       # Fallback SPA
└── config/
    └── jwt.php                       # Configuración JWT
```

---

## Base de Datos

### Tablas Principales

#### `users`
- `id`, `nombre`, `username` (unique), `password` (hashed), `rol_id` (FK → roles)
- Implementa `JWTSubject` para tokens JWT
- Cada usuario tiene un `rol` (ADMIN o USUARIO)

#### `roles`
- `id`, `nombre` (unique) — Valores: `ADMIN`, `USUARIO`

#### `expedientes`
- `id`, `numero` (unique), `materia`, `juzgado`, `especialista`, `tercero`, `demandado`, `demandante`, `estado`
- `archivo` (boolean), `nombre_archivo`, `ultima_resolucion` (nullable int), `resolucion_detectada` (nullable int)
- Relación: `hasOne(Archivo)`, `hasMany(Resolucion)`

#### `archivos`
- `id`, `expediente_id` (FK), `nombre_archivo`, `tipo_archivo`, `documento_data` (longText — base64)
- Almacena el documento binario del expediente (PDF, DOC o DOCX)

#### `resoluciones`
- `id`, `expediente_id` (FK), `numero`, `estado` (pendiente/completada/base), `es_documento_base` (boolean)
- `nombre_archivo`, `tipo_archivo`, `documento_data` (nullable longText), `completada_at`
- Unique constraint: `(expediente_id, numero)`

#### `contacts`
- `id`, `nombre`, `email`, `telefono`, `mensaje` — Formulario de contacto

#### `services`
- `id`, `title`, `description`, `service_type`, `features` (JSON), `icon`, `is_active`

---

## Backend — Laravel API

### Autenticación
- Login por `username` + `password`, retorna JWT token
- Token TTL: 1440 min (24h), Refresh TTL: 20160 min (14 días)
- Rate limiting: 5 intentos/min en login
- Custom claims JWT: `{ rol: 'ADMIN' | 'USUARIO' }`
- Middleware `role:ADMIN` para rutas administrativas

### Endpoints API (`routes/api.php`)

#### Públicos
| Método | Ruta | Descripción |
|--------|------|-------------|
| POST | `/api/auth/login` | Login (throttle 5/min) |
| POST | `/api/contacto` | Enviar mensaje de contacto (throttle 10/min) |

#### Protegidos (JWT)
| Método | Ruta | Descripción |
|--------|------|-------------|
| POST | `/api/auth/logout` | Cerrar sesión |
| POST | `/api/auth/refresh` | Refrescar token |
| GET | `/api/auth/me` | Usuario actual |
| GET/POST/PUT/DELETE | `/api/expedientes` | CRUD expedientes |
| POST | `/api/expedientes/{id}/archivo` | Subir archivo |
| GET | `/api/expedientes/{id}/archivo/download` | Descargar archivo |
| GET | `/api/expedientes/{id}/resoluciones` | Historial resoluciones |
| POST | `/api/expedientes/{id}/resoluciones/confirmar-inicial` | Confirmar resolución inicial |
| POST | `/api/expedientes/{id}/resoluciones/siguiente` | Generar plantilla siguiente |
| GET | `/api/expedientes/{id}/resoluciones/{rid}/download` | Descargar resolución |
| POST | `/api/expedientes/{id}/resoluciones/{rid}/completar` | Completar resolución |
| GET | `/api/expedientes/{id}/word` | Generar Word |
| GET | `/api/expedientes/{id}/pdf` | Generar PDF |
| GET | `/api/estadisticas` | Dashboard stats |
| GET | `/api/estadisticas/expedientes-por-estado` | Stats por estado |
| GET | `/api/estadisticas/expedientes-por-tipo` | Stats por materia |

#### Solo ADMIN
| Método | Ruta | Descripción |
|--------|------|-------------|
| GET/POST/PUT/DELETE | `/api/usuarios` | CRUD usuarios |
| GET/DELETE | `/api/contacto` | Gestionar contactos |

### Servicios Principales

#### `DocumentConversionService`
- Convierte documentos Word (DOC/DOCX) a PDF
- `convertToPdf()` — Conversión con fallback a PHPWord
- `convertToPdfStrict()` — Requiere LibreOffice (para consolidación)
- `convertResolutionToPdfStrict()` — Para resoluciones: elimina cabecera generada antes de convertir
- `convertStoredDocumentToPdf()` — Convierte documentos almacenados en base64
- Validación de integridad PDF con FPDI

#### `LibreOfficeService`
- Ejecuta LibreOffice headless para conversiones
- Auto-detección de binario en PATH o configuración manual
- Conversión DOC → DOCX, DOC/DOCX → PDF
- Validación de firma del archivo de salida

#### `ResolutionTemplateService`
- Genera plantillas Word (.docx) con PHPWord para nuevas resoluciones
- Incluye campos del expediente (número, materia, juzgado, etc.)

#### `ResolutionNumberDetector`
- Detecta el número de resolución en documentos Word
- Soporta números escritos en letras (ej: "DOS", "VEINTIÚN")
- No detecta en PDF (requiere OCR)

#### `PdfMergeService`
- Consolida múltiples PDFs usando FPDI
- Preserva tamaños y orientaciones de página

#### `ResolutionHeaderStripper`
- Elimina la cabecera generada (expediente, materia, etc.) de documentos DOCX
- Manipula el XML interno del DOCX vía DOMDocument

#### `WordDocumentService`
- Genera documentos Word de expedientes con PHPWord

#### `SpanishNumberService`
- Convierte números enteros a letras en español

---

## Frontend — Vue 3 SPA

### Router (`resources/js/router/index.ts`)

| Ruta | Componente | Auth | Descripción |
|------|-----------|------|-------------|
| `/login` | Login.vue | No | Página de login |
| `/main` | Main.vue | Sí | Menú principal |
| `/expedientes` | Expedientes.vue | Sí | Lista de expedientes |
| `/expedientes/:id` | ExpedienteDetail.vue | Sí | Detalle de expediente |
| `/expedientes/:id/editar` | ExpedienteDetail.vue | Sí | Editar expediente |

### Páginas

#### `Login.vue`
- Formulario de usuario/contraseña
- Redirige a `/main` si ya está autenticado

#### `Main.vue`
- Menú principal con 4 acciones: Ver, Crear, Actualizar, Listar expedientes
- Botón de cerrar sesión

#### `Expedientes.vue`
- Tabla paginada con búsqueda
- Modal de creación/actualización de expedientes
- Visor de documentos: descarga el archivo, verifica si es PDF, si no lo es intenta convertir, fallback a descarga
- Crear/actualizar vía query params (`?create=true`, `?update=true`)

#### `ExpedienteDetail.vue`
- Vista detallada del expediente con información y historial de resoluciones
- Subir documento inicial (PDF/DOC/DOCX)
- Confirmar resolución inicial (número detectado o manual)
- Generar plantilla de resolución → descargar DOCX
- Completar resolución: subir Word terminado → consolidar PDF
- Descargar archivos y generar PDF
- Eliminar expediente con confirmación
- Actualizar estado del expediente

### Componentes Reutilizables

| Componente | Descripción |
|-----------|-------------|
| `Button.vue` | Botón con variantes (primary/secondary/danger/outline), loading, tamaños |
| `Modal.vue` | Modal con Teleport, tamaños (sm/md/lg/xl/full) |
| `Table.vue` | Tabla genérica con slots para celdas personalizadas |
| `ProgressBar.vue` | Barra de progreso para uploads |
| `LoadingSpinner.vue` | Spinner de carga |
| `ExpedienteForm.vue` | Formulario de creación/edición de expedientes |
| `FileUploader.vue` | Uploader con drag & drop, validación de tipos/tamaño |
| `Layout.vue` | Layout con header y navegación |
| `ToastContainer.vue` | Notificaciones toast (success/error/info) |

### Composables

| Composable | Descripción |
|-----------|-------------|
| `useAuth` | Estado de autenticación global (user, token, isAuthenticated, isAdmin) |
| `useToast` | Sistema de notificaciones toast |
| `useDocumentGeneration` | Generación de Word/PDF |
| `useEstadisticas` | Estadísticas del dashboard |

### API Client (`resources/js/api/`)

Cliente axios centralizado con:
- Interceptor de request: agrega JWT token del localStorage
- Interceptor de response: manejo de errores 401, 403, 404, 422, 500
- Auto-logout en sesión expirada
- `VITE_API_URL` vacío = mismo origen de Laravel

### Utilidades

| Utilidad | Descripción |
|---------|-------------|
| `apiError.ts` | Extrae mensajes de error de respuestas API |
| `fileDownload.ts` | Descarga de blobs, validación de tipo/tamaño |
| `pdf.ts` | Validación de firma PDF, generación de nombre |
| `sanitize.ts` | Sanitización y truncado de texto |

---

## Flujo Principal de Resoluciones

1. **Crear expediente** → se crea sin archivo
2. **Subir documento inicial** (DOC/DOCX/PDF) → el sistema detecta el número de resolución
3. **Confirmar resolución inicial** → el usuario confirma/corrige el número detectado
4. **Generar siguiente resolución** → descarga plantilla Word con campos del expediente
5. **Completar resolución** → subir el Word terminado → se consolida el PDF del expediente
6. El PDF consolidado incluye todas las resoluciones anteriores + la nueva

### Estados de Resolución
- `base` — Documento original del expediente (primera carga)
- `pendiente` — Plantilla generada, pendiente de subir Word terminado
- `completada` — Word subido y consolidado en el PDF del expediente

---

## Seguridad

- JWT con blacklist habilitada
- Rate limiting en login (5/min) y contacto (10/min)
- Roles: ADMIN y USUARIO con middleware
- No se exponen datos binarios en respuestas JSON (`hidden` en modelos)
- Validación de MIME types por extensión (no browser MIME)
- Sanitización de nombres de archivo para descarga
- XSS: sanitize.ts para texto en frontend

---

## Dependencias Clave

### Backend
- `laravel/framework` ^11.0
- `php-open-source-saver/jwt-auth` ^2.8
- `phpoffice/phpword` ^1.4
- `barryvdh/laravel-dompdf` ^3.1
- `setasign/fpdf` ^1.8 + `setasign/fpdi` ^2.6

### Frontend
- `vue` ^3.5.13
- `vue-router` ^4.5.0
- `axios` ^1.11.0
- `@lucide/vue` ^1.31.0 (iconos)
- `tailwindcss` ^3.4.17

---

## Variables de Entorno Importantes

| Variable | Descripción |
|---------|-------------|
| `APP_URL` | URL base de Laravel |
| `VITE_API_URL` | URL del API (vacío = mismo origen) |
| `JWT_SECRET` | Secreto para tokens JWT |
| `JWT_TTL` | Duración del token (min) |
| `LIBREOFFICE_BINARY` | Ruta a LibreOffice (auto-detect si vacío) |
| `LIBREOFFICE_TIMEOUT` | Timeout de LibreOffice (seg) |
| `FRONTEND_URL` | URL del frontend para CORS |

---

## Seeders

- **RoleSeeder:** Crea `ADMIN` y `USUARIO`
- **UserSeeder:** Crea `admin`/`admin123` y `usuario`/`usuario123`

---

## Comandos Útiles

```bash
# Desarrollo
php artisan serve          # Backend
npm run dev                # Frontend (Vite)

# Build
npm run build              # Build producción
npm run typecheck          # TypeScript check

# Base de datos
php artisan migrate --seed
php artisan jwt:secret     # Generar secreto JWT
```
