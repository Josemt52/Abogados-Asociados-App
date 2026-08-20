# Plan: Integración de ONLYOFFICE Docs con el Sistema de Expedientes

## Resumen Ejecutivo

El objetivo es integrar un editor de documentos tipo Word **directamente en el navegador** para que los usuarios puedan crear, editar y modificar expedientes y resoluciones **sin descargar ni re-subir archivos manualmente**. Se utilizará **ONLYOFFICE Docs Community Edition** (open source, gratuito) desplegado via Docker, integrado con el backend Laravel y el frontend Vue 3.

---

## Arquitectura Actual vs Nueva

### Flujo Actual (problema)
```
1. Usuario descarga .docx del servidor
2. Abre en Word de escritorio
3. Edita el documento
4. Vuelve a subir el archivo
5. Sistema reemplaza el documento
```

### Flujo Nuevo (objetivo)
```
1. Usuario hace clic en "Editar documento"
2. Se abre el editor ONLYOFFICE embebido en el navegador
3. Usuario edita directamente (tablas, formato, imágenes, etc.)
4. Al guardar/cerrar, ONLYOFFICE notifica al backend
5. Backend actualiza el .docx en la base de datos
```

---

## Fase 1: Despliegue de ONLYOFFICE Document Server

### 1.1 Requisitos del Servidor
- **RAM mínima:** 4 GB (recomendado 8 GB para uso con tablas/imágenes)
- **CPU:** 2+ cores
- **Disco:** 20 GB+ (para el contenedor Docker)
- **Puertos:** 8080 (editor), 8081 (checker)

### 1.2 Despliegue con Docker

**Archivo `docker-compose.onlyoffice.yml`:**
```yaml
version: '3.8'

services:
  onlyoffice-documentserver:
    image: onlyoffice/documentserver:latest
    container_name: onlyoffice-docs
    environment:
      - JWT_SECRET=${ONLYOFFICE_JWT_SECRET}
      - JWT_ENABLED=true
      - JWT_HEADER=Authorization
      - JWT_HEADER_SCHEMA=Bearer
      - JWT_QS_PARAM=jwt
      - ONLYOFFICE_API_HOST=0.0.0.0
    ports:
      - "8080:80"
      - "8081:80"
    volumes:
      - onlyoffice_data:/var/www/onlyoffice/Data
      - onlyoffice_logs:/var/log/onlyoffice
    restart: unless-stopped
    networks:
      - onlyoffice-network

volumes:
  onlyoffice_data:
  onlyoffice_logs:

networks:
  onlyoffice-network:
    driver: bridge
```

**Variables de entorno a agregar en `.env`:**
```bash
# ONLYOFFICE Configuration
ONLYOFFICE_JWT_SECRET=your-random-secret-min-32-chars
ONLYOFFICE_SERVER_URL=http://localhost:8080
```

### 1.3 Verificación del Despliegue
- Acceder a `http://localhost:8080` → debe mostrar la página de bienvenida de ONLYOFFICE
- El endpoint de health check: `http://localhost:8080/healthcheck`

---

## Fase 2: Backend Laravel — Nuevo Servicio `OnlyOfficeService`

### 2.1 Instalar dependencia JWT para ONLYOFFICE
```bash
composer require firebase/php-jwt
```

### 2.2 Crear configuración `config/onlyoffice.php`
```php
return [
    'url' => env('ONLYOFFICE_SERVER_URL', 'http://localhost:8080'),
    'jwt_secret' => env('ONLYOFFICE_JWT_SECRET'),
    'callback_url' => env('APP_URL') . '/api/onlyoffice/callback',
];
```

### 2.3 Crear servicio `app/Services/OnlyOfficeService.php`

**Responsabilidades:**
- Generar la configuración JSON para el editor ONLYOFFICE
- Firmar el JWT con el secret
- Crear el payload de configuración con:
  - Document mode (edit/view/review)
  - Document URL (endpoint para descargar el .docx)
  - Callback URL (endpoint para recibir cambios)
  - Permisos del usuario
  - Idioma (español)
  - Tema de la interfaz

