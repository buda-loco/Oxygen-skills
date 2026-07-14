#!/bin/bash
# wp-eval.sh — run wp-cli against a WordPress + Oxygen site from any plain shell.
#
# Handy with "Local" (by WP Engine), whose PHP/MySQL are NOT on the default PATH/socket, but works
# for any setup: point it at a site and (optionally) a mysqld socket.
#
# Configure via env vars (or edit the defaults at the top):
#   OXY_SITE_PATH   REQUIRED. Absolute path to the site's app dir that contains `public/`
#                   (a Local site) — or set it to the WordPress root and adjust WP_ROOT below.
#   OXY_MYSQL_SOCK  Optional. Path to mysqld.sock. If unset, we try to autodiscover a Local socket.
#   OXY_WP_PHAR     Optional. Path to wp-cli.phar (defaults to Local's bundled one, else `wp` on PATH).
#   OXY_ENVRC       Optional. An env file to source before running (Local writes one at $OXY_SITE_PATH/.envrc).
#
# Usage:
#   ./wp-eval.sh <script.php> [args...]   # -> wp eval-file <script.php> [args]  (args land in $args)
#   ./wp-eval.sh -- <any wp args...>      # -> wp <args>, e.g. ./wp-eval.sh -- post list
set -euo pipefail

APP="${OXY_SITE_PATH:-}"
[ -n "$APP" ] || { echo "ERROR: set OXY_SITE_PATH to your site's app dir (the one containing public/)." >&2; exit 1; }

WP_ROOT="$APP/public"
[ -d "$WP_ROOT" ] || WP_ROOT="$APP"   # fall back to APP if there's no public/ (non-Local layout)

# MySQL socket: use OXY_MYSQL_SOCK, else try to autodiscover a Local socket (harmless if not present).
SOCK="${OXY_MYSQL_SOCK:-$(ls "$HOME/Library/Application Support/Local/run/"*/mysql/mysqld.sock 2>/dev/null | head -1 || true)}"

# wp-cli: OXY_WP_PHAR, else Local's bundled phar, else `wp` on PATH.
PHAR="${OXY_WP_PHAR:-/Applications/Local.app/Contents/Resources/extraResources/bin/wp-cli/wp-cli.phar}"
if [ ! -f "$PHAR" ]; then command -v wp >/dev/null && PHAR="$(command -v wp)" || { echo "ERROR: no wp-cli found. Set OXY_WP_PHAR or install wp." >&2; exit 1; }; fi

# Source an env file if present (Local generates $APP/.envrc to put the site's PHP on PATH).
ENVRC="${OXY_ENVRC:-$APP/.envrc}"
[ -f "$ENVRC" ] && { set +u; source "$ENVRC"; set -u; }

PHP_SOCK_OPTS=()
if [ -n "$SOCK" ] && [ -S "$SOCK" ]; then
  PHP_SOCK_OPTS=(-d "mysqli.default_socket=$SOCK" -d "pdo_mysql.default_socket=$SOCK")
fi

CALLER_PWD="$PWD"
cd "$WP_ROOT"
if [ "${1:-}" = "--" ]; then
  shift
  exec php "${PHP_SOCK_OPTS[@]}" "$PHAR" "$@"
else
  SCRIPT="$1"; shift
  case "$SCRIPT" in /*) ;; *) SCRIPT="$CALLER_PWD/$SCRIPT" ;; esac   # resolve relative to caller
  exec php "${PHP_SOCK_OPTS[@]}" "$PHAR" eval-file "$SCRIPT" "$@"
fi
