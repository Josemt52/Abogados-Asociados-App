# 🔧 Configuración de Profiles - Backend

## 📋 Profiles Configurados

### 1. Development (dev) - Por defecto
- **Base de datos:** `abogados_asociados` (local)
- **Usuario:** `root`
- **Password:** (vacío)
- **DDL:** `update` (crea/actualiza tablas automáticamente)
- **Logs:** DEBUG

### 2. Production (prod)
- **Base de datos:** `josemiguelo_abogados_asociados`
- **Usuario:** `josemiguelo_root`
- **Password:** `Morganella.12334`
- **DDL:** `validate` (solo valida, no modifica)
- **Logs:** INFO

---

## 🚀 Cómo Ejecutar

### En Desarrollo (Local)

**Opción 1: Maven (usa profile dev por defecto)**
```bash
cd backend
mvn spring-boot:run
```

**Opción 2: JAR con profile dev**
```bash
cd backend
mvn clean package
java -jar target/backend-1.0.0.jar
```

**Opción 3: Especificar profile dev explícitamente**
```bash
mvn spring-boot:run -Dspring-boot.run.profiles=dev
```

---

### En Producción (Servidor)

**Opción 1: Con systemd (Recomendado)**
```bash
# El servicio ya está configurado con profile=prod
sudo systemctl start expedientesback
sudo systemctl status expedientesback
```

**Opción 2: JAR con profile prod**
```bash
java -jar backend-1.0.0.jar --spring.profiles.active=prod
```

**Opción 3: Con variables de entorno**
```bash
export SPRING_PROFILES_ACTIVE=prod
java -jar backend-1.0.0.jar
```

---

## 📁 Archivos de Configuración

```
backend/src/main/resources/
├── application.properties          # Configuración base + dev
└── application-prod.properties     # Configuración de producción
```

### application.properties (Base + Dev)
- Profile por defecto: `dev`
- Puerto: `8019`
- Base de datos local
- DDL: `update`

### application-prod.properties
- Se activa con: `--spring.profiles.active=prod`
- Base de datos de producción
- DDL: `validate`
- Logs: INFO

---

## ✅ Verificar Profile Activo

Al iniciar la aplicación, verás en los logs:

**Development:**
```
The following 1 profile is active: "dev"
```

**Production:**
```
The following 1 profile is active: "prod"
```

---

## 🔄 Cambiar Profile en Tiempo de Ejecución

### Método 1: Argumento de línea de comandos
```bash
java -jar backend-1.0.0.jar --spring.profiles.active=prod
```

### Método 2: Variable de entorno
```bash
export SPRING_PROFILES_ACTIVE=prod
java -jar backend-1.0.0.jar
```

### Método 3: Propiedad del sistema
```bash
java -Dspring.profiles.active=prod -jar backend-1.0.0.jar
```

---

## 🛠️ Compilar para Producción

```bash
cd backend
mvn clean package -DskipTests
```

El JAR generado funcionará con cualquier profile según cómo lo ejecutes.

---

## 📊 Resumen de Comandos

| Entorno | Comando |
|---------|---------|
| **Dev (Maven)** | `mvn spring-boot:run` |
| **Dev (JAR)** | `java -jar backend-1.0.0.jar` |
| **Prod (Systemd)** | `sudo systemctl start expedientesback` |
| **Prod (JAR)** | `java -jar backend-1.0.0.jar --spring.profiles.active=prod` |

---

## ⚠️ Importante

- **Desarrollo:** Usa `application.properties` (profile dev por defecto)
- **Producción:** Usa `--spring.profiles.active=prod` para activar `application-prod.properties`
- El servicio systemd ya está configurado con `SPRING_PROFILES_ACTIVE=prod`
- No necesitas cambiar nada en el código, solo el profile al ejecutar

---

¡Listo! Ahora el backend usa automáticamente la configuración correcta según el entorno. 🎉
