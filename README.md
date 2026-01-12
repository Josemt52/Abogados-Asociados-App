# Abogados Asociados - Sistema de Gestión Jurídica

Sistema integral de gestión de expedientes jurídicos con backend en Spring Boot y frontend en React + TypeScript.

![Version](https://img.shields.io/badge/version-2.0.0-blue.svg)
![License](https://img.shields.io/badge/license-MIT-green.svg)

---

## 📋 Tabla de Contenidos

- [Descripción General](#-descripción-general)
- [Arquitectura del Sistema](#-arquitectura-del-sistema)
- [Tecnologías](#-tecnologías)
- [Estructura del Proyecto](#-estructura-del-proyecto)
- [Instalación y Configuración](#-instalación-y-configuración)
- [Backend - Documentación Técnica](#-backend---documentación-técnica)
- [Frontend - Documentación](#-frontend---documentación)
- [API Endpoints](#-api-endpoints)
- [Deployment](#-deployment)
- [Seguridad](#-seguridad)
- [Contribuir](#-contribuir)

---

## 📖 Descripción General

**Abogados Asociados** es un sistema web moderno para la gestión integral de expedientes jurídicos que permite:

✅ **Gestión de Expedientes**: Crear, editar, consultar y archivar expedientes legales  
✅ **Gestión de Usuarios**: Administración de usuarios con roles (Admin/Usuario)  
✅ **Autenticación Segura**: Sistema JWT con BCrypt para passwords  
✅ **Gestión de Archivos**: Upload y descarga de documentos asociados a expedientes  
✅ **Generación de Documentos**: Exportación a PDF y Word con plantillas personalizadas  
✅ **Estadísticas**: Dashboard con métricas y actividad reciente  
✅ **Responsive Design**: Interfaz moderna con TailwindCSS

---

## 🏗️ Arquitectura del Sistema

```
┌─────────────────┐         ┌──────────────────┐         ┌─────────────┐
│                 │         │                  │         │             │
│  React Frontend │ ◄─────► │  Spring Boot API │ ◄─────► │   MySQL DB  │
│  (Vite + TS)    │  REST   │  (Java 17)       │  JPA    │             │
│                 │         │                  │         │             │
└─────────────────┘         └──────────────────┘         └─────────────┘
         │                           │
         │                           │
         ▼                           ▼
  Nginx (Producción)        JWT Authentication
                           BCrypt Password Hash
```

### Flujo de Datos

1. **Cliente** → Envía request HTTP al backend (con JWT token)
2. **Security Filter** → Valida JWT token y establece contexto de seguridad
3. **Controller** → Recibe request y valida datos
4. **Repository** → Accede a la base de datos vía JPA
5. **Response** → Devuelve JSON al cliente

---

## 🛠️ Tecnologías

### Backend

| Tecnología | Versión | Propósito |
|------------|---------|-----------|
| **Java** | 17 | Lenguaje de programación |
| **Spring Boot** | 3.3.0 | Framework principal |
| **Spring Security** | 3.3.0 | Autenticación y autorización |
| **Spring Data JPA** | 3.3.0 | ORM para base de datos |
| **JWT (jjwt)** | 0.12.3 | Tokens de autenticación |
| **MySQL Connector** | 8.0.33 | Driver de base de datos |
| **Apache POI** | 5.2.3 | Generación de documentos Word |
| **Apache PDFBox** | 2.0.29 | Generación de documentos PDF |
| **Jsoup** | 1.16.1 | Parsing de HTML |
| **Lombok** | Auto | Reducción de boilerplate |
| **Maven** | 3.6+ | Gestión de dependencias |

### Frontend

| Tecnología | Versión | Propósito |
|------------|---------|-----------|
| **React** | 18.3.1 | Framework UI |
| **TypeScript** | 5.5.3 | Tipado estático |
| **Vite** | 5.4.2 | Build tool y dev server |
| **TailwindCSS** | 3.4.1 | Framework CSS |
| **Axios** | 1.11.0 | Cliente HTTP |
| **React Router** | 7.8.2 | Enrutamiento |
| **React Hot Toast** | 2.6.0 | Notificaciones |
| **Lucide React** | 0.344.0 | Iconos |
| **Vitest** | 1.6.0 | Testing framework |

### Infraestructura

- **Base de Datos**: MySQL 8.0+
- **Servidor Web**: Nginx (producción)
- **Runtime**: Java 17 + Node.js 18+

---

## 📁 Estructura del Proyecto

```
Abogados-Asociados-App/
│
├── backend/                          # Backend Spring Boot
│   ├── src/
│   │   ├── main/
│   │   │   ├── java/com/abogados/backend/
│   │   │   │   ├── BackendApplication.java
│   │   │   │   ├── config/
│   │   │   │   │   └── CorsConfig.java          # Configuración CORS
│   │   │   │   ├── controllers/                 # Endpoints REST
│   │   │   │   │   ├── AuthController.java      # Login
│   │   │   │   │   ├── UsuarioController.java   # Gestión usuarios
│   │   │   │   │   ├── ExpedienteController.java # Gestión expedientes
│   │   │   │   │   ├── DocumentoController.java  # Generación docs
│   │   │   │   │   ├── EstadisticasController.java
│   │   │   │   │   ├── RolController.java
│   │   │   │   │   ├── ContactController.java
│   │   │   │   │   └── GlobalExceptionHandler.java
│   │   │   │   ├── dto/                         # Data Transfer Objects
│   │   │   │   │   ├── ExpedienteDTO.java
│   │   │   │   │   └── UserRegistrationRequest.java
│   │   │   │   ├── models/                      # Entidades JPA
│   │   │   │   │   ├── Usuario.java
│   │   │   │   │   ├── Rol.java
│   │   │   │   │   ├── Expediente.java
│   │   │   │   │   ├── Archivo.java
│   │   │   │   │   ├── Contact.java
│   │   │   │   │   └── Service.java
│   │   │   │   ├── repositories/                # Acceso a datos
│   │   │   │   │   ├── UsuarioRepository.java
│   │   │   │   │   ├── RolRepository.java
│   │   │   │   │   ├── ExpedienteRepository.java
│   │   │   │   │   ├── ArchivoRepository.java
│   │   │   │   │   └── ContactRepository.java
│   │   │   │   ├── security/                    # Seguridad
│   │   │   │   │   ├── SecurityConfig.java      # Config Spring Security
│   │   │   │   │   ├── JwtService.java          # Generación/validación JWT
│   │   │   │   │   └── JwtAuthenticationFilter.java
│   │   │   │   └── services/                    # Lógica de negocio
│   │   │   │       ├── PDFDocumentService.java
│   │   │   │       └── WordDocumentService.java
│   │   │   └── resources/
│   │   │       ├── application.properties       # Config principal
│   │   │       └── application-prod.properties
│   │   └── test/                                # Tests (pendiente)
│   ├── .env.example                             # Template variables entorno
│   ├── .env.production.example
│   ├── pom.xml                                  # Dependencias Maven
│   └── README.md
│
├── project/                          # Frontend React
│   ├── src/
│   │   ├── components/              # Componentes reutilizables
│   │   │   ├── UI/                  # Componentes UI base
│   │   │   ├── Layout/              # Layout principal
│   │   │   ├── ProtectedRoute/      # Protección de rutas
│   │   │   ├── ExpedienteForm/
│   │   │   └── FileUploader/
│   │   ├── pages/                   # Páginas de la aplicación
│   │   │   ├── Login/
│   │   │   ├── Main/
│   │   │   ├── Expedientes/
│   │   │   ├── ExpedienteDetail/
│   │   │   ├── Usuarios/
│   │   │   └── RegistrarUsuario/
│   │   ├── hooks/                   # Custom React Hooks
│   │   │   ├── useAuth.tsx          # Hook de autenticación
│   │   │   ├── useFetch.ts
│   │   │   ├── useEstadisticas.ts
│   │   │   └── useDocumentGeneration.ts
│   │   ├── utils/                   # Utilidades
│   │   ├── App.tsx                  # Componente raíz
│   │   ├── main.tsx                 # Entry point
│   │   └── index.css
│   ├── .env.example
│   ├── package.json
│   ├── vite.config.ts
│   └── README.md
│
├── deploy.sh                        # Script de deployment
├── DEPLOYMENT_GUIDE.md              # Guía de deployment
└── README.md                        # Este archivo
```

---

## 🚀 Instalación y Configuración

### Pre-requisitos

- **Java 17** o superior
- **Maven 3.6+**
- **Node.js 18+** y npm 8+
- **MySQL 8.0+**
- **Git**

### 1. Clonar el Repositorio

```bash
git clone https://github.com/tu-usuario/Abogados-Asociados-App.git
cd Abogados-Asociados-App
```

### 2. Configurar Backend

#### 2.1 Crear Base de Datos

```sql
CREATE DATABASE abogados_asociados CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

#### 2.2 Configurar Variables de Entorno

```bash
cd backend
cp .env.example .env
```

Edita `backend/.env` con tus valores:

```properties
# Base de datos
DB_URL=jdbc:mysql://localhost:3306/abogados_asociados?useSSL=false&allowPublicKeyRetrieval=true
DB_USERNAME=root
DB_PASSWORD=tu_password

# JWT (Genera uno seguro con: openssl rand -base64 32)
JWT_SECRET=tu-secret-seguro-de-minimo-256-bits
JWT_EXPIRATION=86400000
```

> ⚠️ **CRÍTICO**: Nunca subas el archivo `.env` al repositorio. Ya está en `.gitignore`.

#### 2.3 Compilar y Ejecutar Backend

```bash
# Compilar
mvn clean package -DskipTests

# Ejecutar
java -jar target/backend-1.0.0.jar

# O directamente con Maven
mvn spring-boot:run
```

El backend arrancará en: **http://localhost:8019**

### 3. Configurar Frontend

```bash
cd project

# Instalar dependencias
npm install

# Copiar archivo de configuración
cp .env.example .env
```

Edita `project/.env`:

```properties
VITE_API_URL=http://localhost:8019
VITE_APP_NAME=Abogados Asociados
VITE_MAX_FILE_SIZE=10485760
VITE_NODE_ENV=development
```

#### Ejecutar Frontend

```bash
# Modo desarrollo
npm run dev

# Build para producción
npm run build:prod
```

El frontend estará disponible en: **http://localhost:5173**

---

## 🔧 Backend - Documentación Técnica

### Arquitectura de Capas

```
┌──────────────────────────────────────┐
│         Controllers                  │  ← REST API Endpoints
│  (AuthController, UsuarioController) │
└──────────────┬───────────────────────┘
               │
               ▼
┌──────────────────────────────────────┐
│         Services (pendiente)         │  ← Lógica de negocio
│  (Actualmente lógica en controllers) │
└──────────────┬───────────────────────┘
               │
               ▼
┌──────────────────────────────────────┐
│        Repositories                  │  ← Acceso a datos (JPA)
│  (UsuarioRepository, ExpedienteRepo) │
└──────────────┬───────────────────────┘
               │
               ▼
┌──────────────────────────────────────┐
│          Models/Entities             │  ← Entidades JPA
│  (Usuario, Expediente, Archivo)      │
└──────────────────────────────────────┘
```

### Modelos de Datos Principales

#### **Usuario**
```java
@Entity
public class Usuario {
    @Id @GeneratedValue
    private Long id;
    private String nombre;        // Nombre completo
    private String username;      // Usuario único
    private String password;      // BCrypt hash
    @ManyToOne
    private Rol rol;             // Relación con Rol
}
```

#### **Expediente**
```java
@Entity
public class Expediente {
    @Id @GeneratedValue
    private Long id;
    private String numeroExpediente;
    private String materia;
    private String cliente;
    private String demandado;
    private String juzgado;
    private LocalDate fechaInicio;
    private String estado;       // EN_PROCESO, GANADO, PERDIDO, etc.
    @ManyToOne
    private Usuario responsable;
    @OneToMany
    private List<Archivo> archivos;
}
```

#### **Archivo**
```java
@Entity
public class Archivo {
    @Id @GeneratedValue
    private Long id;
    private String nombre;
    private String tipo;         // MIME type
    @Lob
    private byte[] contenido;   // Archivo binario
    @ManyToOne
    private Expediente expediente;
}
```

### Seguridad

#### JWT Authentication Flow

```
1. Login (POST /api/auth/login)
   ├─ Validar username/password con BCrypt
   ├─ Generar JWT token (firma con JWT_SECRET)
   └─ Retornar { user, token }

2. Request Autenticado (cualquier endpoint protegido)
   ├─ JwtAuthenticationFilter intercepta
   ├─ Extrae token del header "Authorization: Bearer <token>"
   ├─ Valida firma y expiración
   ├─ Establece SecurityContext
   └─ Permite acceso si es válido
```

#### Configuración de Seguridad

En `SecurityConfig.java`:

```java
@Bean
public SecurityFilterChain securityFilterChain(HttpSecurity http) {
    return http
        .csrf(csrf -> csrf.disable())  // Deshabilitado para API REST
        .authorizeHttpRequests(auth -> auth
            .requestMatchers("/api/auth/**").permitAll()
            .requestMatchers("/api/usuarios/**").hasRole("ADMIN")
            .requestMatchers("/api/expedientes/**").authenticated()
            .anyRequest().authenticated()
        )
        .sessionManagement(session -> 
            session.sessionCreationPolicy(SessionCreationPolicy.STATELESS)
        )
        .addFilterBefore(jwtAuthFilter, UsernamePasswordAuthenticationFilter.class)
        .build();
}
```

### CORS Configuration

Configurado en `CorsConfig.java` para permitir requests desde el frontend:

```java
@Configuration
public class CorsConfig {
    @Bean
    public WebMvcConfigurer corsConfigurer() {
        return new WebMvcConfigurer() {
            @Override
            public void addCorsMappings(CorsRegistry registry) {
                registry.addMapping("/api/**")
                    .allowedOrigins("http://localhost:5173", "https://expedientes.abogadosyasociados.net.pe")
                    .allowedMethods("GET", "POST", "PUT", "DELETE")
                    .allowedHeaders("*")
                    .allowCredentials(true);
            }
        };
    }
}
```

---

## 🎨 Frontend - Documentación

### Componentes Principales

#### **useAuth Hook**
Gestiona el estado de autenticación global:

```typescript
const { user, token, isAuthenticated, login, logout } = useAuth();
```

- Almacena usuario y token en localStorage
- Provee contexto de autenticación a toda la app
- Valida roles para control de acceso

#### **ProtectedRoute**
Protege rutas que requieren autenticación:

```typescript
<Route path="/expedientes" element={
  <ProtectedRoute>
    <Expedientes />
  </ProtectedRoute>
} />
```

#### **Layout**
Estructura común con sidebar y header:
- Navegación lateral con iconos
- Información del usuario logueado
- Botón de logout

### Páginas

| Ruta | Componente | Descripción | Rol Requerido |
|------|-----------|-------------|---------------|
| `/login` | Login | Inicio de sesión | Público |
| `/main` | Main | Dashboard principal | Usuario |
| `/expedientes` | Expedientes | Lista de expedientes | Usuario |
| `/expedientes/:id` | ExpedienteDetail | Detalle del expediente | Usuario |
| `/usuarios` | Usuarios | Gestión de usuarios | **Admin** |
| `/usuarios/registrar` | RegistrarUsuario | Registro de usuario | **Admin** |

---

## 📡 API Endpoints

### Autenticación

#### POST `/api/auth/login`
Login de usuario.

**Request:**
```json
{
  "username": "Jose",
  "password": "Morganella.12334"
}
```

**Response 200:**
```json
{
  "user": {
    "id": 1,
    "nombre": "Jose Tejada",
    "username": "Jose",
    "rol": {
      "id": 1,
      "nombre": "admin"
    }
  },
  "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."
}
```

**Response 401:**
```json
{
  "message": "Credenciales inválidas"
}
```

---

### Usuarios

#### GET `/api/usuarios`
Listar todos los usuarios (solo Admin).

**Headers:**
```
Authorization: Bearer <token>
```

**Response 200:**
```json
[
  {
    "id": 1,
    "nombre": "Jose Tejada",
    "username": "Jose",
    "rol": { "id": 1, "nombre": "admin" }
  },
  ...
]
```

#### POST `/api/usuarios`
Crear nuevo usuario (solo Admin).

**Request:**
```json
{
  "nombre": "Juan Pérez",
  "username": "juan",
  "password": "SecurePass123",
  "rol": "usuario"
}
```

#### PUT `/api/usuarios/:id`
Actualizar usuario (solo Admin).

**Request:**
```json
{
  "nombre": "Juan Pérez Actualizado",
  "username": "juan",
  "rol": "admin",
  "password": "NewPassword123" // Opcional
}
```

#### DELETE `/api/usuarios/:id`
Eliminar usuario (solo Admin).

---

### Expedientes

#### GET `/api/expedientes`
Listar expedientes del usuario (o todos si es Admin).

**Response 200:**
```json
[
  {
    "id": 1,
    "numeroExpediente": "EXP-2024-001",
    "materia": "Derecho Civil",
    "cliente": "María López",
    "demandado": "Carlos Ruiz",
    "juzgado": "Juzgado Civil de Lima",
    "fechaInicio": "2024-01-15",
    "estado": "EN_PROCESO",
    "responsable": { "id": 1, "nombre": "Jose Tejada" }
  },
  ...
]
```

#### POST `/api/expedientes`
Crear expediente.

#### GET `/api/expedientes/:id`
Obtener detalles de expediente.

#### PUT `/api/expedientes/:id`
Actualizar expediente.

#### DELETE `/api/expedientes/:id`
Eliminar expediente.

---

### Archivos

#### POST `/api/expedientes/:expedienteId/archivos`
Subir archivo a expediente.

**Request:**
```
Content-Type: multipart/form-data

archivo: [binary file]
```

#### GET `/api/expedientes/:expedienteId/archivos`
Listar archivos de expediente.

#### DELETE `/api/archivos/:id`
Eliminar archivo.

---

### Estadísticas

#### GET `/api/estadisticas`
Obtener estadísticas del sistema.

**Response:**
```json
{
  "totalExpedientes": 45,
  "expedientesActivos": 32,
  "expedientesGanados": 10,
  "expedientesPerdidos": 3,
  "porEstado": {
    "EN_PROCESO": 32,
    "GANADO": 10,
    "PERDIDO": 3
  }
}
```

---

## 🚢 Deployment

Ver [DEPLOYMENT_GUIDE.md](DEPLOYMENT_GUIDE.md) para instrucciones completas.

### Resumen

#### Backend

```bash
# 1. Crear .env en servidor de producción
cd /var/www/abogados-backend
nano .env

# 2. Build
mvn clean package -DskipTests

# 3. Deploy JAR
sudo cp target/backend-1.0.0.jar /var/www/abogados-backend/

# 4. Configurar systemd service con EnvironmentFile
sudo systemctl restart abogados-backend
```

#### Frontend

```bash
# Build
npm run build:prod

# Deploy a Nginx
sudo cp -r dist/* /var/www/abogados-frontend/dist/
sudo systemctl reload nginx
```

---

## 🔐 Seguridad

### Implementado ✅

- ✅ Autenticación JWT con tokens firmados
- ✅ Passwords con hash BCrypt (factor 10)
- ✅ Secrets externalizados a variables de entorno
- ✅ CORS configurado para dominios permitidos
- ✅ Validación de roles a nivel de endpoint
- ✅ CSRF deshabilitado (API stateless)
- ✅ Logs seguros (sin passwords)

### Pendiente ⚠️

- ⚠️ Rate limiting para prevenir brute force
- ⚠️ HTTPS obligatorio en producción
- ⚠️ Refresh tokens
- ⚠️ Auditoría de cambios (logs de acciones)
- ⚠️ Validación de fortaleza de password
- ⚠️ 2FA (opcional)

---

## 🤝 Contribuir

### Buenas Prácticas

1. **Commits**: Usar mensajes descriptivos en español
2. **Branches**: `feature/nombre-feature` o `fix/nombre-bug`
3. **Pull Requests**: Describir claramente los cambios
4. **Tests**: Agregar tests para nuevo código (cuando se implemente testing)

### Proceso

```bash
# 1. Fork del proyecto
# 2. Crear branch
git checkout -b feature/nueva-funcionalidad

# 3. Hacer cambios y commit
git commit -m "feat: agregar funcionalidad X"

# 4. Push y crear PR
git push origin feature/nueva-funcionalidad
```

---

## 📝 Licencia

MIT License - Ver archivo LICENSE para más detalles.

---

## 👥 Autor

**Jose Tejada**  
📧 josemigueltejada.meza@gmail.com

---

## 📌 Notas Adicionales

### Versión Actual: 2.0.0

**Cambios recientes:**
- ✅ Migrado de SQLite a MySQL
- ✅ Implementado JWT real (antes era mock)
- ✅ Passwords con BCrypt
- ✅ Secrets externalizados
- ✅ Frontend modernizado con React 18 + TypeScript
- ✅ Nuevo diseño con TailwindCSS

**Próximas mejoras planificadas:**
- [ ] Implementar capa de servicio en backend
- [ ] Agregar tests unitarios y de integración
- [ ] Migrar a Flyway para control de versiones de BD
- [ ] Implementar rate limiting
- [ ] Agregar Swagger/OpenAPI documentation
- [ ] CI/CD con GitHub Actions

---

## 🆘 Soporte

Para reportar problemas o solicitar features:
1. Abrir un issue en GitHub
2. Describir el problema/solicitud claramente
3. Incluir pasos para reproducir (si aplica)

---

**¿Preguntas?** Revisa primero:
- [`backend/README.md`](backend/README.md) - Documentación técnica del backend
- [`DEPLOYMENT_GUIDE.md`](DEPLOYMENT_GUIDE.md) - Guía de deployment
- [`project/README.md`](project/README.md) - Documentación del frontend
