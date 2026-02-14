# LogicPanel - Quick Start Guide

## ⚡ Installation (2 minutes)

### Prerequisites
- Clean Linux VPS (Ubuntu 20.04+, Debian 11+, CentOS 8+, Fedora 36+)
- Root access
- Domain pointing to server IP
- Ports 80, 443, 777, 999 available

### Install Command
```bash
curl -sSL https://raw.githubusercontent.com/LogicPanel/logicpanel/main/install.sh | sudo bash
```

### What You'll Be Asked:
1. **Domain:** panel.example.com
2. **Admin Username:** (auto-generated or custom)
3. **Admin Email:** admin@example.com
4. **Admin Password:** (minimum 8 characters)

### Installation Time:
- Download & Setup: ~30 seconds
- Docker Build: ~3-5 minutes
- SSL Certificate: ~1-2 minutes
- **Total: ~5-8 minutes**

---

## 🔐 SSL & HTTP/3

### Automatic SSL
✅ SSL certificates are generated automatically during installation
✅ No manual configuration needed
✅ Auto-renewal before expiration

### HTTP/3 (QUIC) Support
✅ Enabled by default on all HTTPS ports
✅ UDP ports automatically configured
✅ Firewall rules applied automatically

### Verify SSL
```bash
cd /opt/logicpanel
./check-ssl.sh
```

### Verify HTTP/3
```bash
# Check UDP ports
sudo ss -ulnp | grep -E ":443|:777|:999"

# Should show:
# udp   UNCONN 0  0  *:443   *:*
# udp   UNCONN 0  0  *:777   *:*
# udp   UNCONN 0  0  *:999   *:*
```

---

## 🌐 Access Your Panel

### Master Panel (Admin)
```
https://your-domain.com:999
```
- Create user accounts
- Manage packages
- Configure databases
- System settings

### User Panel
```
https://your-domain.com:777
```
- Deploy applications
- Manage databases
- File manager
- Terminal access

---

## 🚀 Deploy Your First App

### Node.js Application
1. Login to User Panel (port 777)
2. Click "Create Application"
3. Select "Node.js"
4. Enter GitHub repository URL
5. Configure settings:
   - Port: 3000 (default)
   - Node version: 20 (recommended)
   - Environment variables (optional)
6. Click "Deploy"
7. Wait 1-2 minutes
8. Access at: `https://app-name.your-domain.com`

### Python Application
1. Same steps as Node.js
2. Select "Python" instead
3. Port: 5000 (default)
4. Python version: 3.11 (recommended)

---

## 🗄️ Create Database

### MySQL Database
1. Go to "Databases" → "MySQL"
2. Click "Create Database"
3. Enter database name
4. Click "Create"
5. Credentials shown automatically

### PostgreSQL Database
1. Go to "Databases" → "PostgreSQL"
2. Same process as MySQL

### MongoDB Database
1. Go to "Databases" → "MongoDB"
2. Same process as MySQL

---

## 🛠️ Common Tasks

### View Application Logs
```bash
# From panel: Click app → "Logs" tab

# From terminal:
docker logs logicpanel_app_service_<id>
```

### Restart Application
```bash
# From panel: Click app → "Restart" button

# From terminal:
docker restart logicpanel_app_service_<id>
```

### Update Panel
```bash
# From panel: Settings → Updater → Check for Updates

# From terminal:
cd /opt/logicpanel
./update.sh
```

### Backup Data
```bash
# Backup entire installation
sudo tar -czf logicpanel-backup-$(date +%Y%m%d).tar.gz /opt/logicpanel

# Backup databases only
cd /opt/logicpanel
docker compose exec logicpanel-db mysqldump -u root -p --all-databases > backup.sql
```

---

## 🗑️ Uninstall

### Complete Removal
```bash
curl -sSL https://raw.githubusercontent.com/LogicPanel/logicpanel/main/uninstall.sh | sudo bash
```

### What Gets Deleted:
- All containers
- All databases
- All user applications
- All configuration files
- All data (irreversible!)

---

## 🐛 Troubleshooting

### SSL Not Working
```bash
# Check Traefik logs
cd /opt/logicpanel
docker compose logs -f traefik

# Common issues:
# 1. DNS not pointing to server
# 2. Firewall blocking ports
# 3. Domain not propagated yet

# Fix: Wait 5-10 minutes, then restart Traefik
docker compose restart traefik
```

### Port Already in Use
```bash
# Find what's using the port
sudo ss -tulpn | grep :999

# Stop the service
sudo systemctl stop <service-name>

# Restart LogicPanel
cd /opt/logicpanel
docker compose restart
```

### Container Won't Start
```bash
# Check status
docker compose ps

# View logs
docker compose logs <container-name>

# Restart all
docker compose restart

# Rebuild if needed
docker compose down
docker compose up -d --build
```

### Application Won't Deploy
```bash
# Check application logs
docker logs logicpanel_app_service_<id>

# Common issues:
# 1. Wrong port in application
# 2. Missing dependencies
# 3. Build errors

# Fix: Check logs and update application code
```

---

## 📞 Get Help

### Documentation
- GitHub: https://github.com/LogicPanel/logicpanel
- Issues: https://github.com/LogicPanel/logicpanel/issues

### Support
- Email: support@logicpanel.cloud
- Website: https://logicpanel.cloud

---

## ✅ Checklist

- [ ] Domain DNS configured
- [ ] Server meets requirements (2GB RAM, 20GB storage)
- [ ] Ports 80, 443, 777, 999 available
- [ ] Installation completed successfully
- [ ] SSL certificate generated
- [ ] HTTP/3 verified
- [ ] Master panel accessible
- [ ] User panel accessible
- [ ] First application deployed
- [ ] Database created and working

**All checked? You're ready to go! 🎉**
