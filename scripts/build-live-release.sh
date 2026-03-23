#!/usr/bin/env bash
# Build a JunkTracker live upload bundle from the repo root.
#
# Live bundles default to the sibling folder (next to this repo):
#   .../htdocs/junktracker_live_releases/<name>/upload
# Override root: JUNKTRACKER_LIVE_RELEASE_ROOT=/path/to/parent
#
# Default: changed files only (patch / delta drop) since a git ref — use this unless
# you explicitly need the full codebase on the server.
#   ./scripts/build-live-release.sh junktracker_beta_1.3.6 v1.3.5
#   ./scripts/build-live-release.sh /custom/path/to/upload v1.3.5
# Optional keyword "delta" (same behavior):
#   ./scripts/build-live-release.sh delta junktracker_beta_1.3.6 v1.3.5
#
# Full tree (only when you need a complete upload mirror):
#   ./scripts/build-live-release.sh full junktracker_beta_1.3.6
#   ./scripts/build-live-release.sh full /custom/path/to/upload
#
# Committed work only; uncommitted changes are not included unless you commit or
# pass a different ref range.

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

RELEASE_ROOT="${JUNKTRACKER_LIVE_RELEASE_ROOT:-$ROOT/../junktracker_live_releases}"

resolve_dest() {
  local d="$1"
  if [[ "$d" == /* ]] || [[ "$d" == */* ]]; then
    printf '%s\n' "$d"
  elif [[ "$d" == . || "$d" == .. ]]; then
    printf '%s\n' "$d"
  else
    printf '%s/%s/upload\n' "${RELEASE_ROOT%/}" "$d"
  fi
}

usage() {
  echo "usage: $0 <dest> <base_git_ref>     # delta (changed files only; default)" >&2
  echo "       $0 delta <dest> <base_git_ref>  # same as above" >&2
  echo "       $0 full <dest>                # full codebase (rsync entire tree)" >&2
  echo "dest: path to upload folder, or short name (no slashes) under junktracker_live_releases" >&2
  exit 1
}

MODE=""
DEST_RAW=""
BASE_REF=""

if [[ "${1:-}" == "full" ]]; then
  MODE="full"
  DEST_RAW="${2:-}"
  if [[ -z "$DEST_RAW" ]]; then
    usage
  fi
elif [[ "${1:-}" == "delta" ]]; then
  MODE="delta"
  DEST_RAW="${2:-}"
  BASE_REF="${3:-}"
  if [[ -z "$DEST_RAW" || -z "$BASE_REF" ]]; then
    usage
  fi
elif [[ -n "${1:-}" ]]; then
  MODE="delta"
  DEST_RAW="${1:-}"
  BASE_REF="${2:-}"
  if [[ -z "$BASE_REF" ]]; then
    usage
  fi
else
  usage
fi

DEST="$(resolve_dest "$DEST_RAW")"

RSYNC_EXCLUDES=(
  --exclude='.git/'
  --exclude='.cursor/'
  --exclude='.release_tmp/'
  --exclude='config/database.local.php'
  --exclude='config/app.local.php'
  --exclude='.gitignore'
  --exclude='.vscode/'
  --exclude='.DS_Store'
  --exclude='/README.md'
  --exclude='/README.txt'
  --exclude='storage/logs/*.log'
)

full_release() {
  local dest="$1"
  mkdir -p "$dest"
  rsync -a --delete "${RSYNC_EXCLUDES[@]}" "$ROOT/" "$dest/"
}

should_skip_path() {
  local f="$1"
  case "$f" in
    .gitignore|.DS_Store|README.md|README.txt) return 0 ;;
    .vscode|.vscode/*) return 0 ;;
  esac
  return 1
}

delta_release() {
  local dest="$1"
  local base="$2"
  if [[ -z "$base" ]]; then
    echo "Delta mode requires a base ref (e.g. tag v1.3.5): $0 <dest> <base_ref>" >&2
    exit 1
  fi

  mkdir -p "$dest"
  local range="${base}...HEAD"
  local files
  files="$(git diff --name-only --diff-filter=ACMRT "$range" 2>/dev/null || true)"

  if [[ -z "${files//[$'\t\r\n']}" ]]; then
    echo "No file changes between $range (commit or check refs)." >&2
    exit 1
  fi

  local copied=0
  while IFS= read -r f; do
    [[ -z "$f" ]] && continue
    if should_skip_path "$f"; then
      continue
    fi
    if [[ ! -f "$f" ]]; then
      continue
    fi
    mkdir -p "$dest/$(dirname "$f")"
    cp -a "$f" "$dest/$f"
    copied=$((copied + 1))
  done <<< "$files"

  if [[ "$copied" -eq 0 ]]; then
    echo "No eligible files to copy after exclusions (range $range)." >&2
    exit 1
  fi

  echo "Delta bundle: $copied file(s) copied to $dest (from $range)."
}

case "$MODE" in
  full)
    full_release "$DEST"
    echo "Full upload tree -> $DEST"
    ;;
  delta)
    delta_release "$DEST" "$BASE_REF"
    ;;
  *)
    echo "Internal error: unknown mode." >&2
    exit 1
    ;;
esac
