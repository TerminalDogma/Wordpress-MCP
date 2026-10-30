#!/bin/sh
set -eu
cd "$(dirname "$0")/.."
python3 bin/configure.py
docker compose build
docker compose up -d --wait db wordpress
docker compose run --rm -T setup
