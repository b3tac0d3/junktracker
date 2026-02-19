#!/usr/bin/env bash
set -euo pipefail

usage() {
  cat <<'USAGE'
Usage:
  scripts/build_release_bundle.sh --version <label> [options]

Examples:
  scripts/build_release_bundle.sh --version beta_1.1
  scripts/build_release_bundle.sh --version beta_1.2 --output-root /tmp/live_releases
  scripts/build_release_bundle.sh --version beta_1.3.1_patch --mode delta --from origin/main
  JT_LIVE_DB_HOST=127.0.0.1 JT_LIVE_DB_NAME=prod_db JT_LIVE_DB_USER=prod_user JT_LIVE_DB_PASS=secret \
    scripts/build_release_bundle.sh --version beta_1.3.1 --profile live

Options:
  --version, -v           Release label (required)
  --output-root, -o       Release output root (default: /Applications/MAMP/htdocs/junktracker_live_releases)
  --source, -s            Source repo root (default: current repo root)
  --mode                  full | delta (default: full)
  --from                  Base git ref for delta mode (required when --mode delta)
  --to                    Target git ref for delta mode (default: HEAD)
  --profile               local | live (default: local)
  --live-db-host          Live DB host (or env JT_LIVE_DB_HOST)
  --live-db-port          Live DB port (or env JT_LIVE_DB_PORT; default 3306)
  --live-db-name          Live DB name (or env JT_LIVE_DB_NAME)
  --live-db-user          Live DB username (or env JT_LIVE_DB_USER)
  --live-db-pass          Live DB password (or env JT_LIVE_DB_PASS)
  --live-db-charset       Live DB charset (or env JT_LIVE_DB_CHARSET; default utf8mb4)
USAGE
}

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SOURCE_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"
OUTPUT_ROOT="/Applications/MAMP/htdocs/junktracker_live_releases"
VERSION_LABEL=""
NON_UPLOAD_LIST_REL="release/non_upload_paths.txt"
MODE="full"
FROM_REF=""
TO_REF="HEAD"
PROFILE="local"
LIVE_DB_HOST="${JT_LIVE_DB_HOST:-}"
LIVE_DB_PORT="${JT_LIVE_DB_PORT:-3306}"
LIVE_DB_NAME="${JT_LIVE_DB_NAME:-}"
LIVE_DB_USER="${JT_LIVE_DB_USER:-}"
LIVE_DB_PASS="${JT_LIVE_DB_PASS:-}"
LIVE_DB_CHARSET="${JT_LIVE_DB_CHARSET:-utf8mb4}"

TMP_INCLUDE_RAW=""
TMP_DELETE_RAW=""
TMP_INCLUDE_LIST=""

cleanup_tmp() {
  if [[ -n "${TMP_INCLUDE_RAW}" && -f "${TMP_INCLUDE_RAW}" ]]; then
    rm -f "${TMP_INCLUDE_RAW}"
  fi
  if [[ -n "${TMP_DELETE_RAW}" && -f "${TMP_DELETE_RAW}" ]]; then
    rm -f "${TMP_DELETE_RAW}"
  fi
  if [[ -n "${TMP_INCLUDE_LIST}" && -f "${TMP_INCLUDE_LIST}" ]]; then
    rm -f "${TMP_INCLUDE_LIST}"
  fi
  return 0
}
trap cleanup_tmp EXIT

php_escape() {
  local value="${1:-}"
  value="${value//\\/\\\\}"
  value="${value//\'/\\\'}"
  printf '%s' "${value}"
}

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
    --mode)
      MODE="${2:-}"
      shift 2
      ;;
    --from)
      FROM_REF="${2:-}"
      shift 2
      ;;
    --to)
      TO_REF="${2:-}"
      shift 2
      ;;
    --profile)
      PROFILE="${2:-}"
      shift 2
      ;;
    --live-db-host)
      LIVE_DB_HOST="${2:-}"
      shift 2
      ;;
    --live-db-port)
      LIVE_DB_PORT="${2:-}"
      shift 2
      ;;
    --live-db-name)
      LIVE_DB_NAME="${2:-}"
      shift 2
      ;;
    --live-db-user)
      LIVE_DB_USER="${2:-}"
      shift 2
      ;;
    --live-db-pass)
      LIVE_DB_PASS="${2:-}"
      shift 2
      ;;
    --live-db-charset)
      LIVE_DB_CHARSET="${2:-}"
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

