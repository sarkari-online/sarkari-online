#!/bin/bash
set -e

echo "🚀 [Sarkari.online Docker] Starting container initialization..."

# Wait for MySQL database container to be ready
if [ -n "$DB_HOST" ]; then
    echo "⏳ Waiting for database connection at $DB_HOST:$DB_PORT..."
    max_retries=30
    count=0
    until mysqladmin ping -h"$DB_HOST" -P"${DB_PORT:-3306}" -u"$DB_USERNAME" -p"$DB_PASSWORD" --silent || [ $count -ge $max_retries ]; do
        echo "   Database not ready yet, retrying in 2 seconds... ($count/$max_retries)"
        sleep 2
        count=$((count+1))
    done

    if [ $count -ge $max_retries ]; then
        echo "⚠️ Warning: Database connection timed out. Propceeding anyway..."
    else
        echo "✅ Database connection established!"

        # Check if articles table exists, if not import initial dump
        TABLE_EXISTS=$(mysql -h"$DB_HOST" -P"${DB_PORT:-3306}" -u"$DB_USERNAME" -p"$DB_PASSWORD" -e "SHOW TABLES LIKE 'articles';" "$DB_DATABASE" 2>/dev/null | grep articles || true)

        if [ -z "$TABLE_EXISTS" ] && [ -f "/var/www/html/database/production_dump.sql" ]; then
            echo "📦 Initializing clean production database from production_dump.sql..."
            mysql -h"$DB_HOST" -P"${DB_PORT:-3306}" -u"$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" < /var/www/html/database/production_dump.sql
            echo "✅ Database schema & 14 live articles successfully imported!"
        fi
    fi
fi

# Ensure storage directories permissions
mkdir -p /var/www/html/storage/logs /var/www/html/storage/cache /var/www/html/storage/generated /var/www/html/uploads
chown -R www-data:www-data /var/www/html/storage /var/www/html/uploads
chmod -R 775 /var/www/html/storage /var/www/html/uploads

echo "⚡ Starting Supervisord (PHP-FPM, Nginx, Crond)..."
exec /usr/bin/supervisord -n -c /etc/supervisord.conf
