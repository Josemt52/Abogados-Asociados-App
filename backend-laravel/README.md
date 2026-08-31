# Abogados Asociados

Aplicación unificada para la gestión de expedientes jurídicos. Laravel expone la API y sirve una SPA construida con Vue 3, TypeScript, Vite y Tailwind CSS desde el mismo proyecto.

## Requisitos

- PHP 8.2 o superior
- Composer 2
- Node.js 20.19+ o 22.12+
- npm 8 o superior
- MySQL
- Extensión PHP `pdo_sqlite` para las pruebas de integración
- Poppler (`pdftotext` y `pdftoppm`) para cargas masivas en PDF
- Tesseract con español para PDFs o documentos Word escaneados

## Instalación local

```powershell
composer install
npm install
Copy-Item .env.example .env
php artisan key:generate
php artisan jwt:secret
```

Configura la conexión `DB_*` en `.env` y prepara la base de datos:

```powershell
php artisan migrate --seed
```

La SPA consume `/api` desde el mismo origen de Laravel. Deja `VITE_API_URL` vacío salvo que necesites apuntar deliberadamente a otro backend.

## Desarrollo

Ejecuta Laravel y Vite en terminales separadas:

```powershell
php artisan serve
```

```powershell
npm run dev
```

Para procesar cargas masivas en segundo plano, ejecuta también:

```powershell
php artisan queue:work --tries=2 --timeout=420
```

Abre `http://localhost:8000`. Vite solo sirve los recursos durante el desarrollo; las páginas y las peticiones API salen desde Laravel.

La carga para usuarios está en `/carga-masiva`. La revisión interna está separada en `/paneladmin` y exige una cuenta con rol `ADMIN`; no se enlaza desde la interfaz normal.

## Validación

```powershell
npm run typecheck
npm run build
php artisan test
```

## Producción

```powershell
npm ci
npm run build
php artisan optimize
```

Configura el servidor web con `public/` como raíz. El fallback de `routes/web.php` permite abrir directamente rutas de Vue como `/expedientes/15`; las rutas `/api/*` siguen siendo manejadas exclusivamente por Laravel.

En Ubuntu instala las dependencias de documentos con:

```bash
sudo apt update
sudo apt install -y poppler-utils libreoffice-writer tesseract-ocr tesseract-ocr-spa
```

Mantén un proceso `php artisan queue:work --tries=2 --timeout=420` activo en producción y configura `DB_QUEUE_RETRY_AFTER=600`. Los PDF se analizan con Poppler y se almacenan como DOCX facsímiles para conservar todas sus páginas. LibreOffice procesa los DOC legados difíciles y Tesseract lee las primeras páginas escaneadas; cuando no es posible obtener la cabecera, el original queda protegido en el panel administrativo de pendientes.

## Estructura principal

- `resources/js/`: aplicación Vue, rutas, componentes, páginas y cliente API.
- `resources/css/app.css`: estilos globales y Tailwind.
- `resources/views/app.blade.php`: documento HTML que monta la SPA.
- `routes/api.php`: API protegida con JWT.
- `routes/web.php`: entrada y fallback de Vue Router.
