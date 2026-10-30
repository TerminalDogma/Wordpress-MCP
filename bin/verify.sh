#!/bin/sh
set -eu
cd "$(dirname "$0")/.."
docker compose run --rm -T cli core is-installed
docker compose run --rm -T cli plugin is-active advanced-custom-fields
docker compose run --rm -T cli eval-file /opt/wordpress/verify.php
