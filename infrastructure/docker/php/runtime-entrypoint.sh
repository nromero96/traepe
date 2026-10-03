#!/bin/sh
set -eu

# CLI/Horizon and FPM share writable runtime paths, including on native Linux.
# Setgid preserves the runtime group; no source tree or public file is writable.
umask 0002
for directory in \
    /var/www/html/storage/framework/cache \
    /var/www/html/storage/framework/sessions \
    /var/www/html/storage/framework/views \
    /var/www/html/bootstrap/cache
do
    mkdir -p "$directory"
    find "$directory" -type d -exec chgrp www-data {} + -exec chmod 2770 {} +
    find "$directory" -type f ! -name '.gitignore' -exec chgrp www-data {} + -exec chmod 0660 {} +
done
mkdir -p /var/www/html/storage/framework/cache/data
chgrp www-data /var/www/html/storage/framework/cache/data
chmod 2770 /var/www/html/storage/framework/cache/data

# Logs retain the stricter channel-specific file permission.
mkdir -p /var/www/html/storage/logs
chgrp www-data /var/www/html/storage/logs
chmod 2770 /var/www/html/storage/logs
if [ -f /var/www/html/storage/logs/technical.jsonl ]; then
    chgrp www-data /var/www/html/storage/logs/technical.jsonl
    chmod 0660 /var/www/html/storage/logs/technical.jsonl
fi

exec docker-php-entrypoint "$@"
