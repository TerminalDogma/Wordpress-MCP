#!/bin/sh
set -eu
cd "$(dirname "$0")/.."
[ "${MSC_DISPOSABLE_TESTS:-}" = 1 ] || { echo 'Open import browser fixtures require a disposable local installation.' >&2; exit 2; }
export COMPOSE_FILE="$PWD/compose.yaml:$PWD/compose.shared-content.yaml"
wp_test() { docker compose exec -T --user 33 wordpress /bin/sh -c 'wp --url="$WORDPRESS_URL" "$@"' sh "$@"; }
wp_test eval 'if(wp_get_environment_type()!=="local"||defined("MSC_OPEN_TEST_FIXTURES")){throw new RuntimeException("Use a disposable local installation without existing fixture constants.");}'
cleanup() {
  wp_test plugin deactivate msc-open-fixtures --network >/dev/null 2>&1 || true
  wp_test plugin delete msc-open-fixtures >/dev/null 2>&1 || true
  wp_test config delete MSC_OPEN_TEST_FIXTURES >/dev/null 2>&1 || true
  wp_test eval 'delete_network_option(get_current_network_id(),"msc_open_fixture_seed");' >/dev/null 2>&1 || true
}
trap cleanup EXIT HUP INT TERM
docker compose cp tests/shared-content/open-fixtures.php wordpress:/var/www/html/wp-content/plugins/msc-open-fixtures.php
wp_test config set MSC_OPEN_TEST_FIXTURES true --raw
wp_test plugin activate msc-open-fixtures --network
wp_test eval 'update_network_option(get_current_network_id(),"msc_open_fixture_seed",random_int(500000000,899999000));foreach(["tvmaze","wikidata","wikipedia","commons"] as $p){delete_site_transient("msc_open_rate_".$p);delete_site_transient("msc_open_backoff_".$p);}'
python3 tests/shared-content/open-browser.py
