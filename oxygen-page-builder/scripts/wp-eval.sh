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

# MySQL socket.
#
# Autodiscovery used to be `ls .../run/*/mysql/mysqld.sock | head -1`, which picks the FIRST
# socket of ALL running Local sites. With more than one site up that silently connects
# wp-cli to the WRONG DATABASE while still using this site's WP_ROOT — the file paths are
# right, the data is someone else's. Read-only scripts report nonsense about the wrong site
# ("post 76 is an attachment"); a WRITE script edits the wrong customer's content. Verified
# 2026-08-16 with three Local sites running.
#
# So: derive the socket from THIS site's own Local config, and only fall back to a glob when
# exactly one socket exists (unambiguous). Otherwise refuse and ask.
RUN="$HOME/Library/Application Support/Local/run"
SOCK="${OXY_MYSQL_SOCK:-}"

if [ -z "$SOCK" ]; then
  # Identify THIS site's run dir from its own files. Local writes the absolute run path
  # into .envrc (and often wp-config.php), so the id is sitting right there.
  # Every pipeline below is `|| true`-guarded: under `set -euo pipefail` a grep that finds
  # nothing kills the script mid-detection, before it can explain itself.
  for probe in "$APP/.envrc" "$WP_ROOT/wp-config.php"; do
    [ -f "$probe" ] || continue
    ID="$(grep -oE '/Local/run/[A-Za-z0-9_-]+' "$probe" 2>/dev/null | head -1 || true)"
    ID="${ID##*/}"
    if [ -n "$ID" ] && [ -S "$RUN/$ID/mysql/mysqld.sock" ]; then
      SOCK="$RUN/$ID/mysql/mysqld.sock"
      break
    fi
  done
fi

if [ -z "$SOCK" ]; then
  SOCKS="$(ls "$RUN"/*/mysql/mysqld.sock 2>/dev/null || true)"
  COUNT="$(printf '%s' "$SOCKS" | grep -c . || true)"
  if [ "$COUNT" = "1" ]; then
    SOCK="$SOCKS"                       # unambiguous — one site running
  elif [ "${COUNT:-0}" -gt 1 ]; then
    echo "ERROR: $COUNT Local sites are running and this site's socket could not be identified." >&2
    echo "       Guessing would run wp-cli against the WRONG DATABASE — right files, someone" >&2
    echo "       else's data. Set it explicitly, one of:" >&2
    printf '%s\n' "$SOCKS" | sed 's/^/         OXY_MYSQL_SOCK=/' >&2
    exit 1
  fi
fi

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