if [[ "${MODE}" != "full" && "${MODE}" != "delta" ]]; then
  echo "Error: --mode must be 'full' or 'delta'." >&2
  exit 1
fi

if [[ "${PROFILE}" != "local" && "${PROFILE}" != "live" ]]; then
  echo "Error: --profile must be 'local' or 'live'." >&2
  exit 1
fi

if [[ "${MODE}" == "delta" && -z "${FROM_REF}" ]]; then
  echo "Error: --from is required when --mode delta." >&2
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
DELETED_PATHS_FILE="${RELEASE_DIR}/deleted_paths.txt"

mkdir -p "${RELEASE_DIR}"
rm -rf "${UPLOAD_DIR}" "${NON_UPLOAD_DIR}"
mkdir -p "${UPLOAD_DIR}" "${NON_UPLOAD_DIR}"
: > "${DELETED_PATHS_FILE}"

EXCLUDES=(".git" ".DS_Store")
NON_UPLOAD_PATHS=()
while IFS= read -r line || [[ -n "$line" ]]; do
  path="$(echo "${line}" | sed 's/[[:space:]]*$//')"
  [[ -z "${path}" ]] && continue
  [[ "${path}" =~ ^# ]] && continue
  normalized="${path%/}"
  NON_UPLOAD_PATHS+=("${normalized}")
  EXCLUDES+=("${normalized}")
done < "${NON_UPLOAD_LIST}"

is_non_upload_path() {
  local rel_path="$1"
  local excluded
  for excluded in "${NON_UPLOAD_PATHS[@]}"; do
    if [[ "${rel_path}" == "${excluded}" || "${rel_path}" == "${excluded}/"* ]]; then
      return 0
    fi
  done
  return 1
}

RSYNC_EXCLUDE_ARGS=()
for path in "${EXCLUDES[@]}"; do
  RSYNC_EXCLUDE_ARGS+=("--exclude=${path}")
  RSYNC_EXCLUDE_ARGS+=("--exclude=${path}/")
done

if [[ "${MODE}" == "full" ]]; then
  rsync -a --delete "${RSYNC_EXCLUDE_ARGS[@]}" "${SOURCE_ROOT}/" "${UPLOAD_DIR}/"
else
  if ! git -C "${SOURCE_ROOT}" rev-parse --is-inside-work-tree >/dev/null 2>&1; then
    echo "Error: delta mode requires SOURCE_ROOT to be a git repository." >&2
    exit 1
  fi

  if ! git -C "${SOURCE_ROOT}" rev-parse --verify "${FROM_REF}" >/dev/null 2>&1; then
    echo "Error: invalid --from git ref: ${FROM_REF}" >&2
    exit 1
  fi
  if ! git -C "${SOURCE_ROOT}" rev-parse --verify "${TO_REF}" >/dev/null 2>&1; then
    echo "Error: invalid --to git ref: ${TO_REF}" >&2
    exit 1
  fi

  TMP_INCLUDE_RAW="$(mktemp)"
  TMP_DELETE_RAW="$(mktemp)"
  TMP_INCLUDE_LIST="$(mktemp)"

  while IFS=$'\t' read -r status path_a path_b; do
    [[ -z "${status}" ]] && continue

    case "${status}" in
      R*|C*)
        old_path="${path_a:-}"
        new_path="${path_b:-}"

        if [[ -n "${new_path}" ]] && ! is_non_upload_path "${new_path}" && [[ -e "${SOURCE_ROOT}/${new_path}" ]] && [[ ! -d "${SOURCE_ROOT}/${new_path}" ]]; then
          printf '%s\n' "${new_path}" >> "${TMP_INCLUDE_RAW}"
        fi
        if [[ "${status}" == R* ]] && [[ -n "${old_path}" ]] && [[ "${old_path}" != "${new_path}" ]] && ! is_non_upload_path "${old_path}"; then
          printf '%s\n' "${old_path}" >> "${TMP_DELETE_RAW}"
        fi
        ;;
      D*)
        deleted_path="${path_a:-}"
        if [[ -n "${deleted_path}" ]] && ! is_non_upload_path "${deleted_path}"; then
          printf '%s\n' "${deleted_path}" >> "${TMP_DELETE_RAW}"
        fi
        ;;
      *)
        changed_path="${path_a:-}"
        if [[ -n "${changed_path}" ]] && ! is_non_upload_path "${changed_path}" && [[ -e "${SOURCE_ROOT}/${changed_path}" ]] && [[ ! -d "${SOURCE_ROOT}/${changed_path}" ]]; then
          printf '%s\n' "${changed_path}" >> "${TMP_INCLUDE_RAW}"
        fi
        ;;
    esac
  done < <(git -C "${SOURCE_ROOT}" diff --name-status --find-renames --diff-filter=ACMRD "${FROM_REF}" "${TO_REF}")

  if [[ -s "${TMP_INCLUDE_RAW}" ]]; then
    sort -u "${TMP_INCLUDE_RAW}" > "${TMP_INCLUDE_LIST}"
    rsync -a --files-from="${TMP_INCLUDE_LIST}" "${SOURCE_ROOT}/" "${UPLOAD_DIR}/"
  fi
  if [[ -s "${TMP_DELETE_RAW}" ]]; then
    sort -u "${TMP_DELETE_RAW}" > "${DELETED_PATHS_FILE}"
  fi
