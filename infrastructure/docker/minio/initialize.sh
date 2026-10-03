#!/bin/sh
set -eu
# Credentials are never printed, including on failure. Only this container has root access.
if ! mc alias set local http://minio:9000 "$MINIO_ROOT_USER" "$MINIO_ROOT_PASSWORD" >/dev/null 2>&1 \
    || ! mc mb --ignore-existing local/traepe-local >/dev/null 2>&1 \
    || ! mc admin user add local "$MINIO_APP_USER" "$MINIO_APP_PASSWORD" >/dev/null 2>&1 \
    || ! mc admin policy create local traepe-local /policy.json >/dev/null 2>&1 \
    || ! mc admin policy attach local traepe-local --user "$MINIO_APP_USER" >/dev/null 2>&1; then
    echo "Local storage initialization failed."
    exit 1
fi
echo "Private local bucket and restricted application credential initialized."
