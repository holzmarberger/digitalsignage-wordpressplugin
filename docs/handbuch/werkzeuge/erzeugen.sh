#!/usr/bin/env bash
# Handbuch neu erzeugen: Test-WordPress, Beispieldaten, Screenshots, PDF.
#
#   ./erzeugen.sh umgebung        Test-WordPress (SQLite) aufsetzen – einmalig
#   ./erzeugen.sh server          PHP-Server auf :80 und Grafana-Attrappe auf :3000 starten
#   ./erzeugen.sh daten           Beispieldaten anlegen (nur in einer frischen Umgebung!)
#   ./erzeugen.sh bilder [Filter] Screenshots aufnehmen (Filter = regulärer Ausdruck auf Bildnamen, z. B. '^m-')
#   ./erzeugen.sh pdf [--bogen]   Handbuch und Aushang bauen (build.py)
#   ./erzeugen.sh alles           umgebung + server + daten + bilder + pdf
#
# Umgebung: WP_DIR (Testinstanz, Standard ~/hb-wp), WP_REF (WordPress-Version/Tag, Standard: neuester Stand),
#           HB_WORK (Arbeitsdateien), HB_IMG (Screenshot-Ziel, Standard ../img), HB_OUT (PDF-Ziel, Standard ../build),
#           HB_LANG_DIR (Ordner mit de_DE-.mo-Dateien, z. B. aus wp-content/languages des Intranets), CHROME.
set -euo pipefail
HERE="$(cd "$(dirname "$0")" && pwd)"
HB="$(dirname "$HERE")"
PLUGIN="$(cd "$HB/../.." && pwd)"
export WP_DIR="${WP_DIR:-$HOME/hb-wp}"
export HB_WORK="${HB_WORK:-$HB/arbeit}"
export HB_IMG="${HB_IMG:-$HB/img}"
mkdir -p "$HB_WORK" "$HB_IMG"