**Métodos principales:**
```php
class OnlyOfficeService
{
    /**
     * Genera la configuración para el editor
     */
    public function getEditorConfig(
        string $documentKey,
        string $documentUrl,
        string $callbackUrl,
        User $user,
        string $mode = 'edit',
        ?string $fileName = null
    ): array;

    /**
     * Firma el payload con JWT
     */
    private function signJwt(array $payload): string;

    /**
     * Valida la firma del callback de ONLYOFFICE
     */
    public function validateCallback(string $token): ?array;
}
```

### 2.4 Crear controller `app/Http/Controllers/Api/OnlyOfficeController.php`

**Endpoints:**
```php
/**
 * Retorna la configuración para abrir el editor ONLYOFFICE
 * GET /api/onlyoffice/config/{type}/{id}?mode=edit|view
 *
 * type: "expediente" | "resolucion"
 * id: ID del expediente o resolución
 */
public function getConfig(Request $request, string $type, string $id): JsonResponse;

/**
 * Callback endpoint — ONLYOFFICE notifica cuando el usuario guarda
 * POST /api/onlyoffice/callback
 */
public function callback(Request $request): JsonResponse;
```

### 2.5 Lógica del Callback

Cuando ONLYOFFICE guarda, envía un POST con:
```json
{
  "key": "expediente_123",
  "status": 2,  // 0=connecting, 1=loading, 2=ready, 3=saving, 4=saved, 6=error
  "url": "http://onlyoffice-host/path/to/saved/file.docx"
}
```

**Flujo del callback:**
1. Validar JWT del callback
2. Si `status === 4` (guardado exitosamente):
   - Descargar el .docx desde la URL proporcionada
   - Actualizar el `documento_data` en la tabla `archivos` o `resoluciones`
   - Actualizar metadatos si es necesario
3. Responder con HTTP 200

### 2.6 Manejo del `documentKey`

El `documentKey` es un identificador único que ONLYOFFICE usa para:
- Identificar el documento
- Prevenir ediciones simultáneas (bloqueo)
- Crear un historial de versiones

**Formato:** `{tipo}_{id}_{timestamp}` — esto permite detectar si el documento cambió entre aperturas.

### 2.7 Almacenamiento Temporal de Documentos

ONLYOFFICE necesita una URL pública para descargar el .docx al editor. Se crea un endpoint temporal:

```php
/**
 * Endpoint temporal para servir el .docx a ONLYOFFICE
 * GET /api/onlyoffice/document/{type}/{id}
 *
 * Retorna el .docx binario con Content-Type correcto
 */
public function serveDocument(string $type, string $id): StreamedResponse;
```

**Seguridad:** El endpoint debe:
- Validar que el usuario esté autenticado
- Generar un token temporal (JWT o hash con expiración)
- NO exponer el endpoint públicamente

---

## Fase 3: Frontend Vue 3 — Integración del Editor

### 3.1 Instalar el SDK de ONLYOFFICE
```bash
npm install @onlyoffice/document-editor-js
```

### 3.2 Crear componente `resources/js/components/OnlyOfficeEditor.vue`

**Props:**
```typescript
interface Props {
  documentType: 'word' | 'cell' | 'slide';  // 'word' para .docx
  documentKey: string;
  mode: 'edit' | 'view';
  onClose?: () => void;
  onSave?: () => void;
}
```

**Funcionamiento:**
1. Al montar, llama a `GET /api/onlyoffice/config/{type}/{id}?mode=edit`
2. Recibe la configuración con URLs firmadas
3. Inicializa el editor ONLYOFFICE en un `<div>` contenedor
4. Escucha eventos del editor (save, ready, error)
5. Al cerrar, notifica al padre y recarga los datos

