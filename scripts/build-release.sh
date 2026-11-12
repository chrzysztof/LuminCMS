#!/usr/bin/env bash
# Buduje gotową paczkę ZIP Lumin CMS (z vendor/ i zbudowanym frontendem), którą można rozpakować na hostingu
# bez Composera i Node'a. Użycie: scripts/build-release.sh [wersja]   (domyślnie: git describe lub "dev")
set -euo pipefail

cd "$(dirname "$0")/.."
VERSION="${1:-$(git describe --tags --always 2>/dev/null || echo dev)}"
NAME="lumin-cms-${VERSION#v}"
WORK="$(mktemp -d)"
OUT="$PWD/dist"
trap 'rm -rf "$WORK"' EXIT

mkdir -p "$OUT" "$WORK/$NAME"
# Tylko pliki śledzone w git: bez .env, node_modules, lokalnych baz i logów.
git ls-files -z | grep -zv -e '^tests/' -e '^\.github/' -e '^scripts/' -e '^\.git' | xargs -0 -I{} cp --parents {} "$WORK/$NAME"

cd "$WORK/$NAME"
composer install --no-dev --optimize-autoloader --no-interaction --no-progress --prefer-dist
# Composer czasem pobiera źródła z git zamiast archiwów: repozytoria nie należą do paczki.
find vendor -name .git -prune -exec rm -rf {} +

npm ci --no-audit --no-fund
npm run build
rm -rf node_modules

# Puste katalogi robocze, których wymaga Laravel.
mkdir -p storage/app/public storage/framework/{cache/data,sessions,views} storage/logs bootstrap/cache database
touch database/.gitkeep storage/logs/.gitkeep

cd "$WORK"
rm -f "$OUT/$NAME.zip"
zip -qr "$OUT/$NAME.zip" "$NAME"
echo "Gotowe: $OUT/$NAME.zip ($(du -h "$OUT/$NAME.zip" | cut -f1))"
