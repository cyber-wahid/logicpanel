# LogicPanel - Production Release (v3.1) 🚀

> **The ultimate hosting control panel for Node.js and Python developers.**
> LogicPanel provides a modern, secure, and automated environment to deploy, manage, and scale your applications effortlessly with full Docker isolation and Traefik-powered SSL.

---

## ⚡ Quick Installation (2 minutes)

### Prerequisites
- Clean Linux VPS (Ubuntu 20.04+, Debian 11+, RHEL/Alma 8/9, Fedora 36+)
- Root access
- Domain pointing to server IP
- Standard ports open (80, 443, 7777, 9999)

### Install Command
```bash
curl -sSL https://raw.githubusercontent.com/cyber-wahid/logicpanel/main/install.sh | sudo bash
```

---

## 🔐 SSL & HTTP/3 (High-Speed Networking)

✅ **Automatic SSL**: Certificates generated automatically via Let's Encrypt.  
✅ **HTTP/3 Support**: Enabled by default for all interfaces (WebSecure, UserPanel, MasterPanel).

> [!IMPORTANT]
> To activate HTTP/3 after installation or update, ensure you restart the services:  
> `cd /opt/logicpanel && docker compose restart traefik`

---

## 🌐 Dashboard Access

| Interface | URL | Port | Access Level |
|:--- |:--- |:--- |:--- |
| **Master Panel** | `https://your-domain.com:9999` | 9999 | Admin / Reseller |
| **User Panel** | `https://your-domain.com:7777` | 7777 | Client / End-user |

---

## 🚀 Deployment Guide

### Deploy Your First App
1. Login to **User Panel** (Port 7777).
2. Click **"Create Application"** and select Node.js or Python.
3. Enter your GitHub URL and desired subdomain.
4. Click **"Deploy"** and wait for the magic to happen! ⚡

### Databases
- Create **MySQL**, **PostgreSQL**, or **MongoDB** instances instantly via the "Databases" tab.
- Credentials and connection strings are provided immediately upon creation.

---

## 🗑️ Maintenance & Uninstallation

### 🧼 Debris Cleanup (Recommended)
To keep your server lean by removing unused Docker images and dangling volumes:
```bash
# Safely clean system debris
docker system prune -a --volumes
```

### 🚮 Complete Removal (Uninstall)
To completely remove LogicPanel and all associated data:
```bash
curl -sSL https://raw.githubusercontent.com/cyber-wahid/logicpanel/main/uninstall.sh | sudo bash
```
**⚠️ Warning:** This deletes all containers, databases, and user files permanently.

---

## 🛠️ Essential Commands

| Action | Command |
|:--- |:--- |
| **View Logs** | `docker compose logs -f` |
| **Restart Panel** | `docker compose restart` |
| **Check SSL** | `./check-ssl.sh` |
| **Update Panel** | `git pull && bash install.sh` |

---

## 📞 Support & Links

**Author**: [@cyber-wahid](https://github.com/cyber-wahid)  
**Website**: [https://logicdock.cloud](https://logicdock.cloud)
**Documentation**: [https://docs.logicdock.cloud](https://docs.logicdock.cloud)

<div align="center">
  <b>Built for the next generation of PaaS hosting.</b>
</div>
