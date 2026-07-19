#!/usr/bin/env bash
#
# Static Mirror / oxygen-page-builder — one-way sync FROM the source of truth.
#
# Source of truth (SoT) = this skill repo (Dropbox, pushed to
# github.com/buda-loco/Oxygen-skills). Edit the skill HERE, then run this to push
# the changes down to every deployed copy. Never edit the copies directly.
#
# Portable by design — safe to run from any machine or Claude Code session:
#   * SoT is derived from this script's own location (no hard-coded Dropbox path)
#   * the global skill target uses $HOME
#   * WordPress plugin deployments come from a machine-specific, gitignored file
#     (scripts/sync-targets.local), so the SoT itself stays de-branded.
#
# Usage:  bash scripts/sync-copies.sh [--dry-run]
#
set -euo pipefail

DRY=0
[ "${1:-}" = "--dry-run" ] && DRY=1
run() { if [ "$DRY" = 1 ]; then echo "  [dry-run] $*"; else "$@"; fi; }

# --- source of truth = the skill dir this script lives in (scripts/..) ---
SOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
echo "Source of truth: $SOT"
[ "$DRY" = 1 ] && echo "(dry run — nothing will be written)"

EXCLUDES=(--exclude='.git' --exclude='.DS_Store' --exclude='scripts/sync-targets.local')

# --- 1. GLOBAL SKILL — auto-loaded by every Claude Code session on this machine ---
GLOBAL="$HOME/.claude/skills/oxygen-page-builder"
if [ -d "$HOME/.claude/skills" ]; then
  run mkdir -p "$GLOBAL"
  if [ "$DRY" = 1 ]; then
    rsync -rin --delete "${EXCLUDES[@]}" "$SOT/" "$GLOBAL/" | sed 's/^/    /'
  else
    rsync -a --delete "${EXCLUDES[@]}" "$SOT/" "$GLOBAL/"
  fi
  echo "  OK  global skill  <-  SoT   ($GLOBAL)"
else
  echo "  --  no ~/.claude/skills on this machine; skipping global skill"
fi

# --- 2. WORDPRESS PLUGIN DEPLOYMENTS — machine-specific, gitignored list ---
# scripts/sync-targets.local: one deployment per line
#     /abs/path/to/wp-content/plugins/static-mirror|brand-domain.com
#   The brand domain is OPTIONAL; when present, only the cosmetic Production-URL
#   placeholder is re-branded (SoT stays example.com). CLAUDE.md and any other
#   files already in the plugin dir are left untouched.
TARGETS="$SOT/scripts/sync-targets.local"
if [ -f "$TARGETS" ]; then
  while IFS='|' read -r dir brand || [ -n "$dir" ]; do
    dir="$(echo "$dir" | sed 's/^[[:space:]]*//;s/[[:space:]]*$//')"
    brand="$(echo "${brand:-}" | sed 's/^[[:space:]]*//;s/[[:space:]]*$//')"
    [ -z "$dir" ] && continue
    case "$dir" in \#*) continue;; esac
    if [ ! -d "$dir" ]; then echo "  --  plugin target missing, skipping: $dir"; continue; fi
    run cp "$SOT/scripts/examples/static-mirror.php"        "$dir/static-mirror.php"
    run cp "$SOT/scripts/examples/static-mirror-search.js"  "$dir/static-search.js"
    if [ -n "$brand" ]; then
      run perl -0pi -e "s{placeholder=\"https://example\\.com\"}{placeholder=\"https://$brand\"}g" "$dir/static-mirror.php"
    fi
    echo "  OK  plugin        <-  SoT   ($dir${brand:+   [brand: $brand]})"
  done < "$TARGETS"
else
  echo "  --  no scripts/sync-targets.local; skipping plugin deploys"
  echo "      create it with lines like:"
  echo "      /Users/you/.../wp-content/plugins/static-mirror|yourdomain.com"
fi

echo "Done."
