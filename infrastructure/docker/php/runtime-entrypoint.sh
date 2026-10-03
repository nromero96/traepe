#!/bin/sh
set -eu

# CLI/Horizon run as root; FPM workers use www-data. Share only the log group.
mkdir -p /var/www/html/storage/logs
chgrp www-data /var/www/html/storage/logs
chmod 2770 /var/www/html/storage/logs
if [ -f /var/www/html/storage/logs/technical.jsonl ]; then
    chgrp www-data /var/www/html/storage/logs/technical.jsonl
    chmod 0660 /var/www/html/storage/logs/technical.jsonl
fi

exec docker-php-entrypoint "$@"
