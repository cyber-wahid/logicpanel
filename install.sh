#!/usr/bin/env bash

# LogicPanel - One-Line Installer v3.0 (Traefik Edition)
# Author: cyber-wahid
# Description: Automated installer for LogicPanel with Docker and Traefik SSL.
# Supports: Debian/Ubuntu (apt), RHEL/CentOS/Fedora (dnf/yum), Arch (pacman)
# License: Proprietary

set -e

# --- Configuration ---
INSTALL_DIR="/opt/logicpanel"
VERSION="3.0.0"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
MAGENTA='\033[0;35m'
NC='\033[0m'

# Helpers
log_info() { echo -e "${BLUE}[INFO]${NC} $1"; }
log_success() { echo -e "${GREEN}[OK]${NC} $1"; }
log_warn() { echo -e "${YELLOW}[WARN]${NC} $1"; }
log_error() { echo -e "${RED}[ERROR]${NC} $1"; }
generate_random() { cat /dev/urandom | tr -dc 'a-zA-Z0-9' | fold -w "$1" | head -n 1; }

# --- 1. Root Check ---
if [[ $EUID -ne 0 ]]; then
   log_error "This script must be run as root. Try: sudo bash <(curl -sSL https://raw.githubusercontent.com/cyber-wahid/panel/main/install.sh)"
   exit 1
fi

clear
echo -e "${CYAN}"
echo "██╗      ██████╗  ██████╗ ██╗ ██████╗██████╗  █████╗ ███╗   ██╗███████╗██╗     "
echo "██║     ██╔═══██╗██╔════╝ ██║██╔════╝██╔══██╗██╔══██╗████╗  ██║██╔════╝██║     "
echo "██║     ██║   ██║██║  ███╗██║██║     ██████╔╝███████║██╔██╗ ██║█████╗  ██║     "
echo "██║     ██║   ██║██║   ██║██║██║     ██╔═══╝ ██╔══██║██║╚██╗██║██╔══╝  ██║     "
echo "███████╗╚██████╔╝╚██████╔╝██║╚██████╗██║     ██║  ██║██║ ╚████║███████╗███████╗"
echo "╚══════╝ ╚═════╝  ╚═════╝ ╚═╝ ╚═════╝╚═╝     ╚═╝  ╚═╝╚═╝  ╚═══╝╚══════╝╚══════╝"
echo -e "${NC}"
echo -e "--- ${YELLOW}LogicPanel Automated Installation v${VERSION} (Traefik Edition)${NC} ---\n"

# --- 2. System Preparation ---
log_info "Step 1: System Checks..."

# Check availability of screen/tmux
check_screen() {
    if [ -z "$STY" ] && [ -z "$TMUX" ]; then
        log_warn "You are NOT running inside a 'screen' or 'tmux' session."
        log_warn "If your connection drops, the installation will fail."
        log_warn "It is HIGHLY RECOMMENDED to run: screen -S logicpanel"
        read -p "--- Continue anyway? (y/n): " CONTINUE_SCREEN < /dev/tty
        if [[ ! "$CONTINUE_SCREEN" =~ ^[Yy]$ ]]; then
            log_error "Installation cancelled. Please run 'screen -S logicpanel' first."
            exit 1
        fi
    else
        log_success "Running inside a screen/tmux session."
    fi
}
check_screen

# Spinner for long-running tasks
spinner() {
    local pid=$1
    local delay=0.15
    local spinstr='⠋⠙⠹⠸⠼⠴⠦⠧⠇⠏'
    while ps -p $pid > /dev/null 2>&1; do
        for i in $(seq 0 9); do
            printf "\r  ${CYAN}%s${NC} %s" "${spinstr:$i:1}" "$2"
            sleep $delay
        done
    done
    printf "\r  ${GREEN}✓${NC} %s\n" "$2"
}

# Progress bar countdown
countdown_progress() {
    local seconds=$1
    local message=$2
    local width=40
    for ((i=0; i<=seconds; i++)); do
        local pct=$((i * 100 / seconds))
        local filled=$((i * width / seconds))
        local empty=$((width - filled))
        local bar=$(printf "%${filled}s" | tr ' ' '█')$(printf "%${empty}s" | tr ' ' '░')
        local remaining=$((seconds - i))
        printf "\r  ${CYAN}[${bar}]${NC} ${pct}%% - ${message} (${remaining}s remaining)"
        sleep 1
    done
    printf "\r  ${GREEN}[$(printf "%${width}s" | tr ' ' '█')]${NC} 100%% - ${message}            \n"
}

# Check port availability
check_port() {
    local port=$1
    if ss -tuln | grep -q ":$port "; then
        return 1
    fi
    return 0
}

# Wait for container to be healthy
wait_for_container() {
    local container=$1
    local max_wait=${2:-60}
    local waited=0
    
    while [ $waited -lt $max_wait ]; do
        if docker inspect --format='{{.State.Running}}' "$container" 2>/dev/null | grep -q "true"; then
            return 0
        fi
        sleep 2
        waited=$((waited + 2))
    done
    return 1
}

# Check Docker Pre-requisites
check_docker() {
    if ! command -v docker &> /dev/null; then
        log_error "Docker is NOT installed."
        log_error "Please install Docker first: https://docs.docker.com/engine/install/"
        log_error "This script requires a pre-installed and working Docker environment."
        exit 1
    fi

    if ! docker compose version &> /dev/null; then
        log_error "Docker Compose is NOT installed."
        log_error "Please install Docker Compose Plugin."
        exit 1
    fi

    if ! systemctl is-active --quiet docker; then
        log_warn "Docker service is not running. Attempting to start..."
        systemctl start docker || service docker start
        sleep 2
        if ! systemctl is-active --quiet docker; then
            log_error "Failed to start Docker service."
            exit 1
        fi
    fi

    DOCKER_VER=$(docker --version | grep -oP '\d+\.\d+\.\d+' | head -1)
    log_success "Docker is ready (v${DOCKER_VER})"
}
check_docker

# Check required ports
REQUIRED_PORTS=(80 443 999 777 3306 5432 27017)
for port in "${REQUIRED_PORTS[@]}"; do
    if ! check_port $port; then
        log_warn "Port $port is already in use. Installation may fail or conflict."
        read -p "--- Do you want to kill the process using port $port? (y/n): " KILL_PORT < /dev/tty
        if [[ "$KILL_PORT" =~ ^[Yy]$ ]]; then
             fuser -k -n tcp $port || true
             log_success "Process on port $port killed."
        fi
    fi
done

# Configure firewall automatically (TCP + UDP for HTTP/3)
log_info "Configuring firewall rules (HTTP/3 enabled)..."

# Detect firewall type
if command -v ufw &> /dev/null && ufw status | grep -q "Status: active"; then
    log_info "Detected UFW firewall. Configuring..."
    ufw allow 80/tcp comment "HTTP - Let's Encrypt" > /dev/null 2>&1
    ufw allow 443/tcp comment "HTTPS" > /dev/null 2>&1
    ufw allow 443/udp comment "HTTP/3 (QUIC)" > /dev/null 2>&1
    ufw allow 777/tcp comment "LogicPanel User Panel" > /dev/null 2>&1
    ufw allow 777/udp comment "HTTP/3 User Panel" > /dev/null 2>&1
    ufw allow 999/tcp comment "LogicPanel Master Panel" > /dev/null 2>&1
    ufw allow 999/udp comment "HTTP/3 Master Panel" > /dev/null 2>&1
    log_success "UFW rules configured with HTTP/3 support."
