#!/usr/bin/env bash
# Build a JunkTracker live upload bundle from the repo root.
#
# Live bundles default to the sibling folder (next to this repo):
#   .../htdocs/junktracker_live_releases/<name>/upload
# Override root: JUNKTRACKER_LIVE_RELEASE_ROOT=/path/to/parent
#
# Full tree (major drops) — dest can be a path to the upload folder, or a short
# name (no slashes) that becomes <live_releases_root>/<name>/upload:
#   ./scripts/build-live-release.sh full junktracker_beta_1.3.6
#   ./scripts/build-live-release.sh full /custom/path/to/upload
#
# Changed files only since a git ref (patch drops):
#   ./scripts/build-live-release.sh delta junktracker_beta_1.3.6 v1.3.5
#   ./scripts/build-live-release.sh delta /custom/path/to/upload v1.3.5
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

MODE="${1:?usage: $0 full|delta <dest> [base_ref]}"
DEST="${2:?usage: $0 $MODE <dest> [base_ref]}"
BASE_REF="${3:-}"

DEST="$(resolve_dest "$DEST")"

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
    echo "delta mode requires a base ref (e.g. tag v1.3.2): $0 delta <dest> <base_ref>" >&2
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
    echo "First argument must be 'full' or 'delta'." >&2
    exit 1
    ;;
esac
