# 🔐 Instrucciones para Probar el Login

## ✅ Correcciones Aplicadas

1. **Backend:**
   - ✅ `AuthController` ya existe en `/api/auth/login`
   - ✅ `RolRepository` ahora usa `findByNombreIgnoreCase()` (acepta "admin", "Admin", "ADMIN", etc.)
   - ✅ `UsuarioController` actualizado para usar búsqueda case-insensitive

2. **Frontend:**
   - ✅ `authAPI.login()` llama a `POST /api/auth/login` (correcto)
   - ✅ `useAuth` hook maneja correctamente `{ user, token }`

## 🚀 Pasos para Probar

### 1. Reiniciar el Backend
```powershell
cd c:\Users\josem\Desktop\Abogados-Asociados-App\backend
mvn spring-boot:run
```

Espera a ver este mensaje:
```
Started BackendApplication in X.XXX seconds
```

### 2. Reiniciar el Frontend (si está corriendo)
```powershell
# Detener el frontend (Ctrl+C)
# Luego reiniciarlo
cd c:\Users\josem\Desktop\Abogados-Asociados-App\project
npm run dev
```

### 3. Limpiar Cache del Navegador
**IMPORTANTE:** El navegador puede estar usando código antiguo.

**Opción A - Ctrl+Shift+R (Recomendado):**
1. Abre http://localhost:5173/login
2. Presiona `Ctrl + Shift + R` (recarga fuerte)

**Opción B - DevTools:**
1. Abre DevTools (F12)
2. Click derecho en el botón de recargar
3. Selecciona "Empty Cache and Hard Reload"

**Opción C - Borrar todo:**
1. F12 → Application/Aplicación
2. Storage → Clear site data
3. Recargar la página

### 4. Probar Login

**Usuario Admin (ya existe en BD):**
- Username: `admin`
- Password: `admin123`

**Usuario Test (que creaste):**
- Username: `Test 1 ` (con espacio al final)
- Password: (la que hayas usado al registrar)

### 5. Verificar en DevTools

Abre Network (F12 → Network) y verifica:

**✅ CORRECTO:**
```
Request URL: http://localhost:8080/api/auth/login
Request Method: POST
Status Code: 200 OK

Response:
{
  "user": {
    "id": 1,
    "username": "admin",
    "email": null,
    "rol": {
      "id": 1,
      "nombre": "Administrador"
    }
  },
  "token": "mock-jwt-token"
}
```

**❌ INCORRECTO (código viejo en cache):**
```
Request URL: http://localhost:8080/api/usuarios
Request Method: GET
```

## 🐛 Si Sigue Fallando

1. **Borrar localStorage manualmente:**
```javascript
// Pega esto en la consola del navegador (F12):
localStorage.clear();
location.reload();
```

2. **Verificar que el backend esté corriendo:**
```powershell
# Prueba directa con curl:
curl -X POST http://localhost:8080/api/auth/login `
  -H "Content-Type: application/json" `
  -d '{"username":"admin","password":"admin123"}'
```

Deberías ver:
```json
{
  "user": {...},
  "token": "mock-jwt-token"
}
```

3. **Verificar logs del backend:**
- Mira la consola donde corre `mvn spring-boot:run`
- Busca líneas que digan `POST "/api/auth/login"`

## 📝 Credenciales de Prueba

Según `schema_abogados.sql`:

| Username | Password | Rol |
|----------|----------|-----|
| admin | admin123 | Administrador |
| Test 1 | (tu password) | Usuario |

## 🔄 Siguiente Paso

Una vez que el login funcione correctamente:
- ✅ Deberías ver el Dashboard (`/main`)
- ✅ El nombre de usuario debe aparecer arriba: "Bienvenido, **admin**"
- ✅ Las estadísticas deben cargar

---

**Nota:** El token actual es `"mock-jwt-token"` (no es un JWT real). Para producción, necesitaremos:
1. Generar JWT real
2. Encriptar passwords con BCrypt
3. Validar tokens en cada request
