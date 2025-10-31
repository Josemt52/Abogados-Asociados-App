# 🚀 GUÍA DE MIGRACIÓN DE BASE DE DATOS

## Paso a Paso para Ejecutar la Migración

### ✅ OPCIÓN 1: Usando MySQL Workbench (Recomendado)

1. **Abrir MySQL Workbench**
   - Abre MySQL Workbench
   - Conéctate a tu servidor MySQL (localhost)
   - Usuario: `root` (o el que uses)
   - Password: tu contraseña de MySQL

2. **Abrir el script SQL**
   - En MySQL Workbench: `File` > `Open SQL Script`
   - Navega a: `C:\Users\jose\Desktop\Abogados-Asociados-App\EJECUTAR_MIGRACION.sql`
   - O copia y pega el contenido del archivo directamente

3. **Ejecutar el script**
   - Click en el botón ⚡ (Execute) o presiona `Ctrl + Shift + Enter`
   - Verás la salida en el panel inferior

4. **Verificar resultados**
   - Debe mostrar:
     ```
     === ROLES ANTES DE LA MIGRACIÓN ===
     id | nombre
     1  | Administrador
     2  | Usuario
     3  | Secretario
     
     === ROLES DESPUÉS DE LA MIGRACIÓN ===
     id | nombre
     1  | admin
     2  | usuario
     3  | secretario
     
     === USUARIOS CON SUS ROLES ===
     id | Nombre Completo | Usuario | Rol ID | Rol
     1  | Administrador del Sistema | admin | 1 | admin
     
     ✓ TODOS LOS ROLES ESTÁN EN MINÚSCULAS
     ```

---

### ✅ OPCIÓN 2: Usando Consola MySQL

Si tienes MySQL en PATH o conoces la ruta:

```bash
# Windows PowerShell
& "C:\Program Files\MySQL\MySQL Server 8.0\bin\mysql.exe" -u root -p Abogados_Asociados < C:\Users\jose\Desktop\Abogados-Asociados-App\EJECUTAR_MIGRACION.sql

# O simplemente (si MySQL está en PATH)
mysql -u root -p Abogados_Asociados < EJECUTAR_MIGRACION.sql
```

Ingresa tu contraseña cuando te la solicite.

---

### ✅ OPCIÓN 3: Copiar y Pegar Comandos Manualmente

Si prefieres ejecutar comando por comando:

1. **Conectarse a MySQL:**
   ```sql
   USE Abogados_Asociados;
   ```

2. **Ver roles actuales:**
   ```sql
   SELECT * FROM roles;
   ```

3. **Actualizar roles:**
   ```sql
   UPDATE roles SET nombre = 'admin' WHERE nombre = 'Administrador';
   UPDATE roles SET nombre = 'usuario' WHERE nombre = 'Usuario';
   UPDATE roles SET nombre = 'secretario' WHERE nombre = 'Secretario';
   ```

4. **Actualizar usuario admin:**
   ```sql
   UPDATE usuarios 
   SET nombre = 'Administrador del Sistema' 
   WHERE username = 'admin' AND (nombre IS NULL OR nombre = '');
   ```

5. **Verificar:**
   ```sql
   SELECT u.id, u.nombre, u.username, r.nombre as rol 
   FROM usuarios u 
   INNER JOIN roles r ON u.rol_id = r.id;
   ```

---

### ✅ OPCIÓN 4: Usando phpMyAdmin (si tienes XAMPP/WAMP)

1. Abre phpMyAdmin en tu navegador: `http://localhost/phpmyadmin`
2. Selecciona la base de datos `Abogados_Asociados` del panel izquierdo
3. Click en la pestaña `SQL`
4. Copia y pega el contenido de `EJECUTAR_MIGRACION.sql`
5. Click en el botón `Go` o `Continuar`

---

## 🔍 Verificación de la Migración

Después de ejecutar el script, verifica que:

### 1. Roles actualizados correctamente:
```sql
SELECT * FROM roles;
```

**Resultado esperado:**
```
+----+-----------+
| id | nombre    |
+----+-----------+
|  1 | admin     |
|  2 | usuario   |
|  3 | secretario|
+----+-----------+
```

### 2. Usuarios con roles correctos:
```sql
SELECT u.id, u.nombre, u.username, r.nombre as rol 
FROM usuarios u 
INNER JOIN roles r ON u.rol_id = r.id;
```

**Resultado esperado:**
```
+----+---------------------------+----------+--------+
| id | nombre                    | username | rol    |
+----+---------------------------+----------+--------+
|  1 | Administrador del Sistema | admin    | admin  |
+----+---------------------------+----------+--------+
```

---

## 🚀 Siguiente Paso: Reiniciar el Backend

Una vez completada la migración:

1. **Detener el backend** si está corriendo (Ctrl+C en la terminal)

2. **Reiniciar el backend:**
   ```bash
   cd C:\Users\jose\Desktop\Abogados-Asociados-App\backend
   mvn clean spring-boot:run
   ```

3. **Verificar en la consola:**
   ```
   ✓ Rol 'admin' creado automáticamente
   ✓ Rol 'usuario' creado automáticamente
   ✓ Inicialización de roles completada
   ```

---

## ✅ Prueba Final

1. **Acceder al frontend:**
   ```
   http://localhost:5173/login
   ```

2. **Login como admin:**
   - Usuario: `admin`
   - Password: `admin123`

3. **Verificar acceso a Usuarios:**
   - Click en el menú lateral "Usuarios"
   - ✅ **NO** debe aparecer "Acceso Restringido"
   - ✅ Debe mostrar la tabla de usuarios
   - ✅ Debe ver columnas: ID, Nombre, Usuario, Rol, Acciones

---

## 🐛 Si algo sale mal

### Error: "Table 'Abogados_Asociados.roles' doesn't exist"
- La base de datos no existe o no está correctamente creada
- Ejecuta primero: `schema_abogados.sql`

### Error: "Access denied for user 'root'@'localhost'"
- Verifica tu usuario y contraseña de MySQL
- Usa tu usuario correcto en lugar de `root`

### Los roles no se actualizan
- Verifica que estés conectado a la base de datos correcta: `USE Abogados_Asociados;`
- Verifica que los nombres de roles en la BD coincidan con los del script

### Sigue apareciendo "Acceso Restringido"
1. Verifica que la migración se ejecutó correctamente
2. Cierra sesión en la aplicación
3. Vuelve a iniciar sesión
4. Limpia el localStorage del navegador si es necesario:
   ```javascript
   // En DevTools (F12) > Console
   localStorage.clear();
   ```

---

## 📞 Soporte

Si necesitas ayuda, verifica:
- `RESUMEN_CORRECCION_USUARIOS.md` - Guía completa de cambios
- `README.md` - Documentación general
- `FRONTEND_STRUCTURE.md` - Estructura del frontend

---

**¡Listo!** Una vez completada la migración, el sistema debe funcionar correctamente con los roles actualizados. 🎉
