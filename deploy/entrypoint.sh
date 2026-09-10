#!/bin/sh
set -eu

cd /var/www/html

mkdir -p storage/app/public storage/app/private storage/framework/cache/data \
    storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

if [ "${WAIT_FOR_DB:-1}" = "1" ]; then
    echo "Waiting for MySQL at ${DB_HOST:-mysql}..."
    i=0
    until php -r '
        $h = getenv("DB_HOST") ?: "mysql";
        $d = getenv("DB_DATABASE") ?: "hotel_hub";
        $u = getenv("DB_USERNAME") ?: "hotel";
        $p = getenv("DB_PASSWORD") ?: "";
        try {
            new PDO("mysql:host={$h};dbname={$d}", $u, $p, [PDO::ATTR_TIMEOUT => 3]);
            exit(0);
        } catch (Throwable $e) {
            exit(1);
        }
    '; do
        i=$((i + 1))
        if [ "$i" -ge 60 ]; then
            echo "MySQL did not become ready." >&2
            exit 1
        fi
        sleep 2
    done
fi

if [ -z "${APP_KEY:-}" ]; then
    echo "APP_KEY trống. Chạy ./setup.sh trên host trước." >&2
    exit 1
fi

php artisan package:discover --ansi >/dev/null 2>&1 || true
php artisan storage:link --force >/dev/null 2>&1 || true

if [ "${RUN_MIGRATIONS:-0}" = "1" ]; then
    php artisan migrate --force
fi

exec "$@"
