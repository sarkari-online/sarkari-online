#!/bin/bash
# Sarkari.online - Permanent Storage Permissions Auto-Healer
# Runs inside Docker container to fix ownership after every docker cp
dirs=(
    "/var/www/html/storage/logs"
    "/var/www/html/storage/cache"
    "/var/www/html/storage/generated"
    "/var/www/html/uploads/thumbnails"
    "/var/www/html/uploads"
    "/var/www/html/storage"
)
for dir in "${dirs[@]}"; do
    mkdir -p "$dir" 2>/dev/null
    chown -R www-data:www-data "$dir" 2>/dev/null
    chmod -R 777 "$dir" 2>/dev/null
done
echo "[$(date '+%Y-%m-%d %H:%M:%S')] Permissions healed successfully."