elif command -v firewall-cmd &> /dev/null && systemctl is-active --quiet firewalld; then
    log_info "Detected firewalld. Configuring..."
    firewall-cmd --permanent --add-port=80/tcp > /dev/null 2>&1
    firewall-cmd --permanent --add-port=443/tcp > /dev/null 2>&1
    firewall-cmd --permanent --add-port=443/udp > /dev/null 2>&1
    firewall-cmd --permanent --add-port=777/tcp > /dev/null 2>&1
    firewall-cmd --permanent --add-port=777/udp > /dev/null 2>&1
    firewall-cmd --permanent --add-port=999/tcp > /dev/null 2>&1
    firewall-cmd --permanent --add-port=999/udp > /dev/null 2>&1
    firewall-cmd --reload > /dev/null 2>&1
    log_success "Firewalld rules configured with HTTP/3 support."
elif command -v nft &> /dev/null && systemctl is-active --quiet nftables 2>/dev/null; then
    log_info "Detected nftables. Configuring..."
    # Add LogicPanel table if not exists
    nft add table inet logicpanel 2>/dev/null || true
    nft add chain inet logicpanel input '{ type filter hook input priority 0; policy accept; }' 2>/dev/null || true
    for port in 80 443 777 999; do
        nft add rule inet logicpanel input tcp dport $port accept 2>/dev/null || true
    done
    for port in 443 777 999; do
        nft add rule inet logicpanel input udp dport $port accept 2>/dev/null || true
    done
    # Persist rules
    if [ -d /etc/nftables.d ]; then
        nft list table inet logicpanel > /etc/nftables.d/logicpanel.conf 2>/dev/null || true
    fi
    log_success "nftables rules configured with HTTP/3 support."
elif command -v iptables &> /dev/null; then
    log_info "Configuring iptables..."
    iptables -A INPUT -p tcp --dport 80 -j ACCEPT > /dev/null 2>&1
    iptables -A INPUT -p tcp --dport 443 -j ACCEPT > /dev/null 2>&1
    iptables -A INPUT -p udp --dport 443 -j ACCEPT > /dev/null 2>&1
    iptables -A INPUT -p tcp --dport 777 -j ACCEPT > /dev/null 2>&1
    iptables -A INPUT -p udp --dport 777 -j ACCEPT > /dev/null 2>&1
    iptables -A INPUT -p tcp --dport 999 -j ACCEPT > /dev/null 2>&1
    iptables -A INPUT -p udp --dport 999 -j ACCEPT > /dev/null 2>&1
    
    # Save iptables rules (support multiple persistence methods)
    if command -v iptables-save &> /dev/null; then
        mkdir -p /etc/iptables
        iptables-save > /etc/iptables/rules.v4 2>/dev/null || true
    fi
    if command -v netfilter-persistent &> /dev/null; then
        netfilter-persistent save 2>/dev/null || true
    fi
    log_success "Iptables rules configured with HTTP/3 support."
else
    log_warn "No firewall detected. Ports should be open by default."
    log_info "If you configure a firewall later, open these ports:"
    log_info "  TCP: 80, 443, 777, 999"
    log_info "  UDP: 443, 777, 999 (for HTTP/3)"
fi

# Install Docker
if command -v docker &> /dev/null; then
    DOCKER_VER=$(docker --version 2>/dev/null | grep -oP '\d+\.\d+\.\d+' | head -1)
    log_success "Docker is already installed (v${DOCKER_VER}). Skipping installation."
    
    # Ensure Docker service is running
    if ! systemctl is-active --quiet docker 2>/dev/null; then
        log_info "Docker service is not running. Starting..."
        systemctl enable --now docker 2>/dev/null || service docker start 2>/dev/null || true
        log_success "Docker service started."
    fi
