#!/usr/bin/env bash

###############################################################################
# LogicPanel Auto-Update Script v3.1
# Author: cyber-wahid
# Description: Pulls latest version from GitHub and rebuilds containers
# Supports: RHEL (DNF/YUM) and APT (Debian/Ubuntu) distributions
###############################################################################

set -e  # Exit on error

REPO_URL="https://github.com/cyber-wahid/logicpanel.git"
BRANCH="main"
# Get script directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
LOG_FILE="${SCRIPT_DIR}/storage/logs/update.log"
BACKUP_DIR="${SCRIPT_DIR}/storage/backups"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)

# Ensure log directory exists
mkdir -p "${SCRIPT_DIR}/storage/logs"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Logging functions
log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"
}
log_info() { echo -e "${BLUE}[INFO]${NC} $1" | tee -a "$LOG_FILE"; }
log_success() { echo -e "${GREEN}[OK]${NC} $1" | tee -a "$LOG_FILE"; }
log_warn() { echo -e "${YELLOW}[WARN]${NC} $1" | tee -a "$LOG_FILE"; }
log_error() { echo -e "${RED}[ERROR]${NC} $1" | tee -a "$LOG_FILE"; }

log "========================================="
log "LogicPanel Update Started"
log "========================================="

# Check if we're inside container or on host
if [ -f /.dockerenv ]; then
    log "Running inside Docker container"
    IS_CONTAINER=true
else
    log "Running on host machine"
    IS_CONTAINER=false
fi

# Step 1: Backup current version
log "Step 1: Creating backup..."
mkdir -p "$BACKUP_DIR"
BACKUP_FILE="$BACKUP_DIR/backup_$TIMESTAMP.tar.gz"

# Backup important files (exclude storage, vendor, node_modules)
tar -czf "$BACKUP_FILE" \
    --exclude='storage/logs/*' \
    --exclude='storage/user-apps/*' \
    --exclude='vendor' \
    --exclude='node_modules' \
    --exclude='*.tar.gz' \
    . 2>/dev/null || log "Warning: Some files couldn't be backed up"

log "Backup created: $BACKUP_FILE"

# Step 2: Fetch latest version info
log "Step 2: Checking latest version..."
CURRENT_VERSION=$(cat VERSION 2>/dev/null || echo "0.0.0")
log "Current version: $CURRENT_VERSION"

# Step 3: Pull latest code from GitHub
log "Step 3: Pulling latest code from GitHub..."

if [ -d ".git" ]; then
    log "Git repository detected, pulling updates..."
    
    # Stash any local changes
    git stash save "Auto-stash before update $TIMESTAMP" 2>&1 | tee -a "$LOG_FILE"
    
    # Fetch latest
    git fetch origin "$BRANCH" 2>&1 | tee -a "$LOG_FILE"
    
    # Pull latest
    git pull origin "$BRANCH" 2>&1 | tee -a "$LOG_FILE"
    
    NEW_VERSION=$(cat VERSION 2>/dev/null || echo "0.0.0")
    log "New version: $NEW_VERSION"
else
    log "Not a git repository. Downloading latest release..."
    
    # Download latest release as zip
    TEMP_DIR="/tmp/logicpanel_update_$TIMESTAMP"
    mkdir -p "$TEMP_DIR"
    
    curl -L "https://github.com/cyber-wahid/logicpanel/archive/refs/heads/$BRANCH.zip" \
        -o "$TEMP_DIR/latest.zip" 2>&1 | tee -a "$LOG_FILE"
    
    # Extract (excluding certain directories)
    unzip -q "$TEMP_DIR/latest.zip" -d "$TEMP_DIR" 2>&1 | tee -a "$LOG_FILE"
    
    # Copy files (preserve .env and storage)
    rsync -av --exclude='.env' --exclude='storage/logs/*' --exclude='storage/user-apps/*' \
        "$TEMP_DIR/logicpanel-$BRANCH/" . 2>&1 | tee -a "$LOG_FILE"
    
    # Cleanup
    rm -rf "$TEMP_DIR"
    
    NEW_VERSION=$(cat VERSION 2>/dev/null || echo "0.0.0")
    log "New version: $NEW_VERSION"
fi

# Step 4: Install/Update dependencies
log "Step 4: Updating dependencies..."

if [ -f "composer.json" ]; then
    log "Running composer install..."
    composer install --no-dev --optimize-autoloader --no-interaction 2>&1 | tee -a "$LOG_FILE" || log "Warning: Composer install had issues"
fi

# Step 5: Run database migrations if any
log "Step 5: Checking for database migrations..."
if [ -f "docker/migrate.sh" ]; then
    log "Running migrations..."
    bash docker/migrate.sh 2>&1 | tee -a "$LOG_FILE" || log "Warning: Migrations had issues"
fi

# Step 6: Rebuild and restart containers (if on host)
if [ "$IS_CONTAINER" = false ]; then
    log "Step 6: Rebuilding Docker containers..."
    
    if command -v docker-compose &> /dev/null || command -v docker &> /dev/null; then
        log "Stopping containers..."
        docker compose down 2>&1 | tee -a "$LOG_FILE" || docker-compose down 2>&1 | tee -a "$LOG_FILE"
        
        log "Rebuilding containers..."
        docker compose build --no-cache 2>&1 | tee -a "$LOG_FILE" || docker-compose build --no-cache 2>&1 | tee -a "$LOG_FILE"
        
        log "Starting containers..."
        docker compose up -d 2>&1 | tee -a "$LOG_FILE" || docker-compose up -d 2>&1 | tee -a "$LOG_FILE"
    else
        log "Warning: Docker not found, skipping container rebuild"
    fi
else
    log "Step 6: Skipping container rebuild (running inside container)"
    log "Container will be restarted by orchestrator"
fi

# Step 7: Clear caches
log "Step 7: Clearing caches..."
rm -rf storage/framework/cache/* 2>/dev/null || true
rm -rf storage/framework/sessions/* 2>/dev/null || true

# Step 8: Fix permissions
log "Step 8: Fixing permissions..."
chmod -R 775 storage 2>/dev/null || true
chmod +x update.sh 2>/dev/null || true

log "========================================="
log "Update completed successfully!"
log "Version: $CURRENT_VERSION → $NEW_VERSION"
log "========================================="

exit 0
