#!/usr/bin/env bash
# One-time local setup for http://sharm-station.loc (needs sudo).
# Adds hosts entries, installs the Apache vhost and reloads Apache. Safe to run again.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
CONF_NAME="sharm-station.loc.conf"

for host in sharm-station.loc www.sharm-station.loc api.sharm-station.loc admin.sharm-station.loc; do
    if ! grep -qE "^[0-9.]+[[:space:]]+${host//./\\.}([[:space:]]|$)" /etc/hosts; then
        echo "127.0.0.1 ${host}" | sudo tee -a /etc/hosts >/dev/null
        echo "hosts: added ${host}"
    fi
done

sudo cp "${ROOT}/deploy/apache/${CONF_NAME}" "/etc/apache2/sites-available/${CONF_NAME}"
sudo a2enmod -q proxy proxy_http headers >/dev/null
sudo a2ensite -q "${CONF_NAME}" >/dev/null
sudo apache2ctl configtest
sudo systemctl reload apache2

echo "Done. Site: http://sharm-station.loc   API: http://api.sharm-station.loc/api/v1/meta   Admin: http://admin.sharm-station.loc/admin"