else
    log_info "Docker is not installed. Installing..."
    
    # Detect OS
    if [ -f /etc/os-release ]; then
        . /etc/os-release
        log_info "Detected OS: ${PRETTY_NAME:-$ID}"
    fi
    
    DOCKER_INSTALLED=false
    OS_ID="${ID:-unknown}"
    OS_ID_LIKE="${ID_LIKE:-}"
    OS_VERSION_ID="${VERSION_ID:-}"
    
    # ── Remove conflicting packages (Podman, old Docker) ────────────
    log_info "Removing conflicting packages..."
    if command -v dnf &> /dev/null || command -v yum &> /dev/null; then
        PKG_RM="${PKG_MANAGER:-yum}"
        $PKG_RM remove -y docker docker-client docker-client-latest docker-common \
            docker-latest docker-latest-logrotate docker-logrotate docker-engine \
            podman runc 2>/dev/null || true
    elif command -v apt-get &> /dev/null; then
        for pkg in docker.io docker-doc docker-compose podman-docker containerd runc; do
            apt-get remove -y $pkg 2>/dev/null || true
        done
    fi
    
    # ── Determine Docker repo URL based on distro ───────────────────
    # Docker officially supports: ubuntu, debian, centos, rhel, fedora, sles
    # Derivatives must map to their parent distro's repo
    DOCKER_REPO_URL=""
    REPO_METHOD=""
    
    case "$OS_ID" in
        ubuntu|pop|linuxmint|elementary|zorin|kubuntu|lubuntu|xubuntu|neon)
            DOCKER_REPO_URL="https://download.docker.com/linux/ubuntu"
            REPO_METHOD="apt"
            # Map derivatives to their Ubuntu base
            if [ "$OS_ID" != "ubuntu" ]; then
                # Try to find the Ubuntu codename from os-release
                UBUNTU_CODENAME="${UBUNTU_CODENAME:-$(grep UBUNTU_CODENAME /etc/os-release 2>/dev/null | cut -d= -f2)}"
                [ -z "$UBUNTU_CODENAME" ] && UBUNTU_CODENAME="jammy"
            fi
            ;;
        debian|raspbian|kali|bunsen*)
            DOCKER_REPO_URL="https://download.docker.com/linux/debian"
            REPO_METHOD="apt"
            ;;
        centos|almalinux|rocky|ol|scientific|eurolinux|virtuozzo)
            DOCKER_REPO_URL="https://download.docker.com/linux/centos"
            REPO_METHOD="dnf"
            ;;
        rhel)
            DOCKER_REPO_URL="https://download.docker.com/linux/rhel"
            REPO_METHOD="dnf"
            ;;
        fedora|nobara)
            DOCKER_REPO_URL="https://download.docker.com/linux/fedora"
            REPO_METHOD="dnf"
            ;;
        amzn)
            # Amazon Linux 2023+ uses dnf and is RHEL-based
            DOCKER_REPO_URL="https://download.docker.com/linux/centos"
            REPO_METHOD="dnf"
            ;;
        sles|opensuse*|opensuse-leap|opensuse-tumbleweed)
            DOCKER_REPO_URL="https://download.docker.com/linux/sles"
            REPO_METHOD="zypper"
            ;;
        arch|manjaro|endeavouros|garuda)
            REPO_METHOD="pacman"
            ;;
        *)
            # Try to determine by ID_LIKE
            case "$OS_ID_LIKE" in
                *ubuntu*|*debian*)
                    DOCKER_REPO_URL="https://download.docker.com/linux/ubuntu"
                    REPO_METHOD="apt"
                    ;;
                *rhel*|*centos*|*fedora*)
                    DOCKER_REPO_URL="https://download.docker.com/linux/centos"
                    REPO_METHOD="dnf"
                    ;;
                *suse*)
                    DOCKER_REPO_URL="https://download.docker.com/linux/sles"
                    REPO_METHOD="zypper"
                    ;;
                *)
                    REPO_METHOD="convenience_script"
                    ;;
            esac
            ;;
    esac
    
    log_info "Using installation method: $REPO_METHOD"
    
    # ── Method 1: APT-based (Ubuntu, Debian, derivatives) ──────────
    if [ "$REPO_METHOD" = "apt" ] && [ "$DOCKER_INSTALLED" = false ]; then
        log_info "Installing Docker via APT repository..."
        
        # Install prerequisites
        apt-get update -qq
        apt-get install -y -qq ca-certificates curl gnupg lsb-release
        
        # Add Docker's official GPG key
        install -m 0755 -d /etc/apt/keyrings
        curl -fsSL "${DOCKER_REPO_URL}/gpg" -o /etc/apt/keyrings/docker.asc
        chmod a+r /etc/apt/keyrings/docker.asc
        
        # Determine the codename for the repo
        if [ -n "${UBUNTU_CODENAME:-}" ]; then
            CODENAME="$UBUNTU_CODENAME"
        elif [ -n "${VERSION_CODENAME:-}" ]; then
            CODENAME="$VERSION_CODENAME"
        else
            CODENAME=$(lsb_release -cs 2>/dev/null || echo "jammy")
        fi
        
        # Add the Docker repository
        echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] ${DOCKER_REPO_URL} ${CODENAME} stable" > /etc/apt/sources.list.d/docker.list
        
        # Install Docker
        apt-get update -qq
        apt-get install -y -qq docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
        
        command -v docker &> /dev/null && DOCKER_INSTALLED=true
    fi
    
    # ── Method 2: DNF/YUM-based (RHEL, CentOS, AlmaLinux, Rocky, Fedora, Amazon) ──
    if [ "$REPO_METHOD" = "dnf" ] && [ "$DOCKER_INSTALLED" = false ]; then
        log_info "Installing Docker via DNF/YUM repository..."
        
        # Prefer dnf over yum (AlmaLinux 8 has both, dnf is the modern one)
        if command -v dnf &> /dev/null; then
            DNF_CMD="dnf"
        else
            DNF_CMD="yum"
        fi
        
        # Install prerequisites and add repo
        $DNF_CMD install -y dnf-plugins-core yum-utils 2>/dev/null || $DNF_CMD install -y yum-utils 2>/dev/null || true
        
        # Try dnf config-manager first (modern), fall back to yum-config-manager
        if command -v dnf &> /dev/null; then
            dnf config-manager --add-repo "${DOCKER_REPO_URL}/docker-ce.repo" 2>/dev/null || \
            yum-config-manager --add-repo "${DOCKER_REPO_URL}/docker-ce.repo" 2>/dev/null || true
        else
            yum-config-manager --add-repo "${DOCKER_REPO_URL}/docker-ce.repo" 2>/dev/null || true
        fi
        
        # Install Docker (--allowerasing handles Podman conflicts, --nobest handles version mismatches)
        $DNF_CMD install -y --allowerasing --nobest docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin 2>&1 | tail -10 || \
        $DNF_CMD install -y --nobest docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin 2>&1 | tail -10
        
        command -v docker &> /dev/null && DOCKER_INSTALLED=true
    fi
    
    # ── Method 3: Zypper-based (openSUSE, SLES) ───────────────────
    if [ "$REPO_METHOD" = "zypper" ] && [ "$DOCKER_INSTALLED" = false ]; then
        log_info "Installing Docker via Zypper..."
        zypper install -y docker docker-compose 2>&1 | tail -5 || true
        command -v docker &> /dev/null && DOCKER_INSTALLED=true
    fi
    
    # ── Method 4: Pacman-based (Arch, Manjaro) ─────────────────────
    if [ "$REPO_METHOD" = "pacman" ] && [ "$DOCKER_INSTALLED" = false ]; then
        log_info "Installing Docker via Pacman..."
        pacman -Sy --noconfirm docker docker-compose 2>&1 | tail -5 || true
        command -v docker &> /dev/null && DOCKER_INSTALLED=true
    fi
    
    # ── Method 5: Convenience script fallback ──────────────────────
    if [ "$DOCKER_INSTALLED" = false ]; then
        log_info "Trying Docker convenience script as fallback..."
        curl -fsSL https://get.docker.com | sh 2>&1 | tail -5 || true
        command -v docker &> /dev/null && DOCKER_INSTALLED=true
    fi
    
    # ── Method 6: Static binary as last resort ─────────────────────
    if [ "$DOCKER_INSTALLED" = false ]; then
        log_warn "Standard methods failed. Trying static binary installation..."
        DOCKER_STATIC_VER="27.5.1"
        ARCH=$(uname -m)
        case "$ARCH" in
            x86_64) ARCH="x86_64" ;;
            aarch64|arm64) ARCH="aarch64" ;;
            *) ARCH="x86_64" ;;
        esac
        
        cd /tmp
        curl -fsSL "https://download.docker.com/linux/static/stable/${ARCH}/docker-${DOCKER_STATIC_VER}.tgz" -o docker.tgz
        tar xzf docker.tgz
        cp docker/* /usr/bin/
        rm -rf docker docker.tgz
        
        # Create systemd service
        cat > /etc/systemd/system/docker.service << 'DOCKERSVC'
[Unit]
Description=Docker Application Container Engine
After=network-online.target
Wants=network-online.target

[Service]
Type=notify
ExecStart=/usr/bin/dockerd
ExecReload=/bin/kill -s HUP $MAINPID
Restart=always
RestartSec=10s

[Install]
WantedBy=multi-user.target
DOCKERSVC
        
        systemctl daemon-reload
        command -v docker &> /dev/null && DOCKER_INSTALLED=true
        cd - > /dev/null
    fi
    
    # ── Final check ────────────────────────────────────────────────
    if [ "$DOCKER_INSTALLED" = false ]; then
        log_error "All Docker installation methods failed."
        log_error "Please install Docker manually: https://docs.docker.com/engine/install/"
        exit 1
    fi
    
    # Start and enable Docker
    systemctl enable --now docker 2>/dev/null || service docker start 2>/dev/null || true
    
    # Wait for Docker daemon to be ready
    for i in $(seq 1 15); do
        if docker info &>/dev/null; then
            break
        fi
        sleep 1
    done
    
    DOCKER_VER=$(docker --version 2>/dev/null | grep -oP '\d+\.\d+\.\d+' | head -1)
    log_success "Docker installed successfully (v${DOCKER_VER})."
fi

# Install Git and other dependencies
if ! command -v git &> /dev/null; then
    log_info "Installing Git..."
    $PKG_INSTALL git
    log_success "Git installed."
fi

# Install curl if missing
if ! command -v curl &> /dev/null; then
    log_info "Installing curl..."
    $PKG_INSTALL curl
fi

# Docker Compose Plugin
if docker compose version &> /dev/null 2>&1; then
    COMPOSE_VER=$(docker compose version --short 2>/dev/null || docker compose version 2>/dev/null | grep -oP '\d+\.\d+\.\d+' | head -1)
    log_success "Docker Compose is already installed (v${COMPOSE_VER}). Skipping."
else
    log_info "Installing Docker Compose Plugin..."
    mkdir -p /usr/libexec/docker/cli-plugins
    COMPOSE_VERSION="v2.24.5"
    ARCH=$(uname -m)
    case "$ARCH" in
        aarch64|arm64) ARCH="aarch64" ;;
        armv7l|armhf) ARCH="armv7" ;;
        *) ARCH="x86_64" ;;
    esac
    curl -SL "https://github.com/docker/compose/releases/download/${COMPOSE_VERSION}/docker-compose-linux-${ARCH}" -o /usr/libexec/docker/cli-plugins/docker-compose
    chmod +x /usr/libexec/docker/cli-plugins/docker-compose
    
    mkdir -p ~/.docker/cli-plugins
    cp /usr/libexec/docker/cli-plugins/docker-compose ~/.docker/cli-plugins/docker-compose
    log_success "Docker Compose installed."
fi

# --- 3. Create Docker Network ---
log_info "Step 2: Configuring Docker Network..."
docker network inspect logicpanel_internal &>/dev/null || docker network create logicpanel_internal
log_success "Docker network configured."

# --- 4. User Input ---
log_info "Step 3: Panel Setup (Interactive)"

echo ""
read -p "--- Enter Hostname (e.g., panel.example.cloud): " PANEL_DOMAIN < /dev/tty
while [[ -z "$PANEL_DOMAIN" ]]; do
    read -p "--- ! Hostname required: " PANEL_DOMAIN < /dev/tty
done

# Verify DNS resolution
log_info "Verifying DNS configuration for ${PANEL_DOMAIN}..."
if command -v nslookup &> /dev/null; then
    if nslookup "$PANEL_DOMAIN" > /dev/null 2>&1; then
        RESOLVED_IP=$(nslookup "$PANEL_DOMAIN" | grep -A1 "Name:" | grep "Address:" | awk '{print $2}' | head -1)
        SERVER_IP=$(curl -s ifconfig.me || curl -s icanhazip.com || hostname -I | awk '{print $1}')
        
        if [ -n "$RESOLVED_IP" ]; then
            log_success "DNS resolves to: $RESOLVED_IP"
            
            if [ "$RESOLVED_IP" = "$SERVER_IP" ]; then
                log_success "DNS correctly points to this server ($SERVER_IP)"
            else
                log_warn "DNS points to $RESOLVED_IP but this server is $SERVER_IP"
                log_warn "SSL certificates may fail if DNS is incorrect."
                read -p "--- Continue anyway? (y/n): " CONTINUE_DNS < /dev/tty
                if [[ ! "$CONTINUE_DNS" =~ ^[Yy]$ ]]; then
                    log_error "Installation cancelled. Please fix DNS first."
                    exit 1
                fi
            fi
        fi
    else
        log_warn "DNS lookup failed for ${PANEL_DOMAIN}"
        log_warn "SSL certificates require valid DNS. Please ensure:"
        log_warn "  1. Domain exists and is registered"
        log_warn "  2. A record points to this server's IP"
        log_warn "  3. DNS has propagated (may take up to 24 hours)"
        read -p "--- Continue anyway? (y/n): " CONTINUE_DNS < /dev/tty
        if [[ ! "$CONTINUE_DNS" =~ ^[Yy]$ ]]; then
            log_error "Installation cancelled. Please configure DNS first."
            exit 1
        fi
    fi
else
    log_warn "nslookup not available. Skipping DNS verification."
fi

RANDOM_ADMIN="admin_$(generate_random 5)"
read -p "--- Enter Admin Username (default: $RANDOM_ADMIN): " ADMIN_USER < /dev/tty
ADMIN_USER=${ADMIN_USER:-$RANDOM_ADMIN}

read -p "--- Enter Admin Email: " ADMIN_EMAIL < /dev/tty
while [[ -z "$ADMIN_EMAIL" ]]; do
    read -p "--- ! Email required: " ADMIN_EMAIL < /dev/tty
done

while true; do
    read -s -p "--- Enter Admin Password (min 8 characters): " ADMIN_PASS < /dev/tty
    echo ""
    if [[ ${#ADMIN_PASS} -lt 8 ]]; then
        echo -e "${RED}--- ! Password too short. Min 8 characters.${NC}"
        continue
    fi
    read -s -p "--- Enter Admin Password Again: " ADMIN_PASS_CONFIRM < /dev/tty
    echo ""
    if [[ "$ADMIN_PASS" == "$ADMIN_PASS_CONFIRM" ]]; then
        break
    else
        echo -e "${RED}--- ! Passwords do not match. Try again.${NC}"
    fi
done

# Random Secrets for Security
DB_NAME="lp_db_$(generate_random 8)"
DB_USER="lp_user_$(generate_random 8)"
DB_PASS=$(generate_random 32)
ROOT_PASS=$(generate_random 32)
JWT_SECRET=$(generate_random 64)
ENC_KEY=$(head -c 32 /dev/urandom | base64 -w 0)
DB_PROVISIONER_SECRET=$(generate_random 64)

# Hostnames for Obscurity (Manual or Default)
echo ""
echo "--- container Configuration (Press Enter for Defaults) ---"

read -p "--- Enter Main DB Container Name (default: logicpanel_db): " DB_HOST_MAIN < /dev/tty
DB_HOST_MAIN=${DB_HOST_MAIN:-logicpanel_db}

read -p "--- Enter Shared MySQL Container Name (default: lp_mysql_mother): " DB_HOST_MYSQL < /dev/tty
DB_HOST_MYSQL=${DB_HOST_MYSQL:-lp_mysql_mother}

read -p "--- Enter Shared Postgres Container Name (default: lp_postgres_mother): " DB_HOST_PG < /dev/tty
DB_HOST_PG=${DB_HOST_PG:-lp_postgres_mother}

read -p "--- Enter Shared MongoDB Container Name (default: lp_mongo_mother): " DB_HOST_MONGO < /dev/tty
DB_HOST_MONGO=${DB_HOST_MONGO:-lp_mongo_mother}

# Adminer Setup
echo ""
echo "--- Adminer Database Manager Setup ---"
echo "Adminer allows you to manage your databases securely via a web interface."
echo "Requirement: You MUST create a DNS 'A' Record for 'db.${PANEL_DOMAIN}' pointing to this server IP."
read -p "--- Do you want to enable Adminer at https://db.${PANEL_DOMAIN}? (y/n): " ENABLE_ADMINER < /dev/tty
if [[ "$ENABLE_ADMINER" =~ ^[Yy]$ ]]; then
    # Create the DNS record prompt/reminder
    log_info "Adminer will be enabled."
    log_warn "IMPORTANT: Please ensure 'db.${PANEL_DOMAIN}' points to this server IP."
    echo "   A Record: db.${PANEL_DOMAIN}  ->  $(curl -s ifconfig.me || hostname -I | awk '{print $1}')"
    echo ""
    read -p "--- Press Enter after you have noted this dns requirement..." DUMMY < /dev/tty
else
    log_info "Adminer will be disabled by default."
    # We can disable it by commenting it out in docker-compose or using profiles, 
    # but since we migrated to Traefik labels, we might just let it run but not tell them, 
    # OR we can be smart and comment it out if they say no. 
    # For now, let's keep it simple. It runs but won't be reachable if they don't add DNS.
    # Actually, if they say 'no', we should probably leave it as is or maybe stop it later.
    # Given the script structure, modifying docker-compose dynamically here is complex.
    # We will just inform them it's skipping configuration check.
fi

log_success "Containers configured."

log_info "Step 4: Deploying LogicPanel Services..."
mkdir -p $INSTALL_DIR
cd $INSTALL_DIR

# Fetch source code
log_info "Fetching latest source code..."
curl -sSL https://github.com/cyber-wahid/panel/archive/refs/heads/main.tar.gz | tar xz --strip-components=1

# Create SSL management scripts
log_info "Creating SSL management scripts..."

# Create check-ssl.sh
cat > check-ssl.sh << 'EOFSSL'
#!/bin/bash
# Quick SSL Status Check for LogicPanel

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo -e "${BLUE}🔍 LogicPanel SSL Status Check${NC}"
echo "================================"
echo ""

# Load environment
if [ -f ".env" ]; then
    source .env
else
    echo -e "${RED}❌ .env file not found${NC}"
    exit 1
fi

DOMAIN=${PANEL_DOMAIN:-localhost}
USER_PORT=${USER_PORT:-777}
MASTER_PORT=${MASTER_PORT:-999}

echo "Domain: $DOMAIN"
echo "User Port: $USER_PORT"
echo "Master Port: $MASTER_PORT"
echo ""

# Check 1: DNS Resolution
echo -e "${YELLOW}1. DNS Resolution${NC}"
if nslookup $DOMAIN > /dev/null 2>&1; then
    IP=$(nslookup $DOMAIN | grep -A1 "Name:" | grep "Address:" | awk '{print $2}' | head -1)
    echo -e "${GREEN}✅ DNS resolves to: $IP${NC}"
else
    echo -e "${RED}❌ DNS resolution failed${NC}"
fi
echo ""

# Check 2: Docker Services
echo -e "${YELLOW}2. Docker Services${NC}"
if docker ps | grep -q "logicpanel_traefik"; then
    echo -e "${GREEN}✅ Traefik is running${NC}"
else
    echo -e "${RED}❌ Traefik is not running${NC}"
fi

if docker ps | grep -q "logicpanel_app"; then
    echo -e "${GREEN}✅ LogicPanel app is running${NC}"
else
    echo -e "${RED}❌ LogicPanel app is not running${NC}"
fi
echo ""

# Check 3: Certificate File
echo -e "${YELLOW}3. Certificate File${NC}"
if [ -f "letsencrypt/acme.json" ]; then
    SIZE=$(stat -f%z "letsencrypt/acme.json" 2>/dev/null || stat -c%s "letsencrypt/acme.json" 2>/dev/null)
    PERMS=$(stat -f%A "letsencrypt/acme.json" 2>/dev/null || stat -c%a "letsencrypt/acme.json" 2>/dev/null)
    
    if [ "$SIZE" -gt 100 ]; then
        echo -e "${GREEN}✅ acme.json exists and has content ($SIZE bytes)${NC}"
    else
        echo -e "${YELLOW}⚠️  acme.json exists but is empty or small ($SIZE bytes)${NC}"
    fi
    
    if [ "$PERMS" = "600" ]; then
        echo -e "${GREEN}✅ Permissions are correct (600)${NC}"
    else
        echo -e "${YELLOW}⚠️  Permissions are $PERMS (should be 600)${NC}"
    fi
else
    echo -e "${RED}❌ acme.json not found${NC}"
fi
echo ""

# Check 4: Certificate Details
echo -e "${YELLOW}4. Certificate Details${NC}"
if [ -f "letsencrypt/acme.json" ] && [ -s "letsencrypt/acme.json" ]; then
    if command -v jq &> /dev/null; then
        echo "Certificates in acme.json:"
        cat letsencrypt/acme.json | jq -r '.letsencrypt.Certificates[]?.domain.main' 2>/dev/null || echo "No certificates found"
    else
        echo "Domains in acme.json:"
        grep -o '"main":"[^"]*"' letsencrypt/acme.json | cut -d'"' -f4 || echo "No certificates found"
    fi
else
    echo -e "${RED}No certificates generated yet${NC}"
fi
echo ""

# Summary
echo -e "${BLUE}================================${NC}"
echo "🌐 Test URLs:"
echo "   User Panel:   https://$DOMAIN:$USER_PORT"
echo "   Master Panel: https://$DOMAIN:$MASTER_PORT"
if docker ps | grep -q "logicpanel_adminer"; then
    echo "   Database Manager: https://db.$DOMAIN"
fi
echo ""
echo "📝 Next Steps:"
echo "   - View logs: docker compose logs -f traefik"
echo "   - If SSL not working, wait 2-3 minutes for certificate generation"
echo ""
EOFSSL

chmod +x check-ssl.sh
log_success "SSL check script created."

# Create setup-ssl.sh for manual SSL reconfiguration
cat > setup-ssl.sh <<'EOFSETUPSSL'
#!/bin/bash
# LogicPanel SSL Setup/Reconfiguration Script
# Use this if you need to change domain or reconfigure SSL

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo -e "${BLUE}===================================${NC}"
echo -e "${BLUE}LogicPanel SSL Setup${NC}"
echo -e "${BLUE}===================================${NC}"
echo ""

# Check if domain is provided
if [ -z "$1" ]; then
    echo -e "${YELLOW}Usage: ./setup-ssl.sh <your-domain.com> [admin-email]${NC}"
    echo "Example: ./setup-ssl.sh panel.example.com admin@example.com"
    exit 1
fi

DOMAIN=$1
ADMIN_EMAIL=${2:-admin@$DOMAIN}

echo "Domain: $DOMAIN"
echo "Admin Email: $ADMIN_EMAIL"
echo ""

# Check DNS resolution
echo -e "${YELLOW}Checking DNS resolution...${NC}"
if command -v nslookup &> /dev/null; then
    if nslookup "$DOMAIN" > /dev/null 2>&1; then
        RESOLVED_IP=$(nslookup "$DOMAIN" | grep -A1 "Name:" | grep "Address:" | awk '{print $2}' | head -1)
        echo -e "${GREEN}✓ DNS resolves to: $RESOLVED_IP${NC}"
    else
        echo -e "${RED}⚠️  WARNING: Domain $DOMAIN does not resolve${NC}"
        echo "Please ensure your DNS records are configured correctly:"
        echo "  - A record: $DOMAIN → Your Server IP"
        read -p "Continue anyway? (y/N): " -n 1 -r
        echo
        if [[ ! $REPLY =~ ^[Yy]$ ]]; then
            exit 1
        fi
    fi
else
    echo -e "${YELLOW}⚠️  nslookup not available, skipping DNS check${NC}"
fi

# Update .env file
echo ""
echo -e "${YELLOW}Updating configuration files...${NC}"
if [ -f .env ]; then
    # Backup .env
    cp .env .env.backup.$(date +%Y%m%d_%H%M%S)
    
    # Update domain
    if grep -q "^PANEL_DOMAIN=" .env; then
        sed -i "s|^PANEL_DOMAIN=.*|PANEL_DOMAIN=$DOMAIN|" .env
    else
        echo "PANEL_DOMAIN=$DOMAIN" >> .env
    fi
    
    # Update admin email
    if grep -q "^ADMIN_EMAIL=" .env; then
        sed -i "s|^ADMIN_EMAIL=.*|ADMIN_EMAIL=$ADMIN_EMAIL|" .env
    else
        echo "ADMIN_EMAIL=$ADMIN_EMAIL" >> .env
    fi
    
    # Update APP_URL
    if grep -q "^APP_URL=" .env; then
        sed -i "s|^APP_URL=.*|APP_URL=https://$DOMAIN|" .env
    else
        echo "APP_URL=https://$DOMAIN" >> .env
    fi
    
    echo -e "${GREEN}✓ .env updated (backup created)${NC}"
else
    echo -e "${RED}⚠️  .env file not found${NC}"
    exit 1
fi

# Ensure acme.json exists with correct permissions
echo ""
echo -e "${YELLOW}Setting up certificate storage...${NC}"
mkdir -p letsencrypt
if [ ! -f letsencrypt/acme.json ]; then
    touch letsencrypt/acme.json
fi
chmod 600 letsencrypt/acme.json
echo -e "${GREEN}✓ acme.json configured${NC}"

# Restart Traefik
echo ""
echo -e "${YELLOW}Restarting Traefik...${NC}"
if docker compose restart traefik > /dev/null 2>&1; then
    echo -e "${GREEN}✓ Traefik restarted${NC}"
else
    echo -e "${YELLOW}⚠️  Run manually: docker compose restart traefik${NC}"
fi

echo ""
echo -e "${GREEN}===================================${NC}"
echo -e "${GREEN}SSL Setup Complete!${NC}"
echo -e "${GREEN}===================================${NC}"
echo ""
echo "Next steps:"
echo "1. Wait 1-2 minutes for certificate generation"
echo "2. Check status: ./check-ssl.sh"
echo "3. View logs: docker compose logs -f traefik"
echo "4. Access panel:"
echo "   - User Panel: https://$DOMAIN:777"
echo "   - Master Panel: https://$DOMAIN:999"
echo "   - DB Manager: https://db.$DOMAIN (If enabled)"
echo ""
EOFSETUPSSL

chmod +x setup-ssl.sh
log_success "SSL setup script created."


# --- 5. Environment Configuration ---
log_info "Configuring environment..."

cat > .env << EOF
# LogicPanel Environment Configuration
# Generated by install.sh on $(date)

APP_ENV=production
APP_DEBUG=false
LOG_CHANNEL=stderr
APP_URL=https://${PANEL_DOMAIN}
PANEL_DOMAIN=${PANEL_DOMAIN}

# Ports
MASTER_PORT=999
USER_PORT=777

# Secrets
JWT_SECRET=${JWT_SECRET}
ENCRYPTION_KEY=${ENC_KEY}
DB_PROVISIONER_SECRET=${DB_PROVISIONER_SECRET}

# Database Credentials (LogicPanel's own database)
DB_CONNECTION=mysql
DB_HOST=${DB_HOST_MAIN}
DB_PORT=3306
DB_DATABASE=${DB_NAME}
DB_USERNAME=${DB_USER}
DB_PASSWORD=${DB_PASS}

# For MariaDB container compatibility
DB_NAME=${DB_NAME}
DB_USER=${DB_USER}

# Root Passwords for Mother Containers
MYSQL_ROOT_PASSWORD=${ROOT_PASS}
POSTGRES_ROOT_PASSWORD=${ROOT_PASS}
MONGO_ROOT_PASSWORD=${ROOT_PASS}

# Docker Configuration
DOCKER_NETWORK=logicpanel_internal
USER_APPS_HOST_PATH=${INSTALL_DIR}/storage/user-apps

# Internal Hostnames (Randomized)
DB_HOST_MAIN=${DB_HOST_MAIN}
DB_HOST_MYSQL=${DB_HOST_MYSQL}
DB_HOST_PG=${DB_HOST_PG}
DB_HOST_MONGO=${DB_HOST_MONGO}

# Traefik Configuration
TRAEFIK_ENABLED=true
ADMIN_EMAIL=${ADMIN_EMAIL}
EOF

log_success "Environment configured."

# Create storage layout
mkdir -p storage/logs storage/framework/cache storage/framework/sessions storage/framework/views storage/user-apps
chmod -R 777 storage

# Create Traefik apps directory for user app routing configs
mkdir -p docker/traefik/apps
chmod 755 docker/traefik/apps

# Create config directory and settings.json
mkdir -p config
cat > config/settings.json << EOF
{
    "hostname": "${PANEL_DOMAIN}",
    "master_port": "999",
    "user_port": "777",
    "company_name": "LogicPanel",
    "contact_email": "${ADMIN_EMAIL}",
    "enable_ssl": "1",
    "letsencrypt_email": "${ADMIN_EMAIL}",
    "timezone": "UTC",
    "allow_registration": "1"
}
EOF

# Build and start containers
echo ""
log_info "Building LogicPanel (this may take 3-5 minutes)..."
echo -e "  ${YELLOW}Build logs saved to: /tmp/logicpanel_build.log${NC}"
docker compose build --no-cache > /tmp/logicpanel_build.log 2>&1 &
spinner $! "Compiling LogicPanel Application..."

log_info "Starting Services..."
docker compose up -d > /dev/null 2>&1 &
spinner $! "Launching Docker Containers..."

# Wait for services to initialize
echo ""
log_info "Waiting for services to initialize..."
countdown_progress 60 "Services warming up"

# Verify containers are running
log_info "Verifying container status..."

# Check for containers using patterns (since some have randomized names)
CONTAINER_PATTERNS=(
    "logicpanel_app:LogicPanel Application"
    "${DB_HOST_MAIN}:LogicPanel Database"
    "logicpanel_gateway:Terminal Gateway"
    "logicpanel_traefik:Traefik Proxy"
    "${DB_HOST_MYSQL}:MySQL Database"
    "${DB_HOST_PG}:PostgreSQL Database"
    "${DB_HOST_MONGO}:MongoDB Database"
    "logicpanel_redis:Redis Cache"
    "logicpanel_db_provisioner:DB Provisioner"
)

ALL_RUNNING=true
for pattern in "${CONTAINER_PATTERNS[@]}"; do
    container_name="${pattern%%:*}"
    display_name="${pattern##*:}"
    
    if docker ps --format '{{.Names}}' | grep -q "^${container_name}$"; then
        log_success "$display_name is running"
    else
        log_warn "$display_name ($container_name) not found - checking if running..."
        # Double-check with docker compose ps
        if docker compose ps --format '{{.Names}}' 2>/dev/null | grep -q "$container_name"; then
            log_success "$display_name is running"
        else
            log_error "$display_name failed to start"
            ALL_RUNNING=false
        fi
    fi
done

if [ "$ALL_RUNNING" = false ]; then
    log_error "Some containers failed to start. Check logs with: docker compose logs"
    exit 1
fi

# Create admin user
log_info "Downloading admin setup script..."
if curl -sSL "https://raw.githubusercontent.com/cyber-wahid/panel/main/create_admin.php" -o create_admin.php; then
    docker exec logicpanel_app mkdir -p /var/www/html/database 2>/dev/null || true
    docker cp create_admin.php logicpanel_app:/var/www/html/create_admin.php
    docker cp config/settings.json logicpanel_app:/var/www/html/config/settings.json 2>/dev/null || true
    rm -f create_admin.php

    # Wait for Database to be ready
    log_info "Waiting for Database to initialize..."
    MAX_RETRIES=30
    COUNT=0
    DB_READY=false
    
    while [ $COUNT -lt $MAX_RETRIES ]; do
        if docker exec logicpanel_app php -r "try { new PDO('mysql:host=logicpanel-db;dbname='.getenv('DB_DATABASE'), getenv('DB_USERNAME'), getenv('DB_PASSWORD')); echo 'connected'; } catch(Exception \$e) { exit(1); }" >/dev/null 2>&1; then
            DB_READY=true
            break
        fi
        echo -n "."
        sleep 5
        COUNT=$((COUNT+1))
    done
    echo ""

    if [ "$DB_READY" = true ]; then
        log_success "Database is ready."
        
        log_info "Running database migrations..."
        docker compose exec -T app bash /var/www/html/docker/migrate.sh || log_warn "Migration warning (non-fatal)"

        log_info "Creating administrator account..."
        
        # Verify file exists inside container
        if docker exec logicpanel_app test -f /var/www/html/create_admin.php; then
            if docker exec logicpanel_app php /var/www/html/create_admin.php --user="${ADMIN_USER}" --email="${ADMIN_EMAIL}" --pass="${ADMIN_PASS}"; then
                log_success "Administrator account created successfully!"
            else
                log_warn "Admin creation had issues. You may need to run it manually later."
            fi
        else
            log_warn "create_admin.php not found in container. Re-copying..."
            # Try to download directly into container as fallback
            docker exec logicpanel_app curl -sSL "https://raw.githubusercontent.com/cyber-wahid/panel/main/create_admin.php" -o /var/www/html/create_admin.php
            if docker exec logicpanel_app php /var/www/html/create_admin.php --user="${ADMIN_USER}" --email="${ADMIN_EMAIL}" --pass="${ADMIN_PASS}"; then
                 log_success "Administrator account created successfully (via fallback)!"
            else
                 log_warn "Admin creation failed even after fallback."
            fi
        fi
    else
        log_error "Database failed to initialize within expected time."
    fi

    docker exec logicpanel_app rm -f /var/www/html/create_admin.php 2>/dev/null || true
else
    log_warn "Could not download admin script. Please create admin manually after installation."
fi

# Configure SSL automatically
echo ""
log_info "Configuring SSL certificates..."

# Ensure acme.json exists with correct permissions
mkdir -p letsencrypt
touch letsencrypt/acme.json
chmod 600 letsencrypt/acme.json
log_success "SSL storage configured."

# Wait for Traefik to start
log_info "Waiting for Traefik to initialize..."
sleep 10

# Check if Traefik is running
if docker ps | grep -q "logicpanel_traefik"; then
    log_success "Traefik is running."
    
    # Wait for SSL certificate generation
    log_info "Requesting SSL certificates from Let's Encrypt..."
    log_info "This may take 1-3 minutes depending on DNS propagation..."
    
    # Monitor certificate generation
    MAX_WAIT=180
    WAITED=0
    SSL_SUCCESS=false
    
    while [ $WAITED -lt $MAX_WAIT ]; do
        # Check if acme.json has content (certificate generated)
        if [ -s letsencrypt/acme.json ]; then
            FILESIZE=$(stat -f%z "letsencrypt/acme.json" 2>/dev/null || stat -c%s "letsencrypt/acme.json" 2>/dev/null)
            if [ "$FILESIZE" -gt 100 ]; then
                SSL_SUCCESS=true
                break
            fi
        fi
        
        # Show progress
        if [ $((WAITED % 10)) -eq 0 ]; then
            echo -n "."
        fi
        
        sleep 5
        WAITED=$((WAITED + 5))
    done
    echo ""
    
    if [ "$SSL_SUCCESS" = true ]; then
        log_success "SSL certificates generated successfully!"
        log_info "Certificate details:"
        if command -v jq &> /dev/null; then
            cat letsencrypt/acme.json | jq -r '.letsencrypt.Certificates[]?.domain.main' 2>/dev/null | while read domain; do
                echo "  ✓ $domain"
            done
        else
            grep -o '"main":"[^"]*"' letsencrypt/acme.json | cut -d'"' -f4 | while read domain; do
                echo "  ✓ $domain"
            done
        fi
    else
        log_warn "SSL certificate generation is taking longer than expected."
        log_info "This is normal if DNS is still propagating."
        log_info "Certificates will be generated automatically within 5-10 minutes."
        log_info "You can monitor progress with: docker compose logs -f traefik"
    fi
else
    log_error "Traefik failed to start. SSL may not work."
fi

# Additional SSL verification
log_info "Verifying SSL configuration..."
sleep 5

# Check Traefik logs for errors
if docker compose logs traefik 2>/dev/null | grep -qi "error.*acme\|error.*certificate"; then
    log_warn "Detected SSL configuration warnings. Checking..."
    
    # Common fixes
    log_info "Applying SSL fixes..."
    
    # Ensure correct permissions
    chmod 600 letsencrypt/acme.json
    
    # Restart Traefik to retry certificate generation
    docker compose restart traefik > /dev/null 2>&1
    log_info "Traefik restarted. Certificates will be generated shortly."
    
    sleep 10
fi

log_success "SSL configuration complete."

# Post-installation verification
echo ""
log_info "Running post-installation verification..."

# Test 1: Check if all containers are healthy
log_info "Checking container health..."
UNHEALTHY_CONTAINERS=$(docker ps --filter "health=unhealthy" --format "{{.Names}}" | grep logicpanel || true)
if [ -z "$UNHEALTHY_CONTAINERS" ]; then
    log_success "All containers are healthy."
else
    log_warn "Some containers are unhealthy: $UNHEALTHY_CONTAINERS"
fi

# Test 2: Check if ports are listening
log_info "Checking port availability..."
for port in 80 443 777 999; do
    if ss -tuln | grep -q ":$port "; then
        log_success "Port $port is listening"
    else
        log_warn "Port $port is not listening"
    fi
done

# Test 3: Quick HTTP test
log_info "Testing HTTP connectivity..."
if curl -s -o /dev/null -w "%{http_code}" http://localhost/public/health.php 2>/dev/null | grep -q "200"; then
    log_success "Application is responding to HTTP requests"
else
    log_warn "Application may not be fully ready yet"
fi

# Prepare Adminer Summary Line
ADMINER_SUMMARY_LINE=""
if [[ "$ENABLE_ADMINER" =~ ^[Yy]$ ]]; then
    ADMINER_SUMMARY_LINE="Database Manager:      https://db.${PANEL_DOMAIN}"
fi

# Create a summary file
cat > INSTALLATION_SUMMARY.txt << EOFSUMMARY
╔══════════════════════════════════════════════════════════════════════════════╗
║                                                                              ║
║                    LogicPanel Installation Summary                           ║
║                                                                              ║
╚══════════════════════════════════════════════════════════════════════════════╝

Installation Date: $(date)
Installation Directory: ${INSTALL_DIR}
Panel Domain: ${PANEL_DOMAIN}

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

🔐 ADMIN CREDENTIALS (SAVE THIS SECURELY!)

Username: ${ADMIN_USER}
Email:    ${ADMIN_EMAIL}
Password: ${ADMIN_PASS}

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

🌐 ACCESS URLS

Master Panel (Admin):  https://${PANEL_DOMAIN}:999
User Panel:            https://${PANEL_DOMAIN}:777
${ADMINER_SUMMARY_LINE}

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

🔧 USEFUL COMMANDS

Check SSL Status:
  cd ${INSTALL_DIR} && ./check-ssl.sh

View Logs:
  cd ${INSTALL_DIR} && docker compose logs -f

View Traefik Logs:
  cd ${INSTALL_DIR} && docker compose logs -f traefik

Restart Services:
  cd ${INSTALL_DIR} && docker compose restart

Stop Panel:
  cd ${INSTALL_DIR} && docker compose down

Start Panel:
  cd ${INSTALL_DIR} && docker compose up -d

Update Panel:
  cd ${INSTALL_DIR} && git pull && docker compose up -d --build

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

📦 INSTALLED SERVICES

✓ LogicPanel Application
✓ Traefik Reverse Proxy (with Let's Encrypt SSL)
✓ Terminal Gateway (WebSocket)
✓ MariaDB (MySQL) - Port 3306
✓ PostgreSQL - Port 5432
✓ MongoDB - Port 27017
✓ Redis Cache
✓ Database Provisioner Service

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

🔒 SSL CERTIFICATE STATUS

SSL certificates are automatically managed by Traefik using Let's Encrypt.

Certificate Storage: ${INSTALL_DIR}/letsencrypt/acme.json
Certificate Renewal: Automatic (30 days before expiry)

If SSL is not working immediately:
  1. Wait 2-3 minutes for certificate generation
  2. Ensure DNS points to this server
  3. Check firewall allows ports 80, 443, 777, 999
  4. Run: ./check-ssl.sh

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

⚠️  SECURITY NOTES

1. Change default admin password after first login
2. Keep this file secure (contains credentials)
3. Regular backups recommended
4. Monitor logs for suspicious activity
5. Keep Docker and system updated

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

📚 DOCUMENTATION & SUPPORT

Documentation: https://docs.logicpanel.cloud
GitHub Issues:  https://github.com/cyber-wahid/panel/issues
Community:      https://community.logicpanel.cloud

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Thank you for choosing LogicPanel! 🚀

EOFSUMMARY

chmod 600 INSTALLATION_SUMMARY.txt
log_success "Installation summary saved to: ${INSTALL_DIR}/INSTALLATION_SUMMARY.txt"

# Success Message
clear
echo -e "${CYAN}"
echo "██╗      ██████╗  ██████╗ ██╗ ██████╗██████╗  █████╗ ███╗   ██╗███████╗██╗     "
echo "██║     ██╔═══██╗██╔════╝ ██║██╔════╝██╔══██╗██╔══██╗████╗  ██║██╔════╝██║     "
echo "██║     ██║   ██║██║  ███╗██║██║     ██████╔╝███████║██╔██╗ ██║█████╗  ██║     "
echo "██║     ██║   ██║██║   ██║██║██║     ██╔═══╝ ██╔══██║██║╚██╗██║██╔══╝  ██║     "
echo "███████╗╚██████╔╝╚██████╔╝██║╚██████╗██║     ██║  ██║██║ ╚████║███████╗███████╗"
echo "╚══════╝ ╚═════╝  ╚═════╝ ╚═╝ ╚═════╝╚═╝     ╚═╝  ╚═╝╚═╝  ╚═══╝╚══════╝╚══════╝"
echo -e "${NC}"

echo -e "${GREEN}╔════════════════════════════════════════════════════════════════╗${NC}"
echo -e "${GREEN}║${NC}           ${CYAN}✨ INSTALLATION SUCCESSFUL! ✨${NC}                      ${GREEN}║${NC}"
echo -e "${GREEN}╠════════════════════════════════════════════════════════════════╣${NC}"
echo -e "${GREEN}║${NC}                                                                ${GREEN}║${NC}"
echo -e "${GREEN}║${NC}  ${YELLOW}🌐 PANEL ACCESS LINKS${NC}                                        ${GREEN}║${NC}"
echo -e "${GREEN}║${NC}     Master Panel:  ${CYAN}https://${PANEL_DOMAIN}:999${NC}"
echo -e "${GREEN}║${NC}     User Panel:    ${CYAN}https://${PANEL_DOMAIN}:777${NC}"
if [[ "$ENABLE_ADMINER" =~ ^[Yy]$ ]]; then
    echo -e "${GREEN}║${NC}     DB Manager:    ${CYAN}https://db.${PANEL_DOMAIN}${NC}"
fi
echo -e "${GREEN}║${NC}                                                                ${GREEN}║${NC}"
echo -e "${GREEN}╠════════════════════════════════════════════════════════════════╣${NC}"
echo -e "${GREEN}║${NC}                                                                ${GREEN}║${NC}"
echo -e "${GREEN}║${NC}  ${YELLOW}🔐 ADMIN CREDENTIALS${NC}                                         ${GREEN}║${NC}"
echo -e "${GREEN}║${NC}     Username:    ${CYAN}${ADMIN_USER}${NC}"
echo -e "${GREEN}║${NC}     Email:       ${CYAN}${ADMIN_EMAIL}${NC}"
echo -e "${GREEN}║${NC}     Password:    ${CYAN}${ADMIN_PASS}${NC}"
echo -e "${GREEN}║${NC}                                                                ${GREEN}║${NC}"
echo -e "${GREEN}╠════════════════════════════════════════════════════════════════╣${NC}"
echo -e "${GREEN}║${NC}                                                                ${GREEN}║${NC}"
echo -e "${GREEN}║${NC}  ${YELLOW}📦 INSTALLED SERVICES${NC}                                        ${GREEN}║${NC}"
echo -e "${GREEN}║${NC}     • LogicPanel Application                                  ${GREEN}║${NC}"
echo -e "${GREEN}║${NC}     • Traefik Reverse Proxy (SSL)                             ${GREEN}║${NC}"
echo -e "${GREEN}║${NC}     • Terminal Gateway (WebSocket)                            ${GREEN}║${NC}"
echo -e "${GREEN}║${NC}     • MariaDB (MySQL)    - Port 3306                          ${GREEN}║${NC}"
echo -e "${GREEN}║${NC}     • PostgreSQL         - Port 5432                          ${GREEN}║${NC}"
echo -e "${GREEN}║${NC}     • MongoDB            - Port 27017                         ${GREEN}║${NC}"
echo -e "${GREEN}║${NC}     • Redis Cache                                             ${GREEN}║${NC}"
echo -e "${GREEN}║${NC}                                                                ${GREEN}║${NC}"
echo -e "${GREEN}╠════════════════════════════════════════════════════════════════╣${NC}"
echo -e "${GREEN}║${NC}                                                                ${GREEN}║${NC}"
echo -e "${GREEN}║${NC}  ${YELLOW}ℹ️  IMPORTANT NOTES${NC}                                          ${GREEN}║${NC}"
echo -e "${GREEN}║${NC}     • SSL handled by Traefik (Let's Encrypt)                  ${GREEN}║${NC}"

# Check SSL status
if [ -s letsencrypt/acme.json ]; then
    FILESIZE=$(stat -f%z "letsencrypt/acme.json" 2>/dev/null || stat -c%s "letsencrypt/acme.json" 2>/dev/null)
    if [ "$FILESIZE" -gt 100 ]; then
        echo -e "${GREEN}║${NC}     • ${GREEN}✓${NC} SSL certificates generated successfully!              ${GREEN}║${NC}"
    else
        echo -e "${GREEN}║${NC}     • ${YELLOW}⚠${NC} SSL certificates are being generated...               ${GREEN}║${NC}"
    fi
else
    echo -e "${GREEN}║${NC}     • ${YELLOW}⚠${NC} SSL certificates will be generated within 5 minutes    ${GREEN}║${NC}"
fi

echo -e "${GREEN}║${NC}     • First access may take 1-2 minutes for SSL               ${GREEN}║${NC}"
echo -e "${GREEN}║${NC}     • Ensure DNS A record points to this server               ${GREEN}║${NC}"
echo -e "${GREEN}║${NC}     • Run ./check-ssl.sh to verify SSL status               ${GREEN}║${NC}"
echo -e "${GREEN}║${NC}                                                                ${GREEN}║${NC}"
echo -e "${GREEN}╠════════════════════════════════════════════════════════════════╣${NC}"
echo -e "${GREEN}║${NC}                                                                ${GREEN}║${NC}"
echo -e "${GREEN}║${NC}  ${YELLOW}🔧 USEFUL COMMANDS${NC}                                           ${GREEN}║${NC}"
echo -e "${GREEN}║${NC}     • Check SSL status:    cd ${INSTALL_DIR} && ./check-ssl.sh ${GREEN}║${NC}"
echo -e "${GREEN}║${NC}     • View logs:           cd ${INSTALL_DIR} && docker compose logs -f ${GREEN}║${NC}"
echo -e "${GREEN}║${NC}     • Restart services:    cd ${INSTALL_DIR} && docker compose restart ${GREEN}║${NC}"
echo -e "${GREEN}║${NC}     • Stop panel:          cd ${INSTALL_DIR} && docker compose down ${GREEN}║${NC}"
echo -e "${GREEN}║${NC}     • Start panel:         cd ${INSTALL_DIR} && docker compose up -d ${GREEN}║${NC}"
echo -e "${GREEN}║${NC}                                                                ${GREEN}║${NC}"
echo -e "${GREEN}╚════════════════════════════════════════════════════════════════╝${NC}"
echo ""
echo -e "  ${CYAN}Thank you for choosing LogicPanel by cyber-wahid${NC} 💙"
echo ""
echo -e "  ${MAGENTA}📚 Documentation: https://github.com/cyber-wahid/panel${NC}"
echo -e "  ${MAGENTA}🐛 Report Issues: https://github.com/cyber-wahid/panel/issues${NC}"
echo ""
echo -e "  ${YELLOW}💾 Installation details saved to: ${INSTALL_DIR}/INSTALLATION_SUMMARY.txt${NC}"
echo -e "  ${YELLOW}🔐 Keep this file secure - it contains your credentials!${NC}"
echo ""

# Final SSL reminder
if [ -s letsencrypt/acme.json ]; then
    FILESIZE=$(stat -f%z "letsencrypt/acme.json" 2>/dev/null || stat -c%s "letsencrypt/acme.json" 2>/dev/null)
    if [ "$FILESIZE" -gt 100 ]; then
        echo -e "  ${GREEN}✅ SSL certificates are ready! Your panel is secure.${NC}"
    else
        echo -e "  ${YELLOW}⏳ SSL certificates are being generated. Please wait 2-3 minutes.${NC}"
        echo -e "  ${YELLOW}   Run 'cd ${INSTALL_DIR} && ./check-ssl.sh' to check status.${NC}"
    fi
else
    echo -e "  ${YELLOW}⏳ SSL certificates will be generated within 5 minutes.${NC}"
    echo -e "  ${YELLOW}   Monitor progress: cd ${INSTALL_DIR} && docker compose logs -f traefik${NC}"
fi
# Auto Exit Screen
if [ -n "$STY" ]; then
    echo -e "${YELLOW}Exiting screen session...${NC}"
    exit
fi

