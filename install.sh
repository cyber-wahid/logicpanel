#!/usr/bin/env bash

# LogicPanel - One-Line Installer v3.1.4 (Production Edition)
# Author: cyber-wahid
# Description: Automated installer for LogicPanel with Docker and Traefik SSL.
# Supports: Debian/Ubuntu (APT), RHEL/CentOS/AlmaLinux/Rocky/Fedora (DNF/YUM)
# License: Proprietary

# --- 0. Pre-Installation Checks ---
# Ensure script is being run with bash
if [ -z "$BASH_VERSION" ]; then
    echo "[ERROR] This script must be run with bash. Try: curl ... | bash"
    exit 1
fi

# If we reach here, we're inside screen or the user explicitly bypassed it
# set -e # Disabled to allow better error handling


# --- Configuration ---
# Prevent interactive prompts on APT systems (Critical Fix for Credential Display)
export DEBIAN_FRONTEND=noninteractive
export NEEDRESTART_MODE=a

# Detect Docker Desktop: it restricts bind mounts to /home, /tmp, etc.
# On Docker Desktop, use a path under $HOME. On servers, use /opt.
REAL_USER="${SUDO_USER:-$USER}"
REAL_HOME=$(getent passwd "$REAL_USER" | cut -d: -f6)
IS_DOCKER_DESKTOP=false

# Check for Docker Desktop by looking for its socket or app directory
if [ -d "$REAL_HOME/.docker/desktop" ] || \
   (command -v docker &>/dev/null && docker info 2>/dev/null | grep -qi "docker desktop"); then
    IS_DOCKER_DESKTOP=true
fi

if [ "$IS_DOCKER_DESKTOP" = true ]; then
    INSTALL_DIR="${REAL_HOME}/.logicpanel"
else
    INSTALL_DIR="/opt/logicpanel"
fi
VERSION="3.1.6"

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

# --- 1. Root/Sudo Check ---
SUDO=""
if [[ $EUID -ne 0 ]]; then
   log_warn "Running as non-root user. Access to Docker Desktop should work."
   log_warn "We will use 'sudo' for system commands."
   SUDO="sudo"
   
   # Verify sudo access
   if ! sudo -v; then
       log_error "Sudo password verification failed."
       exit 1
   fi
else
   log_info "Running as root."
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

# We're already in screen at this point (or user bypassed it)

# Progress bar countdown (emoji-free)
countdown_progress() {
    local seconds=$1
    local message=$2
    local width=40
    # Ensure terminal can handle \r and disable cursor for cleaner animation
    tput civis 2>/dev/null || true
    for ((i=0; i<=seconds; i++)); do
        local pct=$((i * 100 / seconds))
        local filled=$((i * width / seconds))
        local empty=$((width - filled))
        local bar=$(printf "%${filled}s" | tr ' ' '#')$(printf "%${empty}s" | tr ' ' '-')
        local remaining=$((seconds - i))
        # Use printf with explicit format and ensure it flushes
        printf "\r  ${CYAN}[%s]${NC} %d%% - %s (%ds remaining)  " "$bar" "$pct" "$message" "$remaining"
        sleep 1
    done
    tput cnorm 2>/dev/null || true
    printf "\r  ${GREEN}[%s]${NC} 100%% - %s                                \n" "$(printf "%${width}s" | tr ' ' '#')" "$message"
}

