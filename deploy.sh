#!/usr/bin/env bash
# ==============================================================================
# Sarkari.online — 1-Click Production Deployment & Sync Script
# Run on server: bash deploy.sh
# ==============================================================================

set -e

echo "🚀 [1/4] Pulling latest code from GitHub main branch..."
git pull origin main

echo "🔒 [2/4] Setting proper file permissions for www-data..."
sudo chown -R www-data:www-data /var/www/sarkari.online
sudo find /var/www/sarkari.online -type d -exec chmod 755 {} \;
sudo find /var/www/sarkari.online -type f -exec chmod 644 {} \;
sudo chmod -R 775 /var/www/sarkari.online/storage
sudo chmod -R 775 /var/www/sarkari.online/uploads

echo "⚡ [3/4] Reloading PHP-FPM and Nginx..."
sudo systemctl reload php8.3-fpm || sudo systemctl reload php8.2-fpm
sudo systemctl reload nginx

echo "✅ [4/4] Deployment successful! Sarkari.online is up to date."
