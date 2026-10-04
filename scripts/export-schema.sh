#!/usr/bin/env bash
# Writes dist/workora-schema.sql: every table, ready to import into an EMPTY MySQL/MariaDB
# database with phpMyAdmin (Import tab). One statement per line, because phpMyAdmin can split
# multi-line statements in the wrong place. Includes DROP TABLE IF EXISTS so it can be re-run.
#
# Needs a local MariaDB/MySQL server and a throwaway database:
#   DB_DATABASE=scratch DB_USERNAME=root DB_PASSWORD=secret scripts/export-schema.sh
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
: "${DB_DATABASE:?set DB_DATABASE (a scratch database; it is wiped)}"
: "${DB_USERNAME:?set DB_USERNAME}"
HOST="${DB_HOST:-127.0.0.1}"
export DB_CONNECTION=mariadb DB_HOST="$HOST" DB_PORT="${DB_PORT:-3306}" DB_URL=
export DB_PASSWORD="${DB_PASSWORD:-}"
TMP="$(mktemp -d)"; trap 'rm -rf "$TMP"' EXIT
mkdir -p "$ROOT/dist"

cd "$ROOT" && php artisan migrate:fresh --force >/dev/null
DUMP="${DUMP_BIN:-mariadb-dump}"
$DUMP -h"$HOST" -u"$DB_USERNAME" ${DB_PASSWORD:+-p"$DB_PASSWORD"} --no-data --skip-comments --compact --add-drop-table "$DB_DATABASE" > "$TMP/schema.sql"
$DUMP -h"$HOST" -u"$DB_USERNAME" ${DB_PASSWORD:+-p"$DB_PASSWORD"} --no-create-info --skip-comments --compact --skip-extended-insert "$DB_DATABASE" migrations > "$TMP/migrations.sql"
# The plan rows (Free, Pro, Agency) are data the app expects to exist.
$DUMP -h"$HOST" -u"$DB_USERNAME" ${DB_PASSWORD:+-p"$DB_PASSWORD"} --no-create-info --skip-comments --compact --skip-extended-insert "$DB_DATABASE" plans > "$TMP/plans.sql"

TMPDIR_PY="$TMP" OUT="$ROOT/dist/workora-schema.sql" python3 - <<'PY'
import os, re
tmp, out = os.environ['TMPDIR_PY'], os.environ['OUT']
schema = re.sub(r'/\*M?!\d+.*?\*/;?', '', open(f'{tmp}/schema.sql').read(), flags=re.S)
stmts = [re.sub(r'\s+', ' ', s).strip() + ';' for s in schema.split(';\n') if s.strip().strip(';')]
migs = [l for l in open(f'{tmp}/migrations.sql').read().splitlines() if l.startswith('INSERT INTO')]
plans = [l for l in open(f'{tmp}/plans.sql').read().splitlines() if l.startswith('INSERT INTO')]
lines = ['-- Workora schema (MySQL/MariaDB). One statement per line.', 'SET FOREIGN_KEY_CHECKS=0;'] + stmts + migs + plans + ['SET FOREIGN_KEY_CHECKS=1;']
open(out, 'w').write('\n'.join(lines) + '\n')
print(f"{sum(s.startswith('CREATE TABLE') for s in stmts)} tables, {len(migs)} migration records -> {out}")
PY