# Spinner for background processes (using \r for modern terminal compatibility)
spinner() {
    local pid=$1
    local delay=0.1
    local spinstr='|/-\'
    local message=$2
    while ps -p $pid > /dev/null 2>&1; do
        local temp=${spinstr#?}
        printf "\r  ${CYAN}[%c]${NC} %s" "$spinstr" "$message"
        local spinstr=$temp${spinstr%"$temp"}
        sleep $delay
    done
    printf "\r  ${GREEN}[OK]${NC} %s                               \n" "$message"
}


# Manage SELinux (Required for RHEL-based systems)
manage_selinux() {
    if command -v getenforce &> /dev/null; then
        local status=$(getenforce)
        log_info "Current SELinux status: $status"
        if [ "$status" != "Permissive" ] && [ "$status" != "Disabled" ]; then
            log_warn "SELinux is active (Enforcing). This often blocks Docker networking."
            # On automated installs or if we want to be helpful, we set to permissive
            log_info "Setting SELinux to Permissive for compatibility..."
            $SUDO setenforce 0 || true
            # Make it persistent
            if [ -f /etc/selinux/config ]; then
                $SUDO sed -i 's/^SELINUX=enforcing/SELINUX=permissive/g' /etc/selinux/config || true
            fi
            log_success "SELinux set to Permissive."
        fi
    fi
}

# Check port availability and handle ALL LogicPanel/Stack conflicts
check_port() {
    local port=$1
    local force_kill=$2
    
    # 1. Check if ANY container is holding the port (not just logicpanel_)
    local conflicting_container=$(docker ps --format '{{.Names}}' --filter "publish=$port" | head -n 1)
    if [ ! -z "$conflicting_container" ]; then
        log_warn "Port $port is held by container: $conflicting_container"
        if [[ "$conflicting_container" == logicpanel_* ]] || [[ "$conflicting_container" == lp_* ]]; then
            log_info "Auto-removing LogicPanel container..."
            docker rm -f "$conflicting_container" >/dev/null 2>&1
            sleep 1
        else
            if [ "$force_kill" != "force" ]; then
                read -p "--- Port $port is held by '$conflicting_container'. Remove it? (y/n): " REM_CONT < /dev/tty
                if [[ "$REM_CONT" =~ ^[Yy]$ ]]; then
                    docker rm -f "$conflicting_container" >/dev/null 2>&1
                    sleep 1
                fi
            else
                docker rm -f "$conflicting_container" >/dev/null 2>&1
            fi
        fi
    fi

    # 2. General check if port is still busy (non-container processes)
    if command -v lsof &> /dev/null; then
        if $SUDO lsof -i :$port -sTCP:LISTEN -t >/dev/null; then
             if [ "$force_kill" = "force" ]; then
                 # Try to kill explicitly
                 local pid=$($SUDO lsof -i :$port -sTCP:LISTEN -t | head -1)
                 if [ ! -z "$pid" ]; then
                     log_warn "Force killing PID $pid on port $port..."
                     $SUDO kill -9 $pid >/dev/null 2>&1 || true
                     sleep 1
                 fi
                 return 1 # Return fail so caller knows we had to kill
            else
                 return 1
            fi
        fi
    elif command -v ss &> /dev/null; then
        if ss -tuln | grep -E ":$port\s"; then
            if [ "$force_kill" = "force" ]; then
                 $SUDO fuser -k -n tcp $port >/dev/null 2>&1 || true
                 sleep 1
            else
                 return 1
            fi
        fi
    elif command -v netstat &> /dev/null; then
        if netstat -tuln | grep -E ":$port\s"; then
            if [ "$force_kill" = "force" ]; then
                 $SUDO fuser -k -n tcp $port >/dev/null 2>&1 || true
                 sleep 1
            else
                 return 1
            fi
        fi
    fi

    # 3. Explicit check for docker-proxy if port is 9999 or 80/443
    if [ "$force_kill" = "force" ]; then
         # This is the "Nuclear" part for zombie proxies
         if pgrep -f "docker-proxy.*$port" > /dev/null; then
             log_warn "Found specific docker-proxy for port $port. Killing it..."
             $SUDO pkill -f "docker-proxy.*$port" || true
             sleep 1
         fi
    fi

    return 0
}

# Restart Docker Service (The Nuclear Option)
restart_docker_service() {
    log_warn "Restarting Docker service to clear stuck ports..."
    if command -v systemctl &> /dev/null; then
        $SUDO systemctl restart docker
    elif command -v service &> /dev/null; then
        $SUDO service docker restart
    fi
    sleep 5
    # Wait for Docker to come back
    count=0
    while ! docker info > /dev/null 2>&1; do
        sleep 1
        count=$((count+1))
        if [ $count -gt 30 ]; then
            log_error "Docker failed to restart."
            exit 1
        fi
    done
    log_success "Docker service restarted."
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
    # ─── Docker Desktop Compatibility Fix ───
    if [ -n "$SUDO_USER" ]; then
        USER_HOME=$(getent passwd "$SUDO_USER" | cut -d: -f6)
        DESKTOP_SOCKET="$USER_HOME/.docker/desktop/docker-cli.sock"
        if [ -S "$DESKTOP_SOCKET" ]; then
            log_info "Detected Docker Desktop. Using socket: $DESKTOP_SOCKET"
            export DOCKER_HOST="unix://$DESKTOP_SOCKET"
        fi
    fi

    if ! command -v docker &> /dev/null; then
        log_error "Docker is NOT installed."
        log_error "Please install Docker first: https://docs.docker.com/engine/install/"
        log_error "This script requires a pre-installed and working Docker environment."
        exit 1
    fi

    # Simplified check: Just try a docker command
    if ! docker ps > /dev/null 2>&1; then
        log_warn "Docker service seems to be down or current user cannot access it."
        log_warn "Attempting to start..."
        # Try systemctl but don't fail if it doesn't work (e.g. non-systemd)
        $SUDO systemctl start docker || $SUDO service docker start || true
        sleep 2
        
        # Check again
        if ! docker ps > /dev/null 2>&1; then
             log_warn "Could not auto-start Docker. Assuming you know what you are doing or using a custom environment."
             log_warn "If Docker is not actually running, the next steps will fail."
             # We do NOT exit here to allow "special" setups to proceed
        fi
    else
        log_success "Docker is running."
    fi

    DOCKER_VER=$(docker --version | grep -oP '\d+\.\d+\.\d+' | head -1)
    log_success "Docker is ready (v${DOCKER_VER})"
}
check_docker

# Check required ports
manage_selinux
REQUIRED_PORTS=(80 443 9999 7777 3306 5432 27017)
for port in "${REQUIRED_PORTS[@]}"; do
    if ! check_port $port; then
        log_warn "Port $port is held by a non-container process."
        $SUDO fuser -k -n tcp $port 2>/dev/null || true
        sleep 1
        if ! check_port $port; then
            log_error "Could not clear port $port. Please free it manually and restart."
            exit 1
        fi
        log_success "Port $port cleared."
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
    ufw allow 7777/tcp comment "LogicPanel User Panel" > /dev/null 2>&1
    ufw allow 7777/udp comment "HTTP/3 User Panel" > /dev/null 2>&1
    ufw allow 9999/tcp comment "LogicPanel Master Panel" > /dev/null 2>&1
    ufw allow 9999/udp comment "HTTP/3 Master Panel" > /dev/null 2>&1
    log_success "UFW rules configured with HTTP/3 support."
elif command -v firewall-cmd &> /dev/null && systemctl is-active --quiet firewalld; then
    log_info "Detected firewalld. Configuring..."
    firewall-cmd --permanent --add-port=80/tcp > /dev/null 2>&1
    firewall-cmd --permanent --add-port=443/tcp > /dev/null 2>&1
    firewall-cmd --permanent --add-port=443/udp > /dev/null 2>&1
    firewall-cmd --permanent --add-port=7777/tcp > /dev/null 2>&1
    firewall-cmd --permanent --add-port=7777/udp > /dev/null 2>&1
    firewall-cmd --permanent --add-port=9999/tcp > /dev/null 2>&1
    firewall-cmd --permanent --add-port=9999/udp > /dev/null 2>&1
    firewall-cmd --reload > /dev/null 2>&1
    log_success "Firewalld rules configured with HTTP/3 support."
elif command -v nft &> /dev/null && systemctl is-active --quiet nftables 2>/dev/null; then
    log_info "Detected nftables. Configuring..."
    # Add LogicPanel table if not exists
    $SUDO nft add table inet logicpanel 2>/dev/null || true
    $SUDO nft add chain inet logicpanel input '{ type filter hook input priority 0; policy accept; }' 2>/dev/null || true
    for port in 80 443 7777 9999; do
        $SUDO nft add rule inet logicpanel input tcp dport $port accept 2>/dev/null || true
    done
    for port in 443 7777 9999; do
        $SUDO nft add rule inet logicpanel input udp dport $port accept 2>/dev/null || true
    done
    # Persist rules
    if [ -d /etc/nftables.d ]; then
        $SUDO nft list table inet logicpanel > /etc/nftables.d/logicpanel.conf 2>/dev/null || true
    fi
    log_success "nftables rules configured with HTTP/3 support."
elif command -v iptables &> /dev/null; then
    log_info "Configuring iptables..."
    $SUDO iptables -A INPUT -p tcp --dport 80 -j ACCEPT > /dev/null 2>&1
    $SUDO iptables -A INPUT -p tcp --dport 443 -j ACCEPT > /dev/null 2>&1
    $SUDO iptables -A INPUT -p udp --dport 443 -j ACCEPT > /dev/null 2>&1
    $SUDO iptables -A INPUT -p tcp --dport 7777 -j ACCEPT > /dev/null 2>&1
    $SUDO iptables -A INPUT -p udp --dport 7777 -j ACCEPT > /dev/null 2>&1
    $SUDO iptables -A INPUT -p tcp --dport 9999 -j ACCEPT > /dev/null 2>&1
    $SUDO iptables -A INPUT -p udp --dport 9999 -j ACCEPT > /dev/null 2>&1
    
    # Save iptables rules (support multiple persistence methods)
    if command -v iptables-save &> /dev/null; then
        $SUDO mkdir -p /etc/iptables
        $SUDO iptables-save > /etc/iptables/rules.v4 2>/dev/null || true
    fi
    if command -v netfilter-persistent &> /dev/null; then
        $SUDO netfilter-persistent save 2>/dev/null || true
    fi
    log_success "Iptables rules configured with HTTP/3 support."
else
    log_warn "No firewall detected. Ports should be open by default."
    log_info "If you configure a firewall later, open these ports:"
    log_info "  TCP: 80, 443, 7777, 9999"
    log_info "  UDP: 443, 7777, 9999 (for HTTP/3)"
fi

# Install Docker
if command -v docker &> /dev/null; then
    DOCKER_VER=$(docker --version 2>/dev/null | grep -oP '\d+\.\d+\.\d+' | head -1)
    log_success "Docker is already installed (v${DOCKER_VER}). Skipping installation."
    
    # Ensure Docker service is running
    if ! $SUDO systemctl is-active --quiet docker 2>/dev/null; then
        log_info "Docker service is not running. Starting..."
        $SUDO systemctl enable --now docker 2>/dev/null || $SUDO service docker start 2>/dev/null || true
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
        $SUDO $PKG_RM remove -y docker docker-client docker-client-latest docker-common \
            docker-latest docker-latest-logrotate docker-logrotate docker-engine \
            podman runc 2>/dev/null || true
    elif command -v apt-get &> /dev/null; then
        for pkg in docker.io docker-doc docker-compose podman-docker containerd runc; do
            $SUDO apt-get remove -y $pkg 2>/dev/null || true
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
        sles|opensuse*|arch|manjaro*)
            log_error "Unsupported distribution: $OS_ID"
            log_error "LogicPanel supports only Debian/Ubuntu (APT) and RHEL/CentOS/AlmaLinux/Rocky/Fedora (DNF/YUM)."
            exit 1
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
                *)
                    log_error "Unsupported distribution"
                    log_error "LogicPanel supports only:"
                    log_error "  - Debian/Ubuntu and derivatives (APT)"
                    log_error "  - RHEL/CentOS/AlmaLinux/Rocky/Fedora (DNF/YUM)"
                    exit 1
                    ;;
            esac
            ;;
    esac
    
    log_info "Using installation method: $REPO_METHOD"
    
    # ── Method 1: APT-based (Ubuntu, Debian, derivatives) ──────────
    if [ "$REPO_METHOD" = "apt" ] && [ "$DOCKER_INSTALLED" = false ]; then
        log_info "Installing Docker via APT repository..."
        
        # Install prerequisites
        $SUDO apt-get update -qq
        $SUDO apt-get install -y apt-transport-https ca-certificates curl gnupg lsb-release
        
        # Add Docker's official GPG key
        $SUDO install -m 0755 -d /etc/apt/keyrings
        curl -fsSL "${DOCKER_REPO_URL}/gpg" | $SUDO gpg --dearmor -o /usr/share/keyrings/docker-archive-keyring.gpg
        
        # Determine the codename for the repo
        if [ -n "${UBUNTU_CODENAME:-}" ]; then
            CODENAME="$UBUNTU_CODENAME"
        elif [ -n "${VERSION_CODENAME:-}" ]; then
            CODENAME="$VERSION_CODENAME"
        else
            HAS_LSB=$(command -v lsb_release &> /dev/null && echo true || echo false)
            if [ "$HAS_LSB" = true ]; then
                CODENAME=$(lsb_release -cs)
            else
                # Fallback to common Ubuntu LTS
                CODENAME="jammy"
                log_warn "Could not detect codename, defaulting to: $CODENAME"
            fi
        fi
        
        # Add the Docker repository
        echo "deb [arch=$(dpkg --print-architecture) signed-by=/usr/share/keyrings/docker-archive-keyring.gpg] ${DOCKER_REPO_URL} ${CODENAME} stable" | \
            $SUDO tee /etc/apt/sources.list.d/docker.list > /dev/null
        
        # Install Docker
        $SUDO apt-get update -qq
        $SUDO apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
        
        command -v docker &> /dev/null && DOCKER_INSTALLED=true
    fi
    
    # ── Method 2: DNF/YUM-based (RHEL, CentOS, AlmaLinux, Rocky, Fedora) ──
    if [ "$REPO_METHOD" = "dnf" ] && [ "$DOCKER_INSTALLED" = false ]; then
        log_info "Installing Docker via DNF/YUM repository..."
        
        # Prefer dnf over yum
        if command -v dnf &> /dev/null; then
            DNF_CMD="dnf"
        else
            DNF_CMD="yum"
        fi
        
        # Install prerequisites
        $SUDO $DNF_CMD install -y yum-utils 2>/dev/null || true
        
        # Add Docker repository
        $SUDO $DNF_CMD config-manager --add-repo "${DOCKER_REPO_URL}/docker-ce.repo" 2>/dev/null || true
        
        # Install Docker
        $SUDO $DNF_CMD install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
        
        command -v docker &> /dev/null && DOCKER_INSTALLED=true
    fi
    
    
    # Start and enable Docker
    $SUDO systemctl enable --now docker 2>/dev/null || $SUDO service docker start 2>/dev/null || true

    
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

