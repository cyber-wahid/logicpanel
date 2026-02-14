# LogicDock 🚀

> **Modern, secure control panel for hosting Node.js and Python applications.**
> Built with Docker, Traefik, and MariaDB.

[![LogicDock](https://img.shields.io/badge/LogicDock-v1.0.0-blue)](https://github.com/cyber-wahid/panel)
[![License](https://img.shields.io/badge/license-GPL--3.0-green)](LICENSE)

---

## 📥 Quick Installation (One-Line)

Run this on a clean **Ubuntu 20.04+**, **Debian 11+**, or **CentOS 8+** server:

```bash
curl -sSL https://raw.githubusercontent.com/cyber-wahid/panel/main/install.sh | sudo bash
```

The installer auto-configures Docker, firewall, SSL, and databases.

---

## 🌐 Access Your Panel

After installation, access your dashboard:

| Panel | URL | Default Port |
|:---|:---|:---|
| **Master Panel** | `https://your-domain.com` | `:999` |
| **User Panel** | `https://your-domain.com` | `:777` |
| **Database Manager** | `https://db.your-domain.com` | `(Requires DNS A Record)` |

> **Note:** SSL Certificates are generated automatically. If you see "Not Secure", wait 1-2 minutes or run `./check-ssl.sh`.

---

## 🛠️ Essential Commands

Run these from `/opt/logicdock`:

| Action | Command |
|:---|:---|
| **View Logs** | `docker compose logs -f` |
| **Check SSL** | `./check-ssl.sh` |
| **Restart Panel** | `docker compose restart` |
| **Stop Panel** | `docker compose down` |
| **Update Panel** | `git pull && ./install.sh` |
| **Uninstall** | `bash uninstall.sh` |

---

## 🐛 Troubleshooting

- **Database Error?** Ensure you waited for "Services warming up" to finish.
- **SSL Error?** Ensure your DNS points to the server IP and port 80/443 is open.
- **Admin Creation Failed?** Run:

  ```bash
  docker exec -it logicdock_app php /var/www/html/create_admin.php --user="admin" --email="admin@example.com" --pass="password"
  ```

---

<div align="center">
  <b>Made with ❤️ by cyber-wahid</b>
</div>
