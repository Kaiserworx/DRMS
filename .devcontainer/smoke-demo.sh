#!/usr/bin/env bash

set -euo pipefail

cd "$(git rev-parse --show-toplevel)"

php_version="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
node_major="$(node -p 'process.versions.node.split(".")[0]')"
mysql_version="$(MYSQL_PWD="${DB_PASSWORD}" mysql \
    --host=mysql \
    --user=drms_demo \
    --batch \
    --skip-column-names \
    --execute='SELECT VERSION()')"

[[ "${php_version}" == "8.4" ]]
[[ "${node_major}" == "24" ]]
[[ "${mysql_version}" == 8.4.* ]]

php artisan migrate:status --no-interaction >/dev/null
php artisan queue:monitor database:notifications,database:default --max=1

curl \
    --fail \
    --location \
    --silent \
    --show-error \
    --max-time 30 \
    http://127.0.0.1:8000/admin/login \
    >/dev/null

echo "DRMS Codespaces demo smoke test passed."