umgebung() {
  if [ -f "$WP_DIR/wp-load.php" ]; then echo "Testinstanz existiert bereits: $WP_DIR"; return; fi
  local tmp; tmp="$(mktemp -d)"
  echo "• WordPress ${WP_REF:-(neuester Stand)} → $WP_DIR"
  git clone -q --depth 1 ${WP_REF:+--branch "$WP_REF"} https://github.com/WordPress/WordPress.git "$WP_DIR"
  echo "• SQLite-Integration"
  git clone -q --depth 1 https://github.com/WordPress/sqlite-database-integration.git "$tmp/sqlite"
  local sq="$tmp/sqlite"; [ -d "$sq/packages/plugin-sqlite-database-integration" ] && sq="$sq/packages/plugin-sqlite-database-integration"
  cp -rL "$sq" "$WP_DIR/wp-content/plugins/sqlite-database-integration"
  rm -rf "$WP_DIR/wp-content/plugins/sqlite-database-integration/.git"
  sed -e "s#{SQLITE_IMPLEMENTATION_FOLDER_PATH}#$WP_DIR/wp-content/plugins/sqlite-database-integration#" \
      -e "s#{SQLITE_PLUGIN}#sqlite-database-integration/load.php#g" \
      "$WP_DIR/wp-content/plugins/sqlite-database-integration/db.copy" > "$WP_DIR/wp-content/db.php"
  echo "• Sprachdateien Deutsch"
  mkdir -p "$WP_DIR/wp-content/languages"
  if [ -n "${HB_LANG_DIR:-}" ]; then
    cp "$HB_LANG_DIR"/*de_DE*.mo "$WP_DIR/wp-content/languages/"
  else
    ( cd "$tmp" && apt-get download -q wordpress-l10n >/dev/null && dpkg -x wordpress-l10n_*.deb x )
    cp "$tmp"/x/usr/share/wordpress/wp-content/languages/*de_DE*.mo "$WP_DIR/wp-content/languages/"
  fi
  echo "• wp-config.php, Kosmetik für Screenshots, Plugin verlinken"
  php -r '
    $s = ""; foreach (["AUTH_KEY","SECURE_AUTH_KEY","LOGGED_IN_KEY","NONCE_KEY","AUTH_SALT","SECURE_AUTH_SALT","LOGGED_IN_SALT","NONCE_SALT"] as $k) { $s .= "define(\x27$k\x27, \x27" . bin2hex(random_bytes(32)) . "\x27);\n"; }
    echo "<?php\n" . $s . <<<CFG
define("DB_NAME", "wp"); define("DB_USER", "x"); define("DB_PASSWORD", "x"); define("DB_HOST", "localhost");
define("DB_CHARSET", "utf8"); define("DB_COLLATE", "");
define("DB_DIR", __DIR__ . "/wp-content/database/"); define("DB_FILE", ".ht.sqlite");
define("WP_HTTP_BLOCK_EXTERNAL", true); define("AUTOMATIC_UPDATER_DISABLED", true); define("DISABLE_WP_CRON", true);
\$table_prefix = "wp_";
define("WP_DEBUG", true); define("WP_DEBUG_LOG", __DIR__ . "/debug.log"); define("WP_DEBUG_DISPLAY", false);
define("WP_HOME", "http://localhost"); define("WP_SITEURL", "http://localhost");
if (!defined("ABSPATH")) define("ABSPATH", __DIR__ . "/");
require_once ABSPATH . "wp-settings.php";
CFG;
  ' > "$WP_DIR/wp-config.php"
  mkdir -p "$WP_DIR/wp-content/mu-plugins"
  cp "$HERE/mu-plugin/hb-cosmetics.php" "$WP_DIR/wp-content/mu-plugins/"
  ln -sfn "$PLUGIN" "$WP_DIR/wp-content/plugins/marberger-signage"
  php "$HERE/php/install.php"
  ( cd "$HERE" && npm install --silent --no-fund --no-audit )
  rm -rf "$tmp"
}

server() {
  pkill -f "php -S 0.0.0.0:80 " 2>/dev/null || true
  pkill -f "php -S 0.0.0.0:3000 " 2>/dev/null || true
  sleep 0.5
  ( cd "$WP_DIR" && PHP_CLI_SERVER_WORKERS=6 nohup php -S 0.0.0.0:80 -t "$WP_DIR" >"$HB_WORK/server.log" 2>&1 & )
  ( cd "$HERE/grafana" && nohup php -S 0.0.0.0:3000 router.php >"$HB_WORK/grafana.log" 2>&1 & )
  for _ in $(seq 1 20); do curl -s -o /dev/null http://localhost/wp-login.php && break; sleep 0.5; done
  curl -s -o /dev/null -w "• Intranet http://localhost → %{http_code}\n" http://localhost/wp-login.php
  curl -s -o /dev/null -w "• Grafana-Attrappe http://localhost:3000/d/zuschnitt → %{http_code}\n" http://localhost:3000/d/zuschnitt
}

daten() {
  if [ -f "$HB_WORK/ids.json" ]; then echo "Beispieldaten sind schon angelegt ($HB_WORK/ids.json). Für einen Neustart Testinstanz und Arbeitsordner löschen."; exit 1; fi
  php "$HERE/php/opts.php"
  php "$HERE/php/media.php"
  node "$HERE/seed.js"
  php "$HERE/php/extra.php"
  php "$HERE/php/redate.php"
}

bilder() {
  node "$HERE/shots.js" "$@"
  pngquant --force --skip-if-larger --quality=70-95 --ext .png "$HB_IMG"/*.png || true
}

case "${1:-}" in
  umgebung) umgebung ;;
  server) server ;;
  daten) daten ;;
  bilder) shift; bilder "$@" ;;
  pdf) shift; python3 "$HB/build.py" "$@" ;;
  alles) umgebung; server; daten; bilder; python3 "$HB/build.py" --bogen ;;
  *) sed -n '2,15p' "$0"; exit 1 ;;
esac
