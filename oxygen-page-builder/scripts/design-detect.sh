#!/usr/bin/env bash
# Run the impeccable design antipattern detector against a URL.
#
# Wraps the puppeteer setup that the detector needs but doesn't do itself:
# installs puppeteer into a cache dir (skipping its ~200MB Chrome download) and
# points it at the system Chrome.
#
# Usage:  design-detect.sh http://example.local/ [more urls...]
#
# TRIAGE THE OUTPUT — it reports two different kinds of finding:
#   * MECHANICAL (always real, fix them): script-error, content-hidden-at-rest,
#     low-contrast measured at rest, clipped-overflow-container where a popover
#     genuinely needs to escape.
#   * TASTE (judge against the brand manual, the brief WINS): ai-color-palette,
#     overused-font, kicker-above-heading, hero-eyebrow-chip, all-caps-body.
#     A locked brand palette or typeface is not a defect. Report these to the
#     client as questions rather than silently "fixing" them.
#
# Caveat: contrast findings labelled "opacity stack" during an entrance stagger
# are mid-animation screenshots, not defects — see PROPERTIES.md §Entrance.

set -euo pipefail

if [ $# -lt 1 ]; then
  echo "usage: design-detect.sh <url> [url...]" >&2
  exit 1
fi

CACHE="${OXY_DETECT_CACHE:-$HOME/.cache/oxygen-skill-detect}"
mkdir -p "$CACHE"

if [ ! -d "$CACHE/node_modules/puppeteer" ]; then
  echo "installing puppeteer into $CACHE (skipping Chrome download)…" >&2
  ( cd "$CACHE" && PUPPETEER_SKIP_DOWNLOAD=1 \
      npm install puppeteer --no-fund --no-audit --loglevel=error >/dev/null )
fi

# Locate a Chrome to drive; the bundled download was skipped on purpose.
if [ -z "${PUPPETEER_EXECUTABLE_PATH:-}" ]; then
  for c in "/Applications/Google Chrome.app/Contents/MacOS/Google Chrome" \
           "/Applications/Chromium.app/Contents/MacOS/Chromium" \
           "$(command -v google-chrome || true)" \
           "$(command -v chromium || true)"; do
    if [ -n "$c" ] && [ -x "$c" ]; then export PUPPETEER_EXECUTABLE_PATH="$c"; break; fi
  done
fi
if [ -z "${PUPPETEER_EXECUTABLE_PATH:-}" ]; then
  echo "No Chrome found. Install Chrome, or set PUPPETEER_EXECUTABLE_PATH." >&2
  exit 2
fi

cd "$CACHE"
status=0
for url in "$@"; do
  echo "── $url"
  npx -y impeccable detect "$url" || status=$?
done
exit $status