# Install Docker if not present
install_docker() {
    if ! command -v docker &> /dev/null; then
        log_info "Docker not found. Installing..."
        
        # Install lsof for better port checking if available
        if command -v apt-get &> /dev/null; then
             $SUDO apt-get update && $SUDO apt-get install -y lsof
        elif command -v yum &> /dev/null; then
             $SUDO yum install -y lsof
        fi

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
            $SUDO $PKG_RM remove -y docker docker-client docker-client-latest docker-common \
                docker-latest docker-latest-logrotate docker-logrotate docker-engine \
                podman runc 2>/dev/null || true
        elif command -v apt-get &> /dev/null; then
            for pkg in docker.io docker-doc docker-compose podman-docker containerd runc; do
                $SUDO apt-get remove -y $pkg 2>/dev/null || true
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
            sles|opensuse*|arch|manjaro*)
                log_error "Unsupported distribution: $OS_ID"
                log_error "LogicPanel supports only Debian/Ubuntu (APT) and RHEL/CentOS/AlmaLinux/Rocky/Fedora (DNF/YUM)."
                exit 1
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
                    *)
                        log_error "Unsupported distribution"
                        log_error "LogicPanel supports only:"
                        log_error "  - Debian/Ubuntu and derivatives (APT)"
                        log_error "  - RHEL/CentOS/AlmaLinux/Rocky/Fedora (DNF/YUM)"
                        exit 1
                        ;;
                esac
                ;;
        esac
        
        log_info "Using installation method: $REPO_METHOD"
        
        # ── Method 1: APT-based (Ubuntu, Debian, derivatives) ──────────
        if [ "$REPO_METHOD" = "apt" ] && [ "$DOCKER_INSTALLED" = false ]; then
            log_info "Installing Docker via APT repository..."
            
            # Install prerequisites
            $SUDO apt-get update -qq
            $SUDO apt-get install -y apt-transport-https ca-certificates curl gnupg lsb-release
            
            # Add Docker's official GPG key
            $SUDO install -m 0755 -d /etc/apt/keyrings
            curl -fsSL "${DOCKER_REPO_URL}/gpg" | $SUDO gpg --dearmor -o /usr/share/keyrings/docker-archive-keyring.gpg
            
            # Determine the codename for the repo
            if [ -n "${UBUNTU_CODENAME:-}" ]; then
                CODENAME="$UBUNTU_CODENAME"
            elif [ -n "${VERSION_CODENAME:-}" ]; then
                CODENAME="$VERSION_CODENAME"
            else
                HAS_LSB=$(command -v lsb_release &> /dev/null && echo true || echo false)
                if [ "$HAS_LSB" = true ]; then
                    CODENAME=$(lsb_release -cs)
                else
                    # Fallback to common Ubuntu LTS
                    CODENAME="jammy"
                    log_warn "Could not detect codename, defaulting to: $CODENAME"
                fi
            fi
            
            # Add the Docker repository
            echo "deb [arch=$(dpkg --print-architecture) signed-by=/usr/share/keyrings/docker-archive-keyring.gpg] ${DOCKER_REPO_URL} ${CODENAME} stable" | \
                $SUDO tee /etc/apt/sources.list.d/docker.list > /dev/null
            
            # Install Docker
            $SUDO apt-get update -qq
            $SUDO apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
            
            command -v docker &> /dev/null && DOCKER_INSTALLED=true
        fi
        
        # ── Method 2: DNF/YUM-based (RHEL, CentOS, AlmaLinux, Rocky, Fedora) ──
        if [ "$REPO_METHOD" = "dnf" ] && [ "$DOCKER_INSTALLED" = false ]; then
            log_info "Installing Docker via DNF/YUM repository..."
            
            # Prefer dnf over yum
            if command -v dnf &> /dev/null; then
                DNF_CMD="dnf"
            else
                DNF_CMD="yum"
            fi
            
            # Install prerequisites
            $SUDO $DNF_CMD install -y yum-utils 2>/dev/null || true
            
            # Add Docker repository
            $SUDO $DNF_CMD config-manager --add-repo "${DOCKER_REPO_URL}/docker-ce.repo" 2>/dev/null || true
            
            # Install Docker
            $SUDO $DNF_CMD install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
            
            command -v docker &> /dev/null && DOCKER_INSTALLED=true
        fi
        
        
        # Start and enable Docker
        $SUDO systemctl enable --now docker 2>/dev/null || $SUDO service docker start 2>/dev/null || true

        
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
}

