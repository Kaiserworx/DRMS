#!/usr/bin/env bash

set -euo pipefail

cd "$(git rev-parse --show-toplevel)"

if [[ ! -f .env ]]; then
    echo "The demo environment is not initialized. Run .devcontainer/setup-demo.sh first." >&2
    exit 1
fi

mkdir -p storage/logs storage/framework/cache storage/framework/sessions storage/framework/views

start_process() {
    local name="$1"
    local pid_file="$2"
    local log_file="$3"
    shift 3

    if [[ -f "${pid_file}" ]]; then
        local existing_pid
        existing_pid="$(cat "${pid_file}")"

        if kill -0 "${existing_pid}" 2>/dev/null; then
            echo "${name} is already running with PID ${existing_pid}."
            return
        fi

        rm -f "${pid_file}"
    fi

    nohup "$@" >"${log_file}" 2>&1 &
    local new_pid=$!
    echo "${new_pid}" >"${pid_file}"
    echo "Started ${name} with PID ${new_pid}."
}

php artisan optimize

start_process \
    "Laravel demo server" \
    "storage/framework/drms-demo-server.pid" \
    "storage/logs/drms-demo-server.log" \
    php artisan serve --host=0.0.0.0 --port=8000

start_process \
    "DRMS queue worker" \
    "storage/framework/drms-demo-worker.pid" \
    "storage/logs/drms-demo-worker.log" \
    php artisan queue:work --queue=notifications,default --tries=3 --timeout=90

echo "DRMS demo URL: https://${CODESPACE_NAME}-8000.app.github.dev"
