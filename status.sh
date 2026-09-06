#!/usr/bin/env bash
# ==============================================================================
# Sarkari.online - 1-Command Master Health & Diagnostics Status Inspector
# Usage:
#   bash status.sh
#   ./status.sh
# ==============================================================================

set -e

# Detect if running inside Docker container or on host machine
if [ -f /.dockerenv ] || grep -q 'docker\|containerd' /proc/1/cgroup 2>/dev/null; then
    # Inside Docker container
    php /var/www/html/cron/system-health.php "$@"
else
    # On Host Server
    if command -v docker >/dev/null 2>&1 && docker ps --format '{{.Names}}' | grep -q "^sarkari_app$"; then
        docker exec -it sarkari_app php /var/www/html/cron/system-health.php "$@"
    elif [ -f "/var/www/sarkari.online/cron/system-health.php" ]; then
        php /var/www/sarkari.online/cron/system-health.php "$@"
    else
        php "$(dirname "$0")/cron/system-health.php" "$@"
    fi
fi
