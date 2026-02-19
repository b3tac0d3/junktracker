#!/usr/bin/env bash
set -euo pipefail

usage() {
  cat <<'USAGE'
Usage:
  scripts/build_release_bundle.sh --version <label> [--output-root <path>] [--source <path>]

Examples:
  scripts/build_release_bundle.sh --version beta_1.1
  scripts/build_release_bundle.sh --version beta_1.2 --output-root /tmp/live_releases
USAGE
}

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SOURCE_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"
OUTPUT_ROOT="/Applications/MAMP/htdocs/junktracker_live_releases"
VERSION_LABEL=""
NON_UPLOAD_LIST_REL="release/non_upload_paths.txt"

while [[ $# -gt 0 ]]; do
  case "$1" in
    --version|-v)
      VERSION_LABEL="${2:-}"
      shift 2
      ;;
    --output-root|-o)
      OUTPUT_ROOT="${2:-}"
      shift 2
      ;;
    --source|-s)
      SOURCE_ROOT="${2:-}"
      shift 2
      ;;
    --help|-h)
      usage
      exit 0
      ;;
    *)
      echo "Unknown arg: $1" >&2
      usage
      exit 1
      ;;
  esac
done

if [[ -z "${VERSION_LABEL}" ]]; then
  echo "Error: --version is required." >&2
  usage
  exit 1
fi

if [[ ! -d "${SOURCE_ROOT}" ]]; then
  echo "Error: source path not found: ${SOURCE_ROOT}" >&2
  exit 1
fi

NON_UPLOAD_LIST="${SOURCE_ROOT}/${NON_UPLOAD_LIST_REL}"
if [[ ! -f "${NON_UPLOAD_LIST}" ]]; then
  echo "Error: non-upload list not found: ${NON_UPLOAD_LIST}" >&2
  exit 1
fi

RELEASE_DIR="${OUTPUT_ROOT}/junktracker_${VERSION_LABEL}"
UPLOAD_DIR="${RELEASE_DIR}/upload"
NON_UPLOAD_DIR="${RELEASE_DIR}/non-upload"

mkdir -p "${RELEASE_DIR}"
rm -rf "${UPLOAD_DIR}" "${NON_UPLOAD_DIR}"
mkdir -p "${UPLOAD_DIR}" "${NON_UPLOAD_DIR}"

EXCLUDES=(".git/" ".DS_Store")
NON_UPLOAD_PATHS=()
while IFS= read -r line || [[ -n "$line" ]]; do
  path="$(echo "${line}" | sed 's/[[:space:]]*$//')"
  [[ -z "${path}" ]] && continue
  [[ "${path}" =~ ^# ]] && continue
  NON_UPLOAD_PATHS+=("${path}")
  EXCLUDES+=("${path}")
done < "${NON_UPLOAD_LIST}"

RSYNC_EXCLUDE_ARGS=()
for path in "${EXCLUDES[@]}"; do
  RSYNC_EXCLUDE_ARGS+=("--exclude=${path}")
done

rsync -a --delete "${RSYNC_EXCLUDE_ARGS[@]}" "${SOURCE_ROOT}/" "${UPLOAD_DIR}/"

for rel_path in "${NON_UPLOAD_PATHS[@]}"; do
  src_path="${SOURCE_ROOT}/${rel_path}"
  dst_path="${NON_UPLOAD_DIR}/${rel_path}"

  if [[ ! -e "${src_path}" ]]; then
    echo "Warning: listed path not found, skipped: ${rel_path}" >&2
    continue
  fi

  mkdir -p "$(dirname "${dst_path}")"
  rsync -a --exclude='.DS_Store' "${src_path}" "${dst_path}"
done

(
  cd "${UPLOAD_DIR}"
  find . -type f | sort > "${RELEASE_DIR}/upload_manifest.txt"
)
(
  cd "${NON_UPLOAD_DIR}"
  find . -type f | sort > "${RELEASE_DIR}/non_upload_manifest.txt"
)

upload_count=$(wc -l < "${RELEASE_DIR}/upload_manifest.txt" | tr -d ' ')
non_upload_count=$(wc -l < "${RELEASE_DIR}/non_upload_manifest.txt" | tr -d ' ')

echo "Release build complete: ${RELEASE_DIR}"
echo "Upload files: ${upload_count}"
echo "Non-upload files: ${non_upload_count}"
echo "Upload root: ${UPLOAD_DIR}"
echo "Non-upload root: ${NON_UPLOAD_DIR}"