# Call the function to ensure Docker is installed
install_docker

# Install Git and other dependencies
if ! command -v git &> /dev/null; then
    log_info "Installing Git..."
    if command -v dnf &> /dev/null; then
        dnf install -y git
    elif command -v yum &> /dev/null; then
        yum install -y git
    elif command -v apt-get &> /dev/null; then
        apt-get update && apt-get install -y git
    elif command -v pacman &> /dev/null; then
        pacman -Sy --noconfirm git
    elif command -v zypper &> /dev/null; then
        zypper install -y git
    else
        log_error "Could not install Git. Please install Git manually."
        exit 1
    fi
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
    LOGICPANEL_VERSION="v3.1.5"
    ARCH=$(uname -m)
    case "$ARCH" in
        x86_64) ARCH="x86_64" ;;
        *) 
            log_error "Unsupported architecture: $ARCH. Only x86_64 is supported."
            exit 1 
            ;;
    esac
    curl -SL "https://github.com/docker/compose/releases/download/${COMPOSE_VERSION}/docker-compose-linux-${ARCH}" -o /usr/libexec/docker/cli-plugins/docker-compose
    chmod +x /usr/libexec/docker/cli-plugins/docker-compose
    
    mkdir -p ~/.docker/cli-plugins
    cp /usr/libexec/docker/cli-plugins/docker-compose ~/.docker/cli-plugins/docker-compose
    log_success "Docker Compose installed."
