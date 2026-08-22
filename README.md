# Sarkari.online — Autonomous Indian Education & Career Portal

A modern, high-trust, production-ready educational news network and automated publishing system designed for competitive exams, government recruitment, board results, and scholarships across India.

---

## 📁 Repository Structure

```
├── app/                  # Application Core (Controllers, AI Engine, Services, DB)
│   ├── AI/               # Gemini & Verification Engines
│   ├── Database/         # PDO Singleton Database Layer
│   ├── Helpers/          # Auth, CSRF, SEO, Sanitizer, Logger
│   └── Services/         # Publishing, Pipeline, Thumbnail, Trends
├── components/           # Reusable UI Components (Header, Footer, Nav, Cards)
├── admin/                # Admin Management & Verification Console
├── cron/                 # Background Autonomous Workers
├── assets/               # CSS, JavaScript, Web Fonts, Logo & Favicon Assets
├── uploads/              # Generated WebP Thumbnails & Media
├── database/             # Production MySQL Dump & Schema
│   ├── production_dump.sql  # Complete Database Dump (Live Ready)
│   └── schema.sql           # Clean Database DDL
├── nginx.conf.example    # Nginx Virtual Host Configuration
├── deploy.sh             # 1-Click Server Update Script
└── .env.example          # Environment Variables Template
```

---

## 🚀 Quick Production Server Deployment (Ubuntu / Nginx)

### 1. Clone to Server
```bash
sudo git clone https://github.com/YOUR_USERNAME/sarkari-online.git /var/www/sarkari.online
cd /var/www/sarkari.online
```

### 2. Configure Environment (.env)
```bash
cp .env.example .env
nano .env
```
*(Update your DB credentials, `APP_URL=https://sarkari.online`, and `GEMINI_API_KEY`)*

### 3. Import Production Database
```bash
mysql -u sarkari_user -p automation < database/production_dump.sql
```

### 4. Setup File Permissions
```bash
sudo chown -R www-data:www-data /var/www/sarkari.online
sudo chmod -R 775 /var/www/sarkari.online/storage
sudo chmod -R 775 /var/www/sarkari.online/uploads
```

### 5. Nginx & Free SSL (Let's Encrypt)
```bash
sudo cp nginx.conf.example /etc/nginx/sites-available/sarkari.online
sudo ln -s /etc/nginx/sites-available/sarkari.online /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx

# Generate Free HTTPS Certificate
sudo certbot --nginx -d sarkari.online -d www.sarkari.online
```

### 6. Enable 24/7 Background Crontab
Run `crontab -e` on the server and append:
```bash
*/15 * * * * cd /var/www/sarkari.online && php cron/fetch-trends.php >> /var/www/sarkari.online/storage/logs/cron.log 2>&1
*/20 * * * * cd /var/www/sarkari.online && php cron/analyze-trends.php >> /var/www/sarkari.online/storage/logs/cron.log 2>&1
*/30 * * * * cd /var/www/sarkari.online && php cron/generate-articles.php >> /var/www/sarkari.online/storage/logs/cron.log 2>&1
*/45 * * * * cd /var/www/sarkari.online && php cron/publish-articles.php >> /var/www/sarkari.online/storage/logs/cron.log 2>&1
```

---

## 🔄 1-Click Server Update (After Git Push)
Whenever you push changes to GitHub, run this single command on your server:
```bash
bash /var/www/sarkari.online/deploy.sh
```
