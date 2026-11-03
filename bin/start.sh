#!/bin/sh
set -eu
cd "$(dirname "$0")/.."
mode="${1:-single}"
case "$mode" in
    single|multisite) ;;
    *) echo 'Usage: sh bin/start.sh [single|multisite]' >&2; exit 2 ;;
esac
python3 bin/configure.py
docker compose build
docker compose up -d --wait db wordpress
docker compose run --rm -T setup "$mode"
