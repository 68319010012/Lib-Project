#!/usr/bin/env bash
# Dumps the current library_checkin database (schema + real data, all 4
# tables) to a single .sql file — used to move the local XAMPP dataset to a
# fresh Coolify MySQL instance (unlike schema.sql, which is structure only).
# Run this FROM the BackEnd/ directory (same place .env and schema.sql live).
set -euo pipefail

ENV_FILE=".env"
if [ ! -f "$ENV_FILE" ]; then
  echo "No .env found in the current directory — run this from BackEnd/ (see NEW_COMPUTER_SETUP.md)." >&2
  exit 1
fi

# Reads DB_HOST/DB_USER/DB_PASSWORD/DB_NAME from the same .env db.py/app.py
# already use, so credentials aren't duplicated/hardcoded in this script.
set -a
source "$ENV_FILE"
set +a

DB_HOST="${DB_HOST:-localhost}"
DB_USER="${DB_USER:-root}"
DB_PASSWORD="${DB_PASSWORD:-}"
DB_NAME="${DB_NAME:-library_checkin}"

OUT_FILE="${1:-library_checkin_$(date +%Y%m%d_%H%M%S).sql}"

# XAMPP's mysqldump usually isn't on PATH on Windows — fall back to its
# default install location if the plain command isn't found.
MYSQLDUMP_BIN="mysqldump"
if ! command -v "$MYSQLDUMP_BIN" >/dev/null 2>&1; then
  if [ -x "/c/xampp/mysql/bin/mysqldump.exe" ]; then
    MYSQLDUMP_BIN="/c/xampp/mysql/bin/mysqldump.exe"
  else
    echo "mysqldump not found on PATH or at the default XAMPP install path." >&2
    exit 1
  fi
fi

# --no-tablespaces: avoids needing the PROCESS privilege (dev's root user has
#   it, but there's no reason to require it — keeps this portable).
# --single-transaction: consistent snapshot without locking the tables while
#   dumping (safe here — all 4 tables are InnoDB).
# --default-character-set=utf8mb4: this data is full of Thai text (names,
#   department names, prefixes) — force the same charset the tables use so
#   nothing gets mis-encoded in the dump (same reasoning as the UTF-8 BOM
#   handling in app.py's CSV export).
# Explicit table list (not the whole database) so this only ever touches the
# 4 tables this project defines, even if stray tables ever show up locally.
MYSQLDUMP_ARGS=(--no-tablespaces --single-transaction --default-character-set=utf8mb4 -h "$DB_HOST" -u "$DB_USER")
if [ -n "$DB_PASSWORD" ]; then
  MYSQLDUMP_ARGS+=(-p"$DB_PASSWORD")
fi

"$MYSQLDUMP_BIN" "${MYSQLDUMP_ARGS[@]}" "$DB_NAME" students users checkin_logs announcements > "$OUT_FILE"

echo "Wrote $OUT_FILE ($(wc -l < "$OUT_FILE") lines)."
echo "This file contains real student PII (names, student IDs, ...) — never commit it to git,"
echo "and delete it once the import into Coolify's MySQL is confirmed working (see COOLIFY_DEPLOY.md)."
