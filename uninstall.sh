#!/usr/bin/env bash

# LogicPanel - Uninstaller v3.1
# Author: cyber-wahid
# Description: Completely removes LogicPanel and all its data.

set -e

# Detect Docker Desktop vs Server installation
REAL_USER="${SUDO_USER:-$USER}"
REAL_HOME=$(getent passwd "$REAL_USER" 2>/dev/null | cut -d: -f6)
IS_DOCKER_DESKTOP=false

if [ -d "$REAL_HOME/.docker/desktop" ] || \
   (command -v docker &> /dev/null && docker info 2>/dev/null | grep -qi "docker desktop"); then
    IS_DOCKER_DESKTOP=true
fi

if [ "$IS_DOCKER_DESKTOP" = true ]; then
    INSTALL_DIR="${REAL_HOME}/.logicpanel"
else
    INSTALL_DIR="/opt/logicpanel"
fi

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
echo "║               LOGICPANEL UNINSTALLER v3.1                   ║"
echo "╚═══════════════════════════════════════════════════════════════╝"
echo -e "${NC}"

echo -e "${RED}!!! WARNING: THIS WILL PERMANENTLY DELETE ALL DATA !!!${NC}"
echo ""
echo "This will remove:"
echo "  • All LogicPanel containers (app, traefik, gateway, databases)"
echo "  • All user application containers"
echo "  • All databases and data"
echo "  • All configuration files"
echo "  • SSL certificates"
echo ""

# Handle stdin properly when piped
echo ""
read -p "Are you sure you want to uninstall LogicPanel? (y/N): " CONFIRM < /dev/tty

if [[ ! "$CONFIRM" =~ ^[Yy]$ ]]; then
    echo "Uninstall cancelled."
    exit 0
fi

# --- 2. Stop and Remove LogicPanel Containers ---
log_info "Stopping LogicPanel containers..."

# All LogicPanel containers
CONTAINERS=(
    "logicpanel_app"
    "logicpanel_traefik"
    "logicpanel_socket_proxy"
    "logicpanel_gateway"
    "logicpanel_db"
    "logicpanel_redis"
    "logicpanel_db_provisioner"
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

# Remove user app containers (logicpanel_app_service_*)
log_info "Removing user application containers..."
USER_CONTAINERS=$(docker ps -a --format '{{.Names}}' | grep "^logicpanel_app_service_" || true)
for container in $USER_CONTAINERS; do
    docker stop "$container" 2>/dev/null || true
    docker rm -f "$container" 2>/dev/null || true
    log_success "Removed user container: $container"
done

# --- 3. Remove LogicPanel Directory & Data ---
if [ -d "$INSTALL_DIR" ]; then
    log_info "Stopping services and cleaning up files..."
    cd "$INSTALL_DIR"
    
    # Check if user wants deep cleanup
    read -p "--- Do you want to delete ALL database data and user files? (y/N): " DEEP_CLEAN < /dev/tty
    if [[ "$DEEP_CLEAN" =~ ^[Yy]$ ]]; then
        log_warn "Executing deep cleanup (removing volumes)..."
        docker compose down -v 2>/dev/null || true
        # Safety confirm for the actual directory
        read -p "--- FINAL WARNING: Delete $INSTALL_DIR? (y/N): " FINAL_CONFIRM < /dev/tty
        if [[ "$FINAL_CONFIRM" =~ ^[Yy]$ ]]; then
            rm -rf "$INSTALL_DIR"
            log_success "LogicPanel directory and data removed."
        fi
    else
        docker compose down 2>/dev/null || true
        log_info "Keeping $INSTALL_DIR for data preservation."
    fi
else
    log_warn "LogicPanel directory not found at $INSTALL_DIR."
fi

# --- 4. Remove Docker Networks ---
log_info "Cleaning up Docker networks..."
# Get all logical networks from compose file if exists
if [ -f "$INSTALL_DIR/.env" ]; then
    source "$INSTALL_DIR/.env"
    if [ -n "$DOCKER_NETWORK" ]; then
        docker network rm "$DOCKER_NETWORK" 2>/dev/null || true
    fi
fi
docker network rm logicpanel_internal 2>/dev/null || true

# --- 5. Remove Cron Jobs ---
log_info "Removing LogicPanel cron jobs..."
crontab -l 2>/dev/null | grep -v "logicpanel" | grep -v "fix-ssl.sh" | crontab - 2>/dev/null || true
log_success "Cron jobs removed."

# --- 6. Firewall Cleanup ---
read -p "--- Do you want to remove LogicPanel firewall rules? (y/N): " FW_CLEAN < /dev/tty
if [[ "$FW_CLEAN" =~ ^[Yy]$ ]]; then
    log_info "Cleaning up firewall rules..."
    if command -v ufw &> /dev/null; then
        $SUDO ufw delete allow 80/tcp 2>/dev/null || true
        $SUDO ufw delete allow 443/tcp 2>/dev/null || true
        $SUDO ufw delete allow 7777/tcp 2>/dev/null || true
        $SUDO ufw delete allow 9999/tcp 2>/dev/null || true
    elif command -v firewall-cmd &> /dev/null; then
        $SUDO firewall-cmd --permanent --remove-port=80/tcp 2>/dev/null || true
        $SUDO firewall-cmd --permanent --remove-port=443/tcp 2>/dev/null || true
        $SUDO firewall-cmd --permanent --remove-port=7777/tcp 2>/dev/null || true
        $SUDO firewall-cmd --permanent --remove-port=9999/tcp 2>/dev/null || true
        $SUDO firewall-cmd --reload 2>/dev/null || true
    fi
    log_success "Firewall rules removed."
fi

# --- 7. Dependency Removal (Docker) ---
read -p "--- Do you want to UNINSTALL Docker and Docker Compose? (y/N): " DOCKER_CLEAN < /dev/tty
if [[ "$DOCKER_CLEAN" =~ ^[Yy]$ ]]; then
    log_warn "Uninstalling Docker components..."
    if command -v apt-get &> /dev/null; then
        $SUDO apt-get purge -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin docker-ce-rootless-extras
        $SUDO rm -rf /var/lib/docker
        $SUDO rm -rf /var/lib/containerd
    elif command -v dnf &> /dev/null || command -v yum &> /dev/null; then
        PKG_MANAGER=$(command -v dnf &> /dev/null && echo "dnf" || echo "yum")
        $SUDO $PKG_MANAGER remove -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
        $SUDO rm -rf /var/lib/docker
        $SUDO rm -rf /var/lib/containerd
    fi
    log_success "Docker uninstalled."
fi

# --- Success Message ---
echo ""
echo -e "${GREEN}╔═══════════════════════════════════════════════════════════════╗${NC}"
echo -e "${GREEN}║           ✓ UNINSTALLATION COMPLETE!                        ║${NC}"
echo -e "${GREEN}╚═══════════════════════════════════════════════════════════════╝${NC}"
echo ""
echo -e "  ${CYAN}All LogicPanel components have been removed.${NC}"
echo ""
echo -e "  ${YELLOW}To reinstall:${NC}"
echo -e "  ${CYAN}curl -sSL https://raw.githubusercontent.com/cyber-wahid/logicpanel/main/install.sh | sudo bash${NC}"
echo ""
echo -e "  For more info, visit: ${CYAN}https://logicdock.cloud${NC}"
echo ""
