# Guía de Despliegue - Backend Laravel

## Requisitos del Servidor
- PHP >= 8.1
- Composer
- MySQL 8.0+
- Nginx o Apache
- Extensiones PHP: BCMath, Ctype, JSON, Mbstring, OpenSSL, PDO, Tokenizer, XML, GD, Zip

## Pasos de Instalación

### 1. Subir archivos
```bash
# Descomprimir en el directorio del subdominio
cd /var/www/tu-subdominio
unzip backend-laravel.zip
```

### 2. Instalar dependencias
```bash
cd backend-laravel
composer install --optimize-autoloader --no-dev
```

### 3. Configurar .env
```bash
cp .env.example .env
nano .env
```

Configurar:
```
APP_NAME="Abogados Asociados"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://tu-subdominio.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=abogados_asociados
DB_USERNAME=tu_usuario
DB_PASSWORD=tu_password

JWT_SECRET=tu_jwt_secret
```

### 4. Generar keys
```bash
php artisan key:generate
php artisan jwt:secret
```

### 5. Configurar permisos
```bash
sudo chown -R www-data:www-data /var/www/tu-subdominio/backend-laravel
sudo chmod -R 755 /var/www/tu-subdominio/backend-laravel
sudo chmod -R 775 /var/www/tu-subdominio/backend-laravel/storage
sudo chmod -R 775 /var/www/tu-subdominio/backend-laravel/bootstrap/cache
```

### 6. Ejecutar migraciones
```bash
php artisan migrate --force
php artisan db:seed --class=DatabaseSeeder
```

### 7. Optimizar para producción
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 8. Configurar Nginx

Crear archivo: `/etc/nginx/sites-available/tu-subdominio`

```nginx
server {
    listen 80;
    server_name tu-subdominio.com;
    root /var/www/tu-subdominio/backend-laravel/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    client_max_body_size 50M;
}
```

Activar sitio:
```bash
sudo ln -s /etc/nginx/sites-available/tu-subdominio /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

### 9. Configurar SSL con Certbot
```bash
sudo apt install certbot python3-certbot-nginx
sudo certbot --nginx -d tu-subdominio.com
```

## Actualizar el proyecto

Para actualizar:
```bash
cd /var/www/tu-subdominio/backend-laravel
php artisan down
git pull origin migration/laravel-backend
composer install --optimize-autoloader --no-dev
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan up
```