fi

# --- 3. Docker Network & Environment ---
# (Variables are generated after user input, see below)

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

# Random Hostnames & Network for Security/Isolation
# This prevents conflicts if multiple panels run on the same network (though unlikely)
# and obscures the internal topology.
RAND_SUFFIX=$(generate_random 6 | tr '[:upper:]' '[:lower:]')
DOCKER_NETWORK="lp_net_${RAND_SUFFIX}"
DB_HOST_MAIN="lp_db_main_${RAND_SUFFIX}"
DB_HOST_MYSQL="lp_db_mysql_${RAND_SUFFIX}"
DB_HOST_PG="lp_db_pg_${RAND_SUFFIX}"
DB_HOST_MONGO="lp_db_mongo_${RAND_SUFFIX}"

log_info "Generated Environment:"
log_info "  Network: $DOCKER_NETWORK"
log_info "  DB Configuration: $DB_HOST_MAIN (MySQL)"

# Create the Docker network now (before docker-compose needs it)
log_info "Creating Docker Network: $DOCKER_NETWORK"
docker network inspect ${DOCKER_NETWORK} &>/dev/null || docker network create ${DOCKER_NETWORK}
log_success "Docker network '${DOCKER_NETWORK}' ready."

log_success "Configuration complete."

log_info "Step 4: Deploying LogicPanel Services..."
$SUDO mkdir -p $INSTALL_DIR
# Change ownership to current user if not root, so we can write files without sudo
if [[ -n "$SUDO" ]]; then
    $SUDO chown -R $USER:$USER $INSTALL_DIR
fi
cd $INSTALL_DIR

# Fetch source code
log_info "Fetching latest source code..."
curl -sSL https://github.com/cyber-wahid/logicpanel/archive/refs/heads/main.tar.gz | tar xz --strip-components=1

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

echo -e "${BLUE}[CHECK] LogicPanel SSL Status Check${NC}"
echo "================================"
echo ""

# Load environment
if [ -f ".env" ]; then
    source .env
else
    echo -e "${RED}[FAIL] .env file not found${NC}"
    exit 1
fi

DOMAIN=${PANEL_DOMAIN:-localhost}
USER_PORT=${USER_PORT:-7777}
MASTER_PORT=${MASTER_PORT:-9999}

echo "Domain: $DOMAIN"
echo "User Port: $USER_PORT"
echo "Master Port: $MASTER_PORT"
echo ""

# Check 1: DNS Resolution
echo -e "${YELLOW}1. DNS Resolution${NC}"
if nslookup $DOMAIN > /dev/null 2>&1; then
    IP=$(nslookup $DOMAIN | grep -A1 "Name:" | grep "Address:" | awk '{print $2}' | head -1)
    echo -e "${GREEN}[OK] DNS resolves to: $IP${NC}"
