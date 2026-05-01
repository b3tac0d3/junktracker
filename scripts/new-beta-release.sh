#!/usr/bin/env bash
# Prepare a new beta-live release scaffold in one command.
#
# Usage:
#   ./scripts/new-beta-release.sh 1.8.3-beta "short summary"
#
# What it does:
# - bumps config/app.php version
# - creates docs/releases/live-<version>.md (if missing)
# - updates docs/deploy-checklist.md latest/earlier links

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

VERSION="${1:-}"
SUMMARY="${2:-beta-live release updates}"

if [[ -z "$VERSION" ]]; then
  echo "Usage: $0 <version-like-1.8.3-beta> [summary]" >&2
  exit 1
fi

if [[ ! "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+(\.[0-9]+)?-beta$ ]]; then
  echo "Invalid version format: $VERSION" >&2
  echo "Expected: <major>.<minor>.<patch>-beta or <major>.<minor>.<patch>.<hotfix>-beta (examples: 1.8.3-beta, 1.8.2.1-beta)" >&2
  exit 1
fi

CONFIG_FILE="$ROOT/config/app.php"
CHECKLIST_FILE="$ROOT/docs/deploy-checklist.md"
RELEASE_FILE="$ROOT/docs/releases/live-${VERSION}.md"
TODAY="$(date +%F)"

python3 - "$CONFIG_FILE" "$VERSION" <<'PY'
import re
import sys

path = sys.argv[1]
version = sys.argv[2]

with open(path, "r", encoding="utf-8") as f:
    content = f.read()

updated, n = re.subn(
    r"('version'\s*=>\s*')([^']+)(')",
    r"\g<1>" + version + r"\g<3>",
    content,
    count=1,
)

if n != 1:
    raise SystemExit(f"Could not update version in {path}")

with open(path, "w", encoding="utf-8") as f:
    f.write(updated)
PY

if [[ ! -f "$RELEASE_FILE" ]]; then
  cat > "$RELEASE_FILE" <<EOF
# Live Release ${VERSION}

Date: ${TODAY}

## Highlights

- ${SUMMARY}

## Database migrations in this release

No new database migrations are required for ${VERSION}.

## Ops notes

- Verify core flow changes included in this release.
EOF
fi

python3 - "$CHECKLIST_FILE" "$VERSION" "$SUMMARY" <<'PY'
import re
import sys

path = sys.argv[1]
version = sys.argv[2]
summary = sys.argv[3]

with open(path, "r", encoding="utf-8") as f:
    lines = f.readlines()

latest_idx = None
earlier_idx = None
for i, line in enumerate(lines):
    if line.startswith("**Latest release notes:**"):
        latest_idx = i
    if line.startswith("**Earlier:**"):
        earlier_idx = i

if latest_idx is None or earlier_idx is None:
    raise SystemExit(f"Could not find Latest/Earlier lines in {path}")

latest_line = lines[latest_idx].rstrip("\n")
match = re.search(r"\[releases/(live-[^\]]+\.md)\]", latest_line)
previous_latest = match.group(1) if match else None

new_latest = (
    f"**Latest release notes:** [releases/live-{version}.md]"
    f"(./releases/live-{version}.md) ({summary}).  \n"
)
lines[latest_idx] = new_latest

earlier_line = lines[earlier_idx].rstrip("\n")
prefix = "**Earlier:** "
existing = earlier_line[len(prefix):] if earlier_line.startswith(prefix) else earlier_line
existing = existing.strip()

new_prev_link = f"[releases/{previous_latest}](./releases/{previous_latest})" if previous_latest else ""
if new_prev_link:
    if existing == "":
        combined = new_prev_link + "."
    elif new_prev_link in existing:
        combined = existing
    else:
        combined = f"{new_prev_link}, {existing}"
else:
    combined = existing if existing else "—"
if not combined.endswith("."):
    combined += "."

lines[earlier_idx] = f"{prefix}{combined}\n"

with open(path, "w", encoding="utf-8") as f:
    f.writelines(lines)
PY

echo "Prepared beta release scaffold:"
echo "  - version: $VERSION"
echo "  - release notes: docs/releases/live-${VERSION}.md"
echo "  - checklist latest link updated"
echo
echo "Next steps:"
echo "  1) Review/edit release notes"
echo "  2) git add . && git commit -m \"Release ${VERSION}: <summary>\""
echo "  3) ./scripts/build-live-release.sh junktracker_beta_${VERSION} <previous_ref>"
