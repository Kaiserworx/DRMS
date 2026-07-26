#!/usr/bin/env bash

set -euo pipefail

cd "$(git rev-parse --show-toplevel)"

for variable_name in CODESPACE_NAME DB_PASSWORD DRMS_DEMO_PASSWORD; do
    if [[ -z "${!variable_name:-}" ]]; then
        echo "Required Codespaces environment variable is missing: ${variable_name}" >&2
        exit 1
    fi
done

php_version="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
node_major="$(node -p 'process.versions.node.split(".")[0]')"

if [[ "${php_version}" != "8.4" ]]; then
    echo "PHP 8.4 is required; found ${php_version}." >&2
    exit 1
fi

if [[ "${node_major}" != "24" ]]; then
    echo "Node.js 24 is required; found $(node --version)." >&2
    exit 1
fi

echo "Waiting for the private MySQL service..."
for attempt in {1..60}; do
    if MYSQL_PWD="${DB_PASSWORD}" mysqladmin ping \
        --host=mysql \
        --user=drms_demo \
        --silent; then
        break
    fi

    if [[ "${attempt}" -eq 60 ]]; then
        echo "MySQL did not become ready within the allowed time." >&2
        exit 1
    fi

    sleep 2
done

mysql_version="$(MYSQL_PWD="${DB_PASSWORD}" mysql \
    --host=mysql \
    --user=drms_demo \
    --batch \
    --skip-column-names \
    --execute='SELECT VERSION()')"

if [[ "${mysql_version}" != 8.4.* ]]; then
    echo "MySQL 8.4 is required; found ${mysql_version}." >&2
    exit 1
fi

umask 077
cp .env.codespaces.example .env
sed -i "s|^APP_URL=.*|APP_URL=https://${CODESPACE_NAME}-8000.app.github.dev|" .env
chmod 600 .env

composer install --no-interaction --prefer-dist --optimize-autoloader
php artisan key:generate --force --no-interaction

npm ci
npm run build

php artisan migrate:fresh --seed --force --no-interaction
php artisan optimize:clear

php artisan test --compact
php vendor/bin/pint --test
composer audit --no-dev
npm audit --audit-level=high

php artisan optimize

echo "DRMS Codespaces demo setup completed successfully."
