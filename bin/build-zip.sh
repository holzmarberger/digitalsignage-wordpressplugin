#!/usr/bin/env bash
# Baut dist/marberger-signage.zip (ohne Tests, Doku, Entwicklungsdateien).
set -euo pipefail
cd "$(dirname "$0")/.."
VERSION=$(grep -E "^ \* Version:" marberger-signage.php | awk '{print $3}')
rm -rf dist && mkdir -p dist/marberger-signage
cp -r marberger-signage.php includes assets README.md dist/marberger-signage/
[ -f uninstall.php ] && cp uninstall.php dist/marberger-signage/
( cd dist && zip -qr "marberger-signage-${VERSION}.zip" marberger-signage )
echo "dist/marberger-signage-${VERSION}.zip"