fi

if [[ "${PROFILE}" == "live" ]]; then
  any_live_db_set=0
  [[ -n "${LIVE_DB_HOST}" ]] && any_live_db_set=1
  [[ -n "${LIVE_DB_NAME}" ]] && any_live_db_set=1
  [[ -n "${LIVE_DB_USER}" ]] && any_live_db_set=1
  [[ -n "${LIVE_DB_PASS}" ]] && any_live_db_set=1

  if [[ "${any_live_db_set}" -eq 1 ]]; then
    missing=()
    [[ -z "${LIVE_DB_HOST}" ]] && missing+=("host")
    [[ -z "${LIVE_DB_NAME}" ]] && missing+=("database")
    [[ -z "${LIVE_DB_USER}" ]] && missing+=("username")
    [[ -z "${LIVE_DB_PASS}" ]] && missing+=("password")

    if [[ "${#missing[@]}" -gt 0 ]]; then
      echo "Error: missing live DB values for: ${missing[*]}" >&2
      exit 1
    fi
    if ! [[ "${LIVE_DB_PORT}" =~ ^[0-9]+$ ]]; then
      echo "Error: live DB port must be numeric (got: ${LIVE_DB_PORT})." >&2
      exit 1
    fi

    mkdir -p "${UPLOAD_DIR}/config"
    cat > "${UPLOAD_DIR}/config/database.local.php" <<PHP
<?php

declare(strict_types=1);

return [
    'driver' => 'mysql',
    'host' => '$(php_escape "${LIVE_DB_HOST}")',
    'port' => ${LIVE_DB_PORT},
    'database' => '$(php_escape "${LIVE_DB_NAME}")',
    'username' => '$(php_escape "${LIVE_DB_USER}")',
    'password' => '$(php_escape "${LIVE_DB_PASS}")',
    'charset' => '$(php_escape "${LIVE_DB_CHARSET}")',
];
PHP
    echo "Generated upload/config/database.local.php from live DB settings."
  else
    echo "Warning: profile=live but no live DB vars supplied; database.local.php was not generated." >&2
  fi
fi

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

{
  echo "version=${VERSION_LABEL}"
  echo "mode=${MODE}"
  echo "profile=${PROFILE}"
  echo "built_at=$(date '+%Y-%m-%d %H:%M:%S')"
  if git -C "${SOURCE_ROOT}" rev-parse --is-inside-work-tree >/dev/null 2>&1; then
    echo "source_commit=$(git -C "${SOURCE_ROOT}" rev-parse --short "${TO_REF}" 2>/dev/null || git -C "${SOURCE_ROOT}" rev-parse --short HEAD)"
  fi
  if [[ "${MODE}" == "delta" ]]; then
    echo "from_ref=${FROM_REF}"
    echo "to_ref=${TO_REF}"
  fi
} > "${RELEASE_DIR}/release_meta.txt"

upload_count=$(wc -l < "${RELEASE_DIR}/upload_manifest.txt" | tr -d ' ')
non_upload_count=$(wc -l < "${RELEASE_DIR}/non_upload_manifest.txt" | tr -d ' ')
deleted_count=0
if [[ -s "${DELETED_PATHS_FILE}" ]]; then
  deleted_count=$(wc -l < "${DELETED_PATHS_FILE}" | tr -d ' ')
fi

echo "Release build complete: ${RELEASE_DIR}"
echo "Upload files: ${upload_count}"
echo "Non-upload files: ${non_upload_count}"
echo "Deleted paths list: ${DELETED_PATHS_FILE} (${deleted_count})"
echo "Upload root: ${UPLOAD_DIR}"
echo "Non-upload root: ${NON_UPLOAD_DIR}"
