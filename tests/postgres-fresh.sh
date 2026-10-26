#!/bin/sh
set -eu

# Solo tmpfs de una instancia efímera: no toca el volumen persistente de Compose.
docker-entrypoint.sh postgres > /tmp/postgres-fresh.log 2>&1 &
server_pid=$!
trap 'kill -INT "$server_pid" 2>/dev/null || true' EXIT

attempt=0
until pg_isready -h 127.0.0.1 -U postgres -d postgres > /dev/null 2>&1; do
    attempt=$((attempt + 1))
    if [ "$attempt" -ge 30 ] || ! kill -0 "$server_pid" 2>/dev/null; then
        cat /tmp/postgres-fresh.log
        exit 1
    fi
    sleep 1
done

PGPASSWORD="$POSTGRES_PASSWORD" psql -h 127.0.0.1 -U postgres -d postgres \
    -v ON_ERROR_STOP=1 -c 'SELECT version(), current_user;'
kill -INT "$server_pid"
wait "$server_pid"
trap - EXIT