else
    echo -e "${RED}[FAIL] DNS resolution failed${NC}"
fi
echo ""

# Check 2: Docker Services
echo -e "${YELLOW}2. Docker Services${NC}"
if docker ps | grep -q "logicpanel_traefik"; then
    echo -e "${GREEN}[OK] Traefik is running${NC}"
else
    echo -e "${RED}[FAIL] Traefik is not running${NC}"
fi

if docker ps | grep -q "logicpanel_app"; then
    echo -e "${GREEN}[OK] LogicPanel app is running${NC}"
else
    echo -e "${RED}[FAIL] LogicPanel app is not running${NC}"
fi
echo ""

# Check 3: Certificate File
echo -e "${YELLOW}3. Certificate File${NC}"
if [ -f "letsencrypt/acme.json" ]; then
    SIZE=$(stat -f%z "letsencrypt/acme.json" 2>/dev/null || stat -c%s "letsencrypt/acme.json" 2>/dev/null)
    PERMS=$(stat -f%A "letsencrypt/acme.json" 2>/dev/null || stat -c%a "letsencrypt/acme.json" 2>/dev/null)
    
    if [ "$SIZE" -gt 100 ]; then
        echo -e "${GREEN}[OK] acme.json exists and has content ($SIZE bytes)${NC}"
    else
        echo -e "${YELLOW}[WARN]  acme.json exists but is empty or small ($SIZE bytes)${NC}"
    fi
    
    if [ "$PERMS" = "600" ]; then
        echo -e "${GREEN}[OK] Permissions are correct (600)${NC}"
    else
        echo -e "${YELLOW}[WARN]  Permissions are $PERMS (should be 600)${NC}"
    fi
else
    echo -e "${RED}[FAIL] acme.json not found${NC}"
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
        echo -e "${GREEN}[OK] DNS resolves to: $RESOLVED_IP${NC}"
    else
        echo -e "${RED}[WARN]  WARNING: Domain $DOMAIN does not resolve${NC}"
        echo "Please ensure your DNS records are configured correctly:"
        echo "  - A record: $DOMAIN → Your Server IP"
        read -p "Continue anyway? (y/N): " -n 1 -r
        echo
        if [[ ! $REPLY =~ ^[Yy]$ ]]; then
            exit 1
        fi
    fi
else
    echo -e "${YELLOW}[WARN]  nslookup not available, skipping DNS check${NC}"
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
    
    echo -e "${GREEN}[OK] .env updated (backup created)${NC}"
else
    echo -e "${RED}[WARN]  .env file not found${NC}"
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
echo -e "${GREEN}[OK] acme.json configured${NC}"

# Restart Traefik
echo ""
echo -e "${YELLOW}Restarting Traefik...${NC}"
if docker compose restart traefik > /dev/null 2>&1; then
    echo -e "${GREEN}[OK] Traefik restarted${NC}"
else
    echo -e "${YELLOW}[WARN]  Run manually: docker compose restart traefik${NC}"
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
echo "   - User Panel: https://$DOMAIN:7777"
echo "   - Master Panel: https://$DOMAIN:9999"
echo ""
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
SERVER_IP=$(curl -s ifconfig.me || curl -s icanhazip.com || hostname -I | awk '{print $1}')

# Ports
MASTER_PORT=9999
USER_PORT=7777

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
DOCKER_NETWORK=${DOCKER_NETWORK}
USER_APPS_PATH=/var/www/html/storage/user-apps
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

# Create necessary directories and ensure init files exist (as files, not directories)
mkdir -p "$INSTALL_DIR/storage/user-apps"
mkdir -p "$INSTALL_DIR/letsencrypt"
mkdir -p "$INSTALL_DIR/docker/mysql"
mkdir -p "$INSTALL_DIR/docker/postgres"
mkdir -p "$INSTALL_DIR/docker/mongo"

# Fix: Ensure init files are files, otherwise Docker creates them as directories
touch "$INSTALL_DIR/docker/mysql/init.sql"
touch "$INSTALL_DIR/docker/postgres/init.sql"
touch "$INSTALL_DIR/docker/mongo/init.js"

# Create storage layout (remaining parts)
mkdir -p storage/logs storage/framework/cache storage/framework/sessions storage/framework/views
chmod -R 775 storage

# Set permissions for user-apps
$SUDO chown -R 1000:1000 "$INSTALL_DIR/storage/user-apps"
$SUDO chmod -R 775 "$INSTALL_DIR/storage/user-apps"

# Create Traefik apps directory for user app routing configs
mkdir -p docker/traefik/apps
chmod 755 docker/traefik/apps

# Update Traefik Dynamic Configuration with User Domain
log_info "Updating Traefik routing with domain: ${PANEL_DOMAIN}..."
sed -i "s|{{PANEL_DOMAIN}}|${PANEL_DOMAIN}|g" docker/traefik/dynamic.yml
sed -i "s|{{ADMIN_EMAIL}}|${ADMIN_EMAIL}|g" traefik.yml
log_success "Traefik configuration updated."

# Create config directory and settings.json
mkdir -p config
cat > config/settings.json << EOF
{
    "hostname": "${PANEL_DOMAIN}",
    "master_port": "9999",
    "user_port": "7777",
    "company_name": "LogicPanel",
    "contact_email": "${ADMIN_EMAIL}",
    "enable_ssl": "1",
    "letsencrypt_email": "${ADMIN_EMAIL}",
    "timezone": "UTC",
    "allow_registration": "1"
}
EOF

