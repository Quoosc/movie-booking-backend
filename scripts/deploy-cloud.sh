#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
test -f .env.cloud || { echo 'Generate .env.cloud first (see docs/DEPLOY-CLOUD-DEMO.md).'; exit 1; }
compose=(docker compose --env-file .env.cloud -f compose.cloud.yaml)
"${compose[@]}" config --quiet
if [[ "${1:-}" != "--no-build" ]]; then
    "${compose[@]}" build app nginx
else
    docker image inspect cineon-backend:demo cineon-web:demo >/dev/null
fi
"${compose[@]}" up -d --wait db redis

if [[ -f demo-data.sql ]]; then
    table_count=$("${compose[@]}" exec -T db sh -c 'MYSQL_PWD="$MYSQL_PASSWORD" mysql -u "$MYSQL_USER" -D "$MYSQL_DATABASE" -Nse "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()"')
    if [[ "$table_count" == "0" ]]; then
        echo 'Importing the private local snapshot into the empty database.'
        "${compose[@]}" exec -T db sh -c 'MYSQL_PWD="$MYSQL_PASSWORD" mysql -u "$MYSQL_USER" "$MYSQL_DATABASE"' < demo-data.sql
    else
        echo 'Database already has tables; skipping snapshot import.'
    fi
fi

# Run migrations once, before HTTP traffic and background processes are started.
"${compose[@]}" run --rm --no-deps app php artisan migrate --force
if [[ -d demo-uploads ]]; then
    container=$("${compose[@]}" create app >/dev/null && "${compose[@]}" ps -aq app)
    docker cp demo-uploads/. "$container:/var/www/html/storage/app/public/"
fi
"${compose[@]}" up -d --wait --wait-timeout 180
"${compose[@]}" exec -T app php artisan locks:cleanup-expired
"${compose[@]}" exec -T app php artisan schedule:list
echo 'Containers started. Check the public HTTPS URL and run the smoke checks.'
echo 'After changing .env.cloud, run this script again; it never replaces existing data.'
