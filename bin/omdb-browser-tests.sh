#!/bin/sh
set -eu
cd "$(dirname "$0")/.."
[ "${MSC_DISPOSABLE_TESTS:-}" = 1 ] || { echo 'OMDb browser fixtures require an explicitly disposable test installation.' >&2; exit 2; }
export COMPOSE_FILE="$PWD/compose.yaml:$PWD/compose.shared-content.yaml"
wp_test() { docker compose exec -T --user 33 wordpress /bin/sh -c 'wp --url="$WORDPRESS_URL" "$@"' sh "$@"; }
wp_test eval 'if(wp_get_environment_type()!=="local"||defined("MSC_OMDB_TEST_FIXTURES")||defined("MSC_OMDB_API_KEY")||defined("MSC_ENABLE_LEGACY_OMDB")){throw new RuntimeException("Use a disposable local installation without existing OMDb config constants.");}'
cleanup() {
  wp_test plugin deactivate msc-omdb-fixtures --network >/dev/null 2>&1 || true
  wp_test plugin delete msc-omdb-fixtures >/dev/null 2>&1 || true
  wp_test config delete MSC_OMDB_TEST_FIXTURES >/dev/null 2>&1 || true
  wp_test config delete MSC_ENABLE_LEGACY_OMDB >/dev/null 2>&1 || true
  wp_test config delete MSC_OMDB_API_KEY >/dev/null 2>&1 || true
  wp_test eval 'delete_network_option(get_current_network_id(),"msc_omdb_browser_id");' >/dev/null 2>&1 || true
}
trap cleanup EXIT HUP INT TERM
docker compose cp tests/shared-content/omdb-fixtures.php wordpress:/var/www/html/wp-content/plugins/msc-omdb-fixtures.php
wp_test config set MSC_OMDB_TEST_FIXTURES true --raw
wp_test config set MSC_ENABLE_LEGACY_OMDB true --raw
wp_test config set MSC_OMDB_API_KEY msc-test-key
wp_test plugin activate msc-omdb-fixtures --network
wp_test eval 'update_network_option(get_current_network_id(),"msc_omdb_browser_id","tt".random_int(920000000,999999999));'
python3 tests/shared-content/omdb-browser.py