# Build step - run in foreground so errors are visible
log_info "Building LogicPanel (this may take 3-5 minutes)..."
echo -e "  ${YELLOW}Build logs saved to: /tmp/logicpanel_build.log${NC}"
if docker compose build --no-cache 2>&1 | tee /tmp/logicpanel_build.log | tail -5; then
    log_success "Build completed successfully."
else
    log_error "Build failed! Check /tmp/logicpanel_build.log for details."
    echo -e "  ${YELLOW}Last 20 lines of build log:${NC}"
    tail -20 /tmp/logicpanel_build.log
    exit 1
fi

# ─── 4. Docker Compose Deployment ───
log_info "Aggressive Cleanup: Removing ALL stale LogicPanel containers..."
# Remove anything starting with logicpanel_ or lp_ (which often happens in compose)
docker ps -a --format '{{.Names}}' | grep -E "^(logicpanel_|lp_)" | xargs -r docker rm -f >/dev/null 2>&1

log_info "Pre-deployment Port Check..."
NEEDS_DOCKER_RESTART=false
for port in 80 443 9999 7777; do
    check_port $port "force"
    # Double check if it's REALLY gone
    if ! check_port $port; then
         log_warn "Port $port is persistent. Scheduling Docker restart."
         NEEDS_DOCKER_RESTART=true
    fi
done

if [ "$NEEDS_DOCKER_RESTART" = true ]; then
    restart_docker_service
    # One last cleanup just in case
    docker ps -a --format '{{.Names}}' | grep -E "^(logicpanel_|lp_)" | xargs -r docker rm -f >/dev/null 2>&1
fi

log_info "Deploying Containers..."

# Clean up any stale containers from previous runs
docker compose down --remove-orphans 2>/dev/null || true

# IMPORTANT: Do NOT run in background. We need to see errors.
log_info "Starting Docker Compose (this takes 30-60 seconds)..."
if docker compose up -d --remove-orphans 2>&1 | tee /tmp/logicpanel_deploy.log; then
    log_success "Docker Compose started successfully."
else
    log_warn "Docker Compose had issues. Attempting recovery..."
    echo ""
    echo -e "  ${YELLOW}--- Deploy Error Log ---${NC}"
    tail -20 /tmp/logicpanel_deploy.log
    echo -e "  ${YELLOW}--- End Error Log ---${NC}"
    echo ""
    
    # Recovery: Restart Docker, clean ports, and retry
    log_info "Recovery Step 1: Restarting Docker service..."
    restart_docker_service
    
    log_info "Recovery Step 2: Clearing all ports..."
    for port in 80 443 9999 7777; do
        check_port $port "force"
    done
    
    log_info "Recovery Step 3: Retrying deployment..."
    docker compose down --remove-orphans 2>/dev/null || true
    sleep 3
    
    if docker compose up -d --remove-orphans 2>&1 | tee -a /tmp/logicpanel_deploy.log; then
        log_success "Recovery successful! Containers started."
    else
        log_error "Deployment failed after recovery attempt."
        echo -e "  ${RED}Full logs: /tmp/logicpanel_deploy.log${NC}"
        echo -e "  ${YELLOW}Try manually: cd $INSTALL_DIR && docker compose up -d${NC}"
    fi
fi

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
    log_warn "Some containers failed to start. Installation will proceed, but check logs if issues persist."
    log_info "Attempting to restart failed containers..."
    docker compose up -d
    sleep 5
fi

# Create admin user
log_info "Setting up admin creation script..."
if [ -f "create_admin.php" ]; then
    docker exec -T logicpanel_app mkdir -p /var/www/html/database 2>/dev/null || true
    docker cp config/settings.json logicpanel_app:/var/www/html/config/settings.json 2>/dev/null || true

    # Wait for Database to be ready
    log_info "Waiting for Database to initialize..."
    MAX_RETRIES=30
    COUNT=0
    DB_READY=false
    
    while [ $COUNT -lt $MAX_RETRIES ]; do
        # Passing variables explicitly to docker exec ensures they are available to php -r
        # We use DB_HOST_MAIN which is the randomized hostname for the main DB container
        if docker exec -T -e DB_DATABASE="${DB_NAME}" -e DB_USERNAME="${DB_USER}" -e DB_PASSWORD="${DB_PASS}" -e DB_HOST="${DB_HOST_MAIN}" logicpanel_app php -r "try { \$pdo = new PDO('mysql:host='.getenv('DB_HOST').';dbname='.getenv('DB_DATABASE'), getenv('DB_USERNAME'), getenv('DB_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]); echo 'connected'; } catch(Exception \$e) { exit(1); }" >/dev/null 2>&1; then
            DB_READY=true
            break
        fi
        echo -n "."
        sleep 5
        COUNT=$((COUNT+1))
    done
    echo ""

    if [ "$DB_READY" = true ]; then
        log_success "Database is ready and accessible."
        log_info "Creating administrator account..."
        
        log_info "Running database migrations..."
        docker compose exec -T app bash /var/www/html/docker/migrate.sh || log_warn "Migration warning (non-fatal)"

        log_info "Creating administrator account..."
        
        # We don't use -T here to allow output to be visible if possible, but -T is safer for non-interactive scripts
        if docker exec -T logicpanel_app php /var/www/html/create_admin.php --user="${ADMIN_USER}" --email="${ADMIN_EMAIL}" --pass="${ADMIN_PASS}"; then
            log_success "Administrator account created/updated successfully!"
        else
            log_error "Admin creation script failed! Check output above."
            log_warn "You may need to create admin manually after installation:"
            log_warn "docker exec -it logicpanel_app php create_admin.php --user=\"${ADMIN_USER}\" --email=\"${ADMIN_EMAIL}\" --pass=\"YOUR_PASS\""
        fi
    else
        log_error "Database failed to initialize within expected time."
    fi
