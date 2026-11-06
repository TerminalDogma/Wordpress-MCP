#!/bin/sh
set -eu
cd "$(dirname "$0")/.."
[ "${MSC_DISPOSABLE_TESTS:-}" = 1 ] || { echo 'Disposable local network required.' >&2; exit 2; }
export COMPOSE_FILE="$PWD/compose.yaml:$PWD/compose.shared-content.yaml"
wp_test() { docker compose exec -T --user 33 wordpress /bin/sh -c 'wp --url="$WORDPRESS_URL" "$@"' sh "$@"; }
wp_test eval 'if(wp_get_environment_type()!=="local"||defined("MSC_IGDB_TEST_FIXTURES")||\MSC\IgdbClient::managed()){throw new RuntimeException("Use a disposable local installation without IGDB server credentials.");}update_network_option(get_current_network_id(),"msc_igdb_test_backup",get_network_option(get_current_network_id(),"msc_igdb_credentials",false));'
cleanup() {
  wp_test plugin deactivate msc-igdb-fixtures --network >/dev/null 2>&1 || true
  wp_test plugin delete msc-igdb-fixtures >/dev/null 2>&1 || true
  wp_test config delete MSC_IGDB_TEST_FIXTURES >/dev/null 2>&1 || true
  wp_test eval '$old=get_network_option(get_current_network_id(),"msc_igdb_test_backup",false);if(false===$old){delete_network_option(get_current_network_id(),"msc_igdb_credentials");}else{update_network_option(get_current_network_id(),"msc_igdb_credentials",$old);}delete_network_option(get_current_network_id(),"msc_igdb_test_backup");delete_network_option(get_current_network_id(),"msc_igdb_fixture_seed");' >/dev/null 2>&1 || true
}
trap cleanup EXIT HUP INT TERM
docker compose cp tests/shared-content/igdb-fixtures.php wordpress:/var/www/html/wp-content/plugins/msc-igdb-fixtures.php
wp_test config set MSC_IGDB_TEST_FIXTURES true --raw
wp_test plugin activate msc-igdb-fixtures --network
wp_test --user=1 eval '\MSC\IgdbClient::save("fixture-client","fixture-secret");update_network_option(get_current_network_id(),"msc_igdb_fixture_seed",random_int(860000000,869000000));delete_site_transient("msc_igdb_rate");delete_site_transient("msc_igdb_backoff");'
sh bin/shared-content.sh wp eval-file /msc-tests/network-profiles.php
python3 tests/shared-content/network-browser.py