**Template simplificado:**
```vue
<template>
  <div class="fixed inset-0 z-50 bg-white">
    <div class="flex h-full flex-col">
      <!-- Barra superior -->
      <div class="flex items-center justify-between border-b px-4 py-2">
        <h2>{{ fileName }}</h2>
        <div class="space-x-2">
          <Button @click="saveAndClose">Guardar y Cerrar</Button>
          <Button variant="outline" @click="closeWithoutSaving">Cerrar</Button>
        </div>
      </div>
      <!-- Contenedor del editor -->
      <div ref="editorContainer" class="flex-1" />
    </div>
  </div>
</template>
```

### 3.3 Crear composable `resources/js/composables/useOnlyOfficeEditor.ts`

**Responsabilidades:**
- Obtener configuración del editor desde el backend
- Inicializar y destruir el editor
- Manejar eventos del editor
- Manejar estado de carga/error

### 3.4 Modificar Páginas Existentes

#### `ExpedienteDetail.vue` — Cambios:
- Agregar botón "Editar en navegador" junto a "Editar"
- Al hacer clic, abrir el editor ONLYOFFICE en modo full-screen
- Después de guardar, recargar el expediente

#### `Expedientes.vue` — Cambios:
- En el visor de documentos, agregar opción "Abrir en editor" para documentos .docx
- Mantener la opción de descargar para cuando no se quiera editar

#### Nueva página o modal: `resources/js/pages/OnlyOfficeEdit/OnlyOfficeEdit.vue`
- Página/ruta dedicada para el editor
- Recibe `{type}/{id}` como parámetros
- Maneja el ciclo completo: abrir → editar → guardar → cerrar

---

## Fase 4: Flujo de Resoluciones con ONLYOFFICE

### 4.1 Generar Plantilla → Editar en Navegador

**Flujo actual:**
1. Backend genera plantilla .docx con campos del expediente
2. Usuario descarga el .docx
3. Abre en Word, edita
4. Sube el Word completado

**Nuevo flujo:**
1. Backend genera plantilla .docx (igual que antes)
2. Se almacena temporalmente en `resoluciones` con estado `pendiente`
3. Usuario hace clic en "Editar plantilla"
4. Se abre ONLYOFFICE con el .docx generado
5. Usuario edita directamente en el navegador
6. Al guardar, el .docx se actualiza en la base de datos
7. El proceso de consolidación (generar PDF) se ejecuta automáticamente

### 4.2 Modificar `ResolucionController`

```php
/**
 * Generar y abrir plantilla en ONLYOFFICE
 * GET /api/expedientes/{id}/resoluciones/{rid}/edit
 *
 * 1. Si la resolución no tiene plantilla, generarla
 * 2. Retornar configuración ONLYOFFICE para editar el .docx
 */
public function editInBrowser(Request $request, string $id, string $rid): JsonResponse;

/**
 * Callback para resoluciones
 * POST /api/onlyoffice/callback/resolucion/{rid}
 */
public function resolucionCallback(Request $request, string $rid): JsonResponse;

/**
 * Completar resolución después de editar
 * POST /api/expedientes/{id}/resoluciones/{rid}/completar-online
 *
 * 1. El .docx ya está guardado por el callback
 * 2. Ejecutar consolidación PDF
 * 3. Marcar resolución como completada
 */
public function completarOnline(Request $request, string $id, string $rid): JsonResponse;
```

### 4.3 Crear Documento Base en ONLYOFFICE

Para expedientes sin documento inicial:
1. Crear un .docx vacío o con plantilla básica
2. Abrir en ONLYOFFICE para que el usuario escriba el contenido
3. Guardar como documento base del expediente

---

## Fase 5: Seguridad y Permisos

### 5.1 Autenticación del Editor
- El SDK de ONLYOFFICE se autentica con JWT
- El backend firma el token con `ONLYOFFICE_JWT_SECRET`
- El token incluye:
  - `document_key` — clave del documento
  - `user_id` — ID del usuario
  - `user_name` — Nombre del usuario
  - `mode` — edit/review/view
  - `exp` — expiración (5 min para abrir, callback con expiración mayor)