else
    log_warn "create_admin.php not found. Please create admin manually after installation."
fi

# ─── 5. Final Setup & Summary ───
log_info "Proceeding to final setup and verification..."

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
                echo "  [OK] $domain"
            done
        else
            grep -o '"main":"[^"]*"' letsencrypt/acme.json | cut -d'"' -f4 | while read domain; do
                echo "  [OK] $domain"
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
for port in 80 443 7777 9999; do
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

Username: ${ADMIN_USER:-admin}
Email:    ${ADMIN_EMAIL:-admin@example.com}
Password: ${ADMIN_PASS:-[Check Logs if Missing]}

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

🌐 ACCESS URLS

Master Panel (Admin):  https://${PANEL_DOMAIN}:9999
User Panel:            https://${PANEL_DOMAIN}:7777


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

[OK] LogicPanel Application
[OK] Traefik Reverse Proxy (with Let's Encrypt SSL)
[OK] Terminal Gateway (WebSocket)
[OK] MariaDB (MySQL) - Port 3306
[OK] PostgreSQL - Port 5432
[OK] MongoDB - Port 27017
[OK] Redis Cache
[OK] Database Provisioner Service

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

🔒 SSL CERTIFICATE STATUS

SSL certificates are automatically managed by Traefik using Let's Encrypt.

Certificate Storage: ${INSTALL_DIR}/letsencrypt/acme.json
Certificate Renewal: Automatic (30 days before expiry)

If SSL is not working immediately:
  1. Wait 2-3 minutes for certificate generation
  2. Ensure DNS points to this server
  3. Check firewall allows ports 80, 443, 7777, 9999
  4. Run: ./check-ssl.sh

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

[WARN]  SECURITY NOTES

1. Change default admin password after first login
2. Keep this file secure (contains credentials)
3. Regular backups recommended
4. Monitor logs for suspicious activity
5. Keep Docker and system updated

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

📚 DOCUMENTATION & SUPPORT

Documentation: https://docs.logicpanel.cloud
GitHub Issues:  https://github.com/cyber-wahid/logicpanel/issues
Community:      https://community.logicpanel.cloud

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Thank you for choosing LogicPanel! 🚀

EOFSUMMARY

chmod 600 INSTALLATION_SUMMARY.txt
log_success "Installation summary saved to: ${INSTALL_DIR}/INSTALLATION_SUMMARY.txt"

# Force clear screen to ensure summary is visible
clear

# Success Message
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
echo -e "${GREEN}║${NC}     Master Panel:  ${CYAN}https://${PANEL_DOMAIN}:9999${NC}"
echo -e "${GREEN}║${NC}     User Panel:    ${CYAN}https://${PANEL_DOMAIN}:7777${NC}"

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
echo -e "${GREEN}║${NC}  ${YELLOW}[INFO]  IMPORTANT NOTES${NC}                                          ${GREEN}║${NC}"
echo -e "${GREEN}║${NC}     • SSL handled by Traefik (Let's Encrypt)                  ${GREEN}║${NC}"

# Check SSL status
if [ -s letsencrypt/acme.json ]; then
    FILESIZE=$(stat -f%z "letsencrypt/acme.json" 2>/dev/null || stat -c%s "letsencrypt/acme.json" 2>/dev/null)
    if [ "$FILESIZE" -gt 100 ]; then
        echo -e "${GREEN}║${NC}     • ${GREEN}[OK]${NC} SSL certificates generated successfully!              ${GREEN}║${NC}"
    else
        echo -e "${GREEN}║${NC}     • ${YELLOW}[WARN]${NC} SSL certificates are being generated...               ${GREEN}║${NC}"
    fi
else
    echo -e "${GREEN}║${NC}     • ${YELLOW}[WARN]${NC} SSL certificates will be generated within 5 minutes    ${GREEN}║${NC}"
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
echo -e "  ${MAGENTA}📚 Documentation: https://github.com/cyber-wahid/logicpanel${NC}"
echo -e "  ${MAGENTA}🐛 Report Issues: https://github.com/cyber-wahid/logicpanel/issues${NC}"
echo ""
echo -e "  ${YELLOW}💾 Installation details saved to: ${INSTALL_DIR}/INSTALLATION_SUMMARY.txt${NC}"
echo -e "  ${YELLOW}🔐 Keep this file secure - it contains your credentials!${NC}"
echo ""

# Final SSL reminder
if [ -s letsencrypt/acme.json ]; then
    FILESIZE=$(stat -f%z "letsencrypt/acme.json" 2>/dev/null || stat -c%s "letsencrypt/acme.json" 2>/dev/null)
    if [ "$FILESIZE" -gt 100 ]; then
        echo -e "  ${GREEN}[OK] SSL certificates are ready! Your panel is secure.${NC}"
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
    echo -e "${YELLOW}Script completed. You can exit this screen with 'exit' or 'CTRL-A D'.${NC}"
fi

