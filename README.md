<h1>LogicPanel</h1>
  <p><b>The Next-Generation Hosting Control Panel for Modern Web Apps.</b></p>
  <p>
    <a href="https://github.com/cyber-wahid/logicpanel/releases"><img src="https://img.shields.io/github/v/release/cyber-wahid/logicpanel" alt="Latest Release" /></a>
    <img src="https://img.shields.io/badge/PHP-8.3+-blue.svg" alt="PHP 8.3+" />
    <img src="https://img.shields.io/badge/Docker-Powered-2496ED.svg" alt="Docker Powered" />
    <img src="https://img.shields.io/badge/HTTP%2F3-Supported-success.svg" alt="HTTP/3 Supported" />
  </p>
</div>

---

**LogicPanel** is an ultra-fast, modern, and highly secure web hosting control panel designed for developers and agencies. Built with pure Docker isolation, it natively supports **Node.js**, **Python**, and **N8n**, alongside classic databases and robust SSL management out of the box. Say goodbye to heavy legacy panels.

<img width="1366" height="644" alt="image" src="https://github.com/user-attachments/assets/cd3a464b-70bb-4bde-9779-d18ac33dec4f" />

<img width="1366" height="644" alt="image" src="https://github.com/user-attachments/assets/eeec83ee-4fc9-42f7-8338-fe8d02a3bcf7" />



## 🌟 Key Features

### 🚀 Zero-Config App Deployments
Deploy applications instantly right from the dashboard.
*   **Node.js**: Multiple runtime versions supported (`18`, `20`, `22`, etc.)
*   **Python**: Run your Flask, Django, or FastAPI apps easily.
*   **N8n**: Setup fully-functional N8n workflow automation instances with one click.
*   **Databases**: Instant provisioning for PostgreSQL, MySQL, Redis, and MongoDB.

### 🔒 Built-in Security & Isolation
*   **100% Docker Isolation**: Every app runs in its own tightly sandboxed container, ensuring that resource limits and data privacy are strictly enforced.
*   **Traefik Proxy Engine**: Fully automated reverse proxy routing with zero downtime reloads.
*   **Automated SSL/TLS**: Free Let's Encrypt certificates are provisioned automatically for every domain and addon domain. Includes a manual SSL Manager for customized certs.
*   **HTTP/3 Support**: blazing fast, modern networking enabled by default.

### 💻 Developer Tools Included
*   **Web-based Terminal**: Get instant, secure shell access to your app's container without leaving your browser.
*   **Advanced File Manager**: Edit, upload, and manage application files directly.
*   **Detailed Metrics**: Live CPU, RAM, and Disk monitoring on the dashboard.

### 👥 Reseller & Multi-Tenant Support
*   **Master Panel (Port `9999`)**: Manage global settings, API keys, users, and all containers across the server.
*   **User Panel (Port `7777`)**: Clean, minimalist interface for your clients to deploy and manage their own apps.

---

## ⚡ Quick Installation

Getting LogicPanel up and running takes less than 2 minutes on a fresh server.

**Requirements:**
*   A clean Linux VPS (Ubuntu 20.04+, Debian 11+)
*   Root privileges
*   Open standard ports: `80`, `443`, `7777`, `9999`

Run the following command as `root`:
```bash
curl -sSL https://raw.githubusercontent.com/cyber-wahid/logicpanel/main/install.sh | sudo bash
```

Once installed, access your panels:
*   **Master Admin Panel**: `https://<YOUR_IP_OR_DOMAIN>:9999`
*   **User Dashboard**: `https://<YOUR_IP_OR_DOMAIN>:7777`

---

## 🏗️ Technical Stack

*   **Backend Interface**: PHP 8.3 (Slim Framework 4, Eloquent ORM)
*   **Frontend**: Vanilla JS, optimized custom CSS (Classic UI)
*   **Core Engine**: Docker Engine, Docker Compose
*   **Proxy & Routing**: Traefik v3
*   **Data Storage**: SQLite (Internal state) & MariaDB

---

## 🧹 Maintenance

### Clean Up System Debris
LogicPanel leverages Docker, which can leave unused images over time. Keep your server lean:
```bash
docker system prune -a --volumes
```

### Complete Uninstallation
*Warning: This will permanently destroy all hosted apps, databases, and LogicPanel configurations.*
```bash
curl -sSL https://raw.githubusercontent.com/cyber-wahid/logicpanel/main/uninstall.sh | sudo bash
```

---

## 💬 Support & Links

*   **Documentation**: [docs.logicdock.cloud](https://docs.logicdock.cloud)
*   **Website**: [logicdock.cloud](https://logicdock.cloud)
*   **Author**: [@cyber-wahid](https://github.com/cyber-wahid)

<div align="center">
  <i>Simplicity is the ultimate sophistication.</i>
</div>