### 5.2 Control de Permisos por Rol

| Usuario | Documento Base | Resoluciones Propias | Resoluciones Otros |
|---------|---------------|---------------------|-------------------|
| ADMIN | Edit | Edit | Edit |
| USUARIO (especialista) | View | Edit (suyas) | View |
| USUARIO (otro) | View | View | View |

### 5.3 Protección del Endpoint de Documento
- El endpoint `serveDocument` debe usar tokens temporales
- Token con expiración corta (5 minutos)
- Una vez que ONLYOFFICE consume el token, se invalida

### 5.4 Prevención de Edición Simultánea
- ONLYOFFICE maneja esto automáticamente con el `documentKey`
- Si dos usuarios intentan editar el mismo documento, el segundo recibe un aviso

---

## Fase 6: Consolidación Automática

### 6.1 Activar Consolidación al Guardar

Después de que ONLYOFFICE guarda un .docx de resolución:
1. El callback recibe el archivo
2. Se ejecuta automáticamente:
   - `DocumentConversionService::convertResolutionToPdfStrict()` → convertir a PDF
   - `PdfMergeService::merge()` → consolidar con resoluciones anteriores
   - Actualizar el `archivo_data` en la tabla `archivos`
   - Marcar la resolución como `completada`

### 6.2 No Bloquear al Usuario

La consolidación puede tardar (LibreOffice conversion). Estrategia:
- Responder HTTP 200 al callback de inmediato
- Ejecutar la consolidación en background con `dispatch()`
- Usar eventos para notificar al frontend cuando termine
- El frontend muestra un indicador "Consolidando..." y recarga cuando termine

---

## Fase 7: Testing y Validación

### 7.1 Pruebas del Backend
- Test del endpoint `getConfig` → retorna configuración válida
- Test del callback → actualiza el documento correctamente
- Test de seguridad → rechaza callbacks sin JWT válido
- Test de permisos → usuarios no admin no pueden editar documentos de otros

### 7.2 Pruebas del Frontend
- Test del componente `OnlyOfficeEditor` → inicializa correctamente
- Test de apertura de editor → carga el .docx
- Test de cierre → notifica al componente padre
- Test de error → muestra mensaje cuando ONLYOFFICE no está disponible

### 7.3 Pruebas de Integración
- Flujo completo: crear expediente → subir documento → editar en navegador → guardar
- Flujo de resolución: generar plantilla → editar → completar → consolidar PDF
- Prueba de edición simultánea (dos usuarios intentan editar el mismo doc)

---

## Fase 8: Monitoreo y Mantenimiento

### 8.1 Health Check del Document Server
```php
/**
 * Verificar que ONLYOFFICE esté disponible
 * GET /api/onlyoffice/status
 */
public function status(): JsonResponse;
```

### 8.2 Logs
- Log de todas las operaciones de apertura de editor
- Log de callbacks recibidos (exitosos y fallidos)
- Log de errores de conversión

### 8.3 Limpieza de Archivos Temporales
- Los .docx temporales en `/var/www/onlyoffice/Data` se limpian automáticamente
- Los archivos temporales en `storage/app/temp` se limpian periódicamente

---

## Archivos a Crear/Modificar

### Archivos Nuevos
| Archivo | Descripción |
|---------|-------------|
| `config/onlyoffice.php` | Configuración de ONLYOFFICE |
| `app/Services/OnlyOfficeService.php` | Servicio principal |
| `app/Http/Controllers/Api/OnlyOfficeController.php` | Controller API |
| `app/Http/Middleware/VerifyOnlyOfficeCallback.php` | Middleware de validación |
| `resources/js/components/OnlyOfficeEditor.vue` | Componente del editor |
| `resources/js/composables/useOnlyOfficeEditor.ts` | Composable del editor |
| `docker-compose.onlyoffice.yml` | Despliegue Docker |
| `.env.onlyoffice.example` | Variables de entorno ejemplo |

