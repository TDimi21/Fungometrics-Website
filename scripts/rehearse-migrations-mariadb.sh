#!/bin/sh
set -eu

name="fmtrx-migration-rehearsal"
image="${FMTRX_MARIADB_IMAGE:-mariadb:10.1.48}"
port="${FMTRX_MARIADB_PORT:-43316}"
password="isolated-rehearsal-only"

command -v docker >/dev/null 2>&1 || { echo "Docker is required."; exit 1; }
docker info >/dev/null 2>&1 || { echo "Docker daemon is not running."; exit 1; }

cleanup() { docker rm -f "$name" >/dev/null 2>&1 || true; }
trap cleanup EXIT
cleanup
docker run -d --name "$name" -e MYSQL_ROOT_PASSWORD="$password" \
  -e MYSQL_DATABASE=fmtrx_migration_rehearsal -p "127.0.0.1:${port}:3306" "$image" >/dev/null

attempt=0
until docker exec "$name" mysqladmin ping -h127.0.0.1 -uroot -p"$password" --silent; do
  attempt=$((attempt + 1))
  [ "$attempt" -lt 60 ] || { docker logs "$name"; exit 1; }
  sleep 2
done
docker exec "$name" mysql -h127.0.0.1 -uroot -p"$password" \
  -Nse 'SELECT VERSION();' fmtrx_migration_rehearsal

export APP_ENV=testing
export DB_CONNECTION=mysql
export DB_HOST=127.0.0.1
export DB_PORT="$port"
export DB_DATABASE=fmtrx_migration_rehearsal
export DB_USERNAME=root
export DB_PASSWORD="$password"
export TEST_DATABASE_ALLOWLIST=fmtrx_migration_rehearsal

php artisan config:clear
php artisan migrate:fresh --force
php artisan migrate:status
php artisan migrate:rollback --step=1 --force
php artisan migrate --force
php artisan migrate:status
echo "Isolated MariaDB migration rehearsal completed."
