#!/usr/bin/env bash

# LogicDock - Uninstaller v3.0
# Author: LogicDock
# Description: Completely removes LogicDock and all its data.

set -e

INSTALL_DIR="/opt/logicdock"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m'

# Helpers
log_info() { echo -e "\033[0;34m[INFO]\033[0m $1"; }
log_success() { echo -e "${GREEN}[OK]\033[0m $1"; }
log_warn() { echo -e "${YELLOW}[WARN]\033[0m $1"; }
log_error() { echo -e "${RED}[ERROR]\033[0m $1"; }

# --- 1. Root Check ---
if [[ $EUID -ne 0 ]]; then
   echo "This script must be run as root."
   exit 1
fi

clear
echo -e "${RED}"
echo "╔═══════════════════════════════════════════════════════════════╗"
echo "║               LOGICPANEL UNINSTALLER v3.0                   ║"
echo "╚═══════════════════════════════════════════════════════════════╝"
echo -e "${NC}"

echo -e "${RED}!!! WARNING: THIS WILL PERMANENTLY DELETE ALL DATA !!!${NC}"
echo ""
echo "This will remove:"
echo "  • All LogicDock containers (app, traefik, gateway, databases)"
echo "  • All user application containers"
echo "  • All databases and data"
echo "  • All configuration files"
echo "  • SSL certificates"
echo ""

# Handle stdin properly when piped
echo ""
read -p "Are you sure you want to uninstall LogicDock? (y/N): " CONFIRM < /dev/tty

if [[ ! "$CONFIRM" =~ ^[Yy]$ ]]; then
    echo "Uninstall cancelled."
    exit 0
fi

# --- 2. Stop and Remove LogicDock Containers ---
log_info "Stopping LogicDock containers..."

# All LogicDock containers
CONTAINERS=(
    "logicdock_app"
    "logicdock_traefik"
    "logicdock_socket_proxy"
    "logicdock_gateway"
    "logicdock_db"
    "logicdock_redis"
    "logicdock_db_provisioner"
    "logicdock_adminer"
)

for container in "${CONTAINERS[@]}"; do
    if docker ps -a --format '{{.Names}}' | grep -q "^${container}$"; then
        docker stop "$container" 2>/dev/null || true
        docker rm -f "$container" 2>/dev/null || true
        log_success "Removed $container"
    fi
done

# Remove containers by env-variable names (randomized names from install.sh)
log_info "Checking for randomized container names..."
for prefix in "lp_sys_" "lp_shared_mysql_" "lp_shared_pg_" "lp_shared_mongo_" "lp-mysql-mother" "lp-postgres-mother" "lp-mongo-mother" "lp_mysql_mother" "lp_postgres_mother" "lp_mongo_mother"; do
    FOUND=$(docker ps -a --format '{{.Names}}' | grep "^${prefix}" || true)
    for container in $FOUND; do
        docker stop "$container" 2>/dev/null || true
        docker rm -f "$container" 2>/dev/null || true
        log_success "Removed $container"
    done
done

# Remove user app containers (logicdock_app_service_*)
log_info "Removing user application containers..."
USER_CONTAINERS=$(docker ps -a --format '{{.Names}}' | grep "^logicdock_app_service_" || true)
for container in $USER_CONTAINERS; do
    docker stop "$container" 2>/dev/null || true
    docker rm -f "$container" 2>/dev/null || true
    log_success "Removed user container: $container"
done

# --- 3. Remove LogicDock Directory ---
if [ -d "$INSTALL_DIR" ]; then
    log_info "Removing LogicDock files..."
    
    # Stop any remaining compose services
    (cd "$INSTALL_DIR" && docker compose down -v 2>/dev/null || true)
    
    rm -rf "$INSTALL_DIR"
    log_success "LogicDock files removed."
else
    log_warn "LogicDock directory not found at $INSTALL_DIR."
fi

# --- 4. Remove Docker Networks ---
log_info "Cleaning up Docker networks..."
docker network rm logicdock_internal 2>/dev/null || true
docker network rm panel_logicdock_internal 2>/dev/null || true

# --- 5. Remove Cron Jobs ---
log_info "Removing LogicDock cron jobs..."
crontab -l 2>/dev/null | grep -v "fix-ssl.sh" | grep -v "logicdock" | crontab - 2>/dev/null || true
log_success "Cron jobs removed."

# --- 6. Clean up Docker ---
read -p "Do you want to remove unused Docker images and volumes? (y/N): " CLEANUP_CONFIRM < /dev/tty
if [[ "$CLEANUP_CONFIRM" =~ ^[Yy]$ ]]; then
    log_info "Cleaning Docker system..."
    docker system prune -f 2>/dev/null || true
    docker volume prune -f 2>/dev/null || true
    log_success "Docker cleanup complete."
fi

# --- Success Message ---
echo ""
echo -e "${GREEN}╔═══════════════════════════════════════════════════════════════╗${NC}"
echo -e "${GREEN}║           ✓ UNINSTALLATION COMPLETE!                        ║${NC}"
echo -e "${GREEN}╚═══════════════════════════════════════════════════════════════╝${NC}"
echo ""
echo -e "  ${CYAN}All LogicDock components have been removed.${NC}"
echo ""
echo -e "  ${YELLOW}To reinstall:${NC}"
echo -e "  ${CYAN}curl -sSL https://raw.githubusercontent.com/LogicDock/LogicDock/main/install.sh | sudo bash${NC}"
echo ""
echo -e "  For more info, visit: ${CYAN}https://logicdock.cloud${NC}"
echo ""
