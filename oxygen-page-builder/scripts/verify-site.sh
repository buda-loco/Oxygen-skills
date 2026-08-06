#!/usr/bin/env bash
# verify-site.sh — the whole verify battery in one command.
#
# Runs, in order:
#   1. selector-store health + panel-expressibility lint + per-tree io-ts
#      invariants   (verify-site.php, one PHP process for every tree)
#   2. front-end fetch of every public page: HTTP status, alt-less <img>,
#      fake span-buttons, <h1> count, <html lang>, title/description/OG/canonical
#   3. OPTIONAL browser pass: opens each tree in the Oxygen builder and asserts
#      no "IO-TS decoding failed"   (--builder, needs puppeteer + an admin login)
#   4. OPTIONAL design antipattern detector   (--detect, wraps design-detect.sh)
#
# Usage:
#   verify-site.sh                       # every tree on the site
#   verify-site.sh 9 12 447              # only these post ids
#   verify-site.sh --builder --detect    # add the browser + taste passes
#
# Env: OXY_SITE_PATH / OXY_MYSQL_SOCK as for wp-eval.sh.
#      OXY_LOGIN_URL  a one-time admin login URL (for --builder). Mint it with
#                     the agent-connector `create-admin-login-link` ability.
#
# Exit 0 only when every check passes. Anything else is a real finding.

set -uo pipefail
HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
WP="$HERE/wp-eval.sh"

DO_BUILDER=0; DO_DETECT=0; IDS=()
for a in "$@"; do
  case "$a" in
    --builder) DO_BUILDER=1 ;;
    --detect)  DO_DETECT=1 ;;
    --help|-h) sed -n '2,22p' "$0"; exit 0 ;;
    *)         IDS+=("$a") ;;
  esac
done

FAIL=0
say()  { printf '%s\n' "$*"; }
fail() { printf '  FAIL  %s\n' "$*"; FAIL=1; }
pass() { printf '  ok    %s\n' "$*"; }

say "── 1. trees, selectors, panel lint"
OUT="$("$WP" "$HERE/verify-site.php" "${IDS[@]+"${IDS[@]}"}" 2>&1)" || { say "$OUT"; exit 2; }
printf '%s\n' "$OUT" | grep -E '^\s+(selector|lint|tree)' || true

val() { printf '%s\n' "$OUT" | grep -m1 "^$1=" | cut -d= -f2-; }
SEL_ERR="$(val SELECTOR_ERRORS)"; LINT="$(val PANEL_LINT)"
T_ALL="$(val TREES_CHECKED)";     T_BAD="$(val TREES_INVALID)"
URLS="$(val URLS)";               B_IDS="$(val BUILDER_IDS)"

[ "${SEL_ERR:-1}" = "0" ] && pass "selector store clean" || fail "$SEL_ERR selector store error(s)"
[ "${LINT:-1}"    = "0" ] && pass "panel lint clean"     || fail "$LINT panel-expressible declaration(s) in custom_css"
[ "${T_BAD:-1}"   = "0" ] && pass "$T_ALL tree(s) pass io-ts invariants" || fail "$T_BAD of $T_ALL tree(s) invalid"

say ""
say "── 2. front-end (a11y + SEO)"
TMP="$(mktemp -d)"; trap 'rm -rf "$TMP"' EXIT
for u in $URLS; do
  # Fetch to a FILE, never a shell variable piped into grep -q: `grep -q` exits
  # on first match, the writer takes SIGPIPE, and `pipefail` reports the whole
  # pipeline as failed. That made every early-in-document check (lang) look
  # missing while late ones (canonical) passed — a convincing false failure.
  CODE="$(curl -sS -o "$TMP/p.html" -w '%{http_code}' "$u" 2>/dev/null || echo 000)"
  [ "$CODE" = "200" ] || { fail "$u -> HTTP $CODE"; continue; }

  n_altless=$(grep -o '<img[^>]*>' "$TMP/p.html" | grep -vc 'alt=' || true)
  n_spanbtn=$(grep -o '<span[^>]*href=' "$TMP/p.html" | wc -l | tr -d ' ')
  n_h1=$(grep -o '<h1[ >]' "$TMP/p.html" | wc -l | tr -d ' ')

  probs=()
  [ "${n_altless:-0}" -gt 0 ] && probs+=("$n_altless alt-less img")
  [ "${n_spanbtn:-0}" -gt 0 ] && probs+=("$n_spanbtn span-button")
  [ "${n_h1:-0}" -eq 1 ] || probs+=("$n_h1 h1 (want 1)")
  grep -qE '<html[^>]*lang=' "$TMP/p.html" || probs+=("no lang")
  grep -q  'name="description"' "$TMP/p.html" || probs+=("no meta description")
  grep -q  'property="og:'      "$TMP/p.html" || probs+=("no OpenGraph")
  grep -q  'rel="canonical"'    "$TMP/p.html" || probs+=("no canonical")

  if [ ${#probs[@]} -eq 0 ]; then pass "$u"
  else fail "$u — $(IFS='|'; echo "${probs[*]}" | tr '|' ', ')"; fi
done

if [ "$DO_BUILDER" = "1" ]; then
  say ""
  say "── 3. builder (IO-TS decode)"
  if [ -z "${OXY_LOGIN_URL:-}" ]; then
    fail "set OXY_LOGIN_URL to a one-time admin login URL (agent-connector create-admin-login-link)"
  else
    SITE="$(printf '%s' "$URLS" | awk '{print $1}')"
    BASE="$(printf '%s' "$SITE" | sed -E 's#(https?://[^/]+).*#\1#')"
    node "$HERE/builder-check.mjs" "$BASE" "$OXY_LOGIN_URL" "$B_IDS" \
      && pass "every tree opens in the builder" \
      || fail "a tree failed to open in the builder (see above)"
  fi
fi

if [ "$DO_DETECT" = "1" ]; then
  say ""
  say "── 4. design antipatterns (taste findings need judging — see design-detect.sh)"
  for u in $URLS; do "$HERE/design-detect.sh" "$u" || true; done
fi

say ""
[ "$FAIL" = "0" ] && say "PASS — every check green." || say "FAIL — fix the findings above."
exit $FAIL
