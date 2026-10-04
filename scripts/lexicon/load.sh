#!/bin/sh
# Loads the files written by build.py into the lex schema of $DATABASE_URL.
# Drops and recreates lex (nothing outside it is touched).
#
#   DATABASE_URL=postgresql://... sh scripts/lexicon/load.sh <out-dir>
set -eu
OUT=$(cd "$1" && pwd)
HERE=$(cd "$(dirname "$0")" && pwd)
: "${DATABASE_URL:?set DATABASE_URL}"

psql "$DATABASE_URL" -v ON_ERROR_STOP=1 -q -f "$HERE/schema.sql"
cd "$OUT"
psql "$DATABASE_URL" -v ON_ERROR_STOP=1 -q -c "\\copy lex.sources (id, code, name, url, licence, licence_url, attribution, visibility, origin, notes) FROM 'sources.tsv'"
for t in entries entry_sources senses forms prons conj_forms links chars sentences texts media; do
    echo "loading $t"
    psql "$DATABASE_URL" -v ON_ERROR_STOP=1 -q -c "\\copy lex.$t FROM '$t.tsv'"
done
echo "keys, indexes, views"
psql "$DATABASE_URL" -v ON_ERROR_STOP=1 -q -f "$HERE/post.sql"
psql "$DATABASE_URL" -Atc "SELECT pg_size_pretty(sum(pg_total_relation_size(c.oid))) FROM pg_class c JOIN pg_namespace n ON n.oid = c.relnamespace WHERE n.nspname = 'lex'" | sed 's/^/lex schema size: /'
