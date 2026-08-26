#!/bin/sh
set -eu

cd /var/www/html

mkdir -p \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

if [ ! -f vendor/autoload.php ] && [ "${INSTALL_DEPENDENCIES_ON_START:-true}" = "true" ]; then
    composer install --no-interaction --prefer-dist
fi

if [ ! -f vendor/autoload.php ]; then
    echo "ERROR: vendor/autoload.php tidak tersedia di image." >&2
    exit 1
fi

if [ "${APP_ENV:-production}" = "production" ]; then
    require_production_secret() {
        secret_name="$1"
        secret_value="$2"

        case "$secret_value" in
            ""|GANTI_*|base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=)
                echo "ERROR: $secret_name produksi belum diatur dengan nilai unik." >&2
                exit 1
                ;;
        esac
    }

    require_production_secret "APP_KEY" "${APP_KEY:-}"
    require_production_secret "DB_PASSWORD" "${DB_PASSWORD:-}"
    require_production_secret "DB_ROOT_PASSWORD" "${DB_ROOT_PASSWORD:-}"
    require_production_secret "REDIS_PASSWORD" "${REDIS_PASSWORD:-}"

    if [ "${APP_DEBUG:-false}" != "false" ]; then
        echo "ERROR: APP_DEBUG wajib false pada production." >&2
        exit 1
    fi

    case "${APP_URL:-}" in
        https://*) ;;
        *)
            echo "ERROR: APP_URL produksi wajib menggunakan HTTPS." >&2
            exit 1
            ;;
    esac
fi

chown -R www-data:www-data storage bootstrap/cache

php artisan package:discover --ansi

if [ "${OPTIMIZE_ON_START:-false}" = "true" ]; then
    php artisan optimize
fi

if [ "${WAIT_FOR_DATABASE:-true}" = "true" ]; then
    echo "Menunggu database siap..."
    until php -r 'new PDO("mysql:host=".getenv("DB_HOST").";port=".getenv("DB_PORT").";dbname=".getenv("DB_DATABASE"), getenv("DB_USERNAME"), getenv("DB_PASSWORD"));' >/dev/null 2>&1; do
        sleep 2
    done
fi

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force
fi

php artisan storage:link >/dev/null 2>&1 || true

exec "$@"
