#!/bin/bash

# Script de Despliegue Automatizado
# Abogados Asociados - expedientes.abogadosyasociados.net.pe

set -e  # Salir si hay error

echo "=========================================="
echo " Despliegue - Abogados Asociados"
echo "=========================================="
echo ""

# Variables
BACKEND_DIR="/var/www/abogados-backend"
FRONTEND_DIR="/var/www/abogados-frontend"
BACKUP_DIR="/var/backups/abogados"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)

# Colores
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Funciones
print_success() {
    echo -e "${GREEN}✓${NC} $1"
}

print_error() {
    echo -e "${RED}✗${NC} $1"
}

print_info() {
    echo -e "${YELLOW}→${NC} $1"
}

# Verificar que estamos en el servidor
if [ ! -d "$BACKEND_DIR" ]; then
    print_error "Directorio backend no existe. ¿Estás en el servidor correcto?"
    exit 1
fi

# 1. Crear backup
print_info "Creando backup..."
mkdir -p $BACKUP_DIR
if [ -f "$BACKEND_DIR/backend-1.0.0.jar" ]; then
    cp "$BACKEND_DIR/backend-1.0.0.jar" "$BACKUP_DIR/backend-$TIMESTAMP.jar"
    print_success "Backup del backend creado"
fi
if [ -d "$FRONTEND_DIR/dist" ]; then
    tar -czf "$BACKUP_DIR/frontend-$TIMESTAMP.tar.gz" -C "$FRONTEND_DIR" dist
    print_success "Backup del frontend creado"
fi

# 2. Detener backend
print_info "Deteniendo backend..."
sudo systemctl stop abogados-backend || true
print_success "Backend detenido"

# 3. Desplegar backend
print_info "Desplegando backend..."
if [ -f "backend/target/backend-1.0.0.jar" ]; then
    cp backend/target/backend-1.0.0.jar $BACKEND_DIR/
    sudo chown www-data:www-data $BACKEND_DIR/backend-1.0.0.jar
    print_success "Backend desplegado"
else
    print_error "JAR del backend no encontrado. Ejecuta 'mvn clean package' primero"
    exit 1
fi

# 4. Desplegar frontend
print_info "Desplegando frontend..."
if [ -d "project/dist" ]; then
    rm -rf $FRONTEND_DIR/dist/*
    cp -r project/dist/* $FRONTEND_DIR/dist/
    sudo chown -R www-data:www-data $FRONTEND_DIR/dist
    print_success "Frontend desplegado"
else
    print_error "Directorio dist no encontrado. Ejecuta 'npm run build' primero"
    exit 1
fi

# 5. Iniciar backend
print_info "Iniciando backend..."
sudo systemctl start abogados-backend
sleep 3
if sudo systemctl is-active --quiet abogados-backend; then
    print_success "Backend iniciado correctamente"
else
    print_error "Error al iniciar backend. Revisa los logs:"
    echo "sudo journalctl -u abogados-backend -n 50"
    exit 1
fi

# 6. Recargar Nginx
print_info "Recargando Nginx..."
sudo nginx -t && sudo systemctl reload nginx
print_success "Nginx recargado"

# 7. Verificar despliegue
print_info "Verificando despliegue..."
sleep 2

# Verificar backend
if curl -s -o /dev/null -w "%{http_code}" http://localhost:8019/api/auth/login | grep -q "200\|401\|403"; then
    print_success "Backend respondiendo correctamente"
else
    print_error "Backend no responde. Verifica los logs"
fi

# Verificar frontend
if curl -s -o /dev/null -w "%{http_code}" https://expedientes.abogadosyasociados.net.pe | grep -q "200"; then
    print_success "Frontend accesible"
else
    print_error "Frontend no accesible"
fi

echo ""
echo "=========================================="
echo -e "${GREEN}✓ Despliegue completado${NC}"
echo "=========================================="
echo ""
echo "URLs:"
echo "  Frontend: https://expedientes.abogadosyasociados.net.pe"
echo "  Backend:  https://expedientes.abogadosyasociados.net.pe/api"
echo ""
echo "Comandos útiles:"
echo "  Ver logs backend:  sudo journalctl -u abogados-backend -f"
echo "  Ver logs Nginx:    sudo tail -f /var/log/nginx/abogados-error.log"
echo "  Reiniciar backend: sudo systemctl restart abogados-backend"
echo ""
