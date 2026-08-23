# Sarkari.online — Production Docker DevOps Architecture

A fully isolated, zero-impact Docker production stack for Sarkari.online that runs safely alongside other client websites on an Ubuntu/Nginx server.

---

## 🏗️ Architecture Overview

- **`sarkari_app`**: PHP 8.3-FPM + Alpine Nginx + 24/7 Crond Daemon (Bound to `127.0.0.1:8085`).
- **`sarkari_db`**: Isolated MariaDB 10.11 on private bridge network `sarkari_network`.
- **Host Nginx**: Serves as a lightweight Reverse Proxy (`sarkari.online` ➡️ `127.0.0.1:8085`) with Certbot SSL.
- **Client Projects**: 100% untouched and isolated on the host system.

---

## 🚀 5-Minute VPS Deployment Guide (via SSH)

### 1. Install Docker & Docker Compose on Server (If not installed)
```bash
sudo apt update
sudo apt install -y docker.io docker-compose-plugin
sudo systemctl enable --now docker
```

### 2. Clone Repository
```bash
sudo git clone https://sarkari-online:YOUR_TOKEN@github.com/sarkari-online/sarkari-online.git /var/www/sarkari.online
cd /var/www/sarkari.online
```

### 3. Create Environment File (.env)
```bash
cp .env.example .env
nano .env
```
*(Add your Gemini API Key, then press `Ctrl+O`, `Enter`, `Ctrl+X`)*

### 4. Build & Start Docker Stack (1-Command)
```bash
sudo docker compose up -d --build
```
*(The container will automatically wait for the database, import `production_dump.sql` with 14 live articles, and start the 24/7 AI publishing cron!)*

### 5. Setup Host Nginx Reverse Proxy
```bash
sudo cp host-nginx-reverse-proxy.conf.example /etc/nginx/sites-available/sarkari.online
sudo ln -s /etc/nginx/sites-available/sarkari.online /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

### 6. Activate Free SSL (Certbot HTTPS)
```bash
sudo certbot --nginx -d sarkari.online -d www.sarkari.online
```

---

## 📊 Useful Docker DevOps Commands

```bash
# Check running containers
docker compose ps

# View live application & cron logs
docker compose logs -f app

# Restart stack
docker compose restart

# Pull & Rebuild on updates
docker compose up -d --build
```
