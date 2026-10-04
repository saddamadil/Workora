#!/usr/bin/env bash
# Builds an upload-ready package for shared hosting (Hostinger and similar).
#
#   scripts/build-deploy.sh [domain]       default: freelancy.saddamadil.in
#
# SQLite (default) ships a migrated database file. For MySQL/MariaDB set
#   DB_CONNECTION=mysql DB_DATABASE=name DB_USERNAME=user scripts/build-deploy.sh domain
# and import the schema from scripts/export-schema.sh into that database; the password is
# left as a placeholder in .env for you to fill in on the server.
#
# Output: dist/workora-<domain>.zip containing
#   workora/        the app, vendor/ and a migrated SQLite database. Upload NEXT TO public_html.
#   webroot/        the web root contents. Move INTO the subdomain's document root.
set -euo pipefail

DOMAIN="${1:-freelancy.saddamadil.in}"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
OUT="$ROOT/dist"
WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT

echo "==> Building the front end"
(cd "$ROOT" && npm run build >/dev/null)

echo "==> Copying the app"
mkdir -p "$WORK/workora" "$WORK/webroot"
( cd "$ROOT" && tar -cf - \
    --exclude='./.git' --exclude='./node_modules' --exclude='./dist' --exclude='./tests' \
    --exclude='./vendor' --exclude='./bootstrap/cache/*' --exclude='./.env' --exclude='./check.php' --exclude='./public' \
    --exclude='./storage/logs/*' --exclude='./storage/framework/views/*' --exclude='./database/database.sqlite' \
    . ) | tar -xf - -C "$WORK/workora"
cp -a "$ROOT/public/." "$WORK/webroot/"
rm -f "$WORK/webroot/hot"

echo "==> Installing production dependencies"
cd "$WORK/workora"
# Prefer the committed lock file; fall back to resolving for the local PHP version.
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts -q 2>/dev/null \
  || composer update --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts -q

echo "==> Trimming vendor/ (git history, tests, docs: nothing the app loads)"
find vendor -type d -name .git -prune -exec rm -rf {} + 2>/dev/null || true
find vendor -type d \( -iname tests -o -iname docs \) -prune -exec rm -rf {} + 2>/dev/null || true

echo "==> Writing production .env"
cp .env.example .env
KEY="$(php -r 'echo "base64:".base64_encode(random_bytes(32));')"
set_env() { sed -i "s|^#\? \?$1=.*|$1=$2|" .env; }
set_env APP_NAME Freelancy
set_env APP_ENV production
set_env APP_KEY "$KEY"
set_env APP_DEBUG false
set_env APP_URL "https://$DOMAIN"
set_env LOG_LEVEL warning
set_env SESSION_DRIVER database
if [ "${DB_CONNECTION:-sqlite}" = "mysql" ]; then
  set_env DB_CONNECTION mysql
  sed -i "s|^# DB_HOST=.*|DB_HOST=localhost|; s|^# DB_PORT=.*|DB_PORT=3306|; s|^# DB_DATABASE=.*|DB_DATABASE=${DB_DATABASE:?set DB_DATABASE}|; s|^# DB_USERNAME=.*|DB_USERNAME=${DB_USERNAME:?set DB_USERNAME}|; s|^# DB_PASSWORD=.*|DB_PASSWORD=PUT_YOUR_DATABASE_PASSWORD_HERE|" .env
else
  set_env DB_CONNECTION sqlite
fi
cat >> .env <<ENV
SESSION_SECURE_COOKIE=true
ENV

echo "==> Creating the database"
mkdir -p storage/framework/{cache,sessions,views} storage/logs storage/app/private bootstrap/cache
if [ "${DB_CONNECTION:-sqlite}" = "mysql" ]; then
  echo "    (MySQL: import the schema SQL into your database instead)"
else
  touch database/database.sqlite
  php artisan migrate --force --no-interaction >/dev/null
fi
php artisan package:discover --ansi >/dev/null

echo "==> Writing webroot/index.php"
cat > "$WORK/webroot/index.php" <<'PHP'
<?php

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// The app lives outside the web root. Walk up from here until the folder that
// contains "workora/" is found, so this works wherever Hostinger puts the subdomain.
$app = null;
for ($dir = __DIR__, $i = 0; $i < 6; $i++, $dir = dirname($dir)) {
    if (is_file($dir.'/workora/vendor/autoload.php')) {
        $app = $dir.'/workora';
        break;
    }
}

if ($app === null) {
    http_response_code(500);
    exit('Workora app folder not found. Upload the "workora" folder next to public_html.');
}

if (file_exists($maintenance = $app.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require $app.'/vendor/autoload.php';

$laravel = require_once $app.'/bootstrap/app.php';

// The web root (this folder) is not workora/public, so say where compiled assets live.
$laravel->usePublicPath(__DIR__);

$laravel->handleRequest(Request::capture());
PHP

chmod -R u+rwX,go+rX "$WORK"
chmod -R 775 workora/storage workora/bootstrap/cache workora/database 2>/dev/null || true

echo "==> Zipping"
cd "$WORK"
mkdir -p "$OUT"
ZIP="$OUT/workora-$DOMAIN.zip"
rm -f "$ZIP"
zip -qr "$ZIP" workora webroot
echo "Done: $ZIP ($(du -h "$ZIP" | cut -f1))"