### Archivos a Modificar
| Archivo | Cambios |
|---------|---------|
| `routes/api.php` | Agregar rutas de ONLYOFFICE |
| `app/Http/Controllers/Api/ExpedienteController.php` | Endpoint para servir documento |
| `app/Http/Controllers/Api/ResolucionController.php` | Endpoint de edición online |
| `resources/js/pages/ExpedienteDetail/ExpedienteDetail.vue` | Botón "Editar en navegador" |
| `resources/js/pages/Expedientes/Expedientes.vue` | Opción de editar en visor |
| `resources/js/api/expedientes.ts` | Métodos para ONLYOFFICE |
| `resources/js/router/index.ts` | Ruta del editor (opcional) |
| `.env.example` | Variables de ONLYOFFICE |
| `composer.json` | Dependencia `firebase/php-jwt` |
| `package.json` | Dependencia `@onlyoffice/document-editor-js` |

---

## Estimación de Esfuerzo

| Fase | Días Estimados | Dependencias |
|------|---------------|--------------|
| Fase 1: Despliegue Docker | 0.5 días | Ninguna |
| Fase 2: Backend Laravel | 2-3 días | Fase 1 |
| Fase 3: Frontend Vue 3 | 2-3 días | Fase 2 |
| Fase 4: Flujo de Resoluciones | 2-3 días | Fases 2-3 |
| Fase 5: Seguridad | 1-2 días | Fase 2 |
| Fase 6: Consolidación Auto | 1-2 días | Fases 2-4 |
| Fase 7: Testing | 2-3 días | Fases 1-6 |
| Fase 8: Monitoreo | 1 día | Fase 2 |
| **Total** | **12-17 días** | |

---

## Riesgos y Mitigaciones

| Riesgo | Impacto | Mitigación |
|--------|---------|------------|
| ONLYOFFICE consume mucha RAM | Alto | Limitar usuarios simultáneo a 10-15; upgrade RAM si es necesario |
| Formato .docx no se preserva 100% | Medio | Probar con documentos legales reales; usar LibreOffice como fallback |
| Callback de ONLYOFFICE falla | Alto | Reintentos automáticos; cola de mensajes; monitoreo de salud |
| Edición simultánea no detectada | Medio | ONLYOFFICE maneja esto con documentKey; documentar comportamiento |
| CORS o problemas de red | Medio | Verificar configuración Docker; network isolation |

---

## Alternativa: Si ONLYOFFICE no funciona en el servidor actual

Si el servidor no tiene suficientes recursos para Docker:
1. **Opción A:** Subir a un VPS separado (Railway, DigitalOcean, etc.)
2. **Opción B:** Usar ONLYOFFICE DocSpace Cloud (plan gratuito para uso personal)
3. **Opción C:** Cambiar a CKEditor 5 con plugin de import Word (menos fidelidad pero más ligero)

---

## Referencias

- [ONLYOFFICE Docs GitHub](https://github.com/ONLYOFFICE/DocumentServer)
- [ONLYOFFICE API](https://api.onlyoffice.com/)
- [ONLYOFFICE SDK JavaScript](https://github.com/nicolo-ribaudo/tc39-proposal-docx)
- [ONLYOFFICE Help Center - Development](https://helpcenter.onlyoffice.com/ONLYOFFICE_Docs/ONLYOFFICE_Docs_forDevelopers/index.html)
- [ONLYOFFICE Docker Installation](https://helpcenter.onlyoffice.com/ONLYOFFICE_Docs/ONLYOFFICE_Docs_forDevelopers/ONLYOFFICE_Docs_editors_integration/ONLYOFFICE_Docs_connecting_Document_Server_to_the_dms_cms/index.html)
