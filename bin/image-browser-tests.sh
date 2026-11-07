#!/bin/sh
set -eu
cd "$(dirname "$0")/.."
[ "${MSC_DISPOSABLE_TESTS:-}" = 1 ] || { echo 'Disposable local installation required.' >&2; exit 2; }
export COMPOSE_FILE="$PWD/compose.yaml:$PWD/compose.shared-content.yaml"
wp_test() { docker compose exec -T --user 33 wordpress /bin/sh -c 'wp --url="$WORDPRESS_URL" "$@"' sh "$@"; }
wp_test eval 'if(wp_get_environment_type()!=="local"||defined("MSC_IMAGE_TEST_FIXTURES")){throw new RuntimeException("Disposable local installation without existing image fixtures required.");}'
wp_test eval 'if(\MSC\ImageSearch::managed()){throw new RuntimeException("Use a disposable test network without a server-managed search key.");}update_network_option(get_current_network_id(),"msc_image_search_test_backup",get_network_option(get_current_network_id(),"msc_image_search_key",false));'
cleanup() {
  wp_test plugin deactivate msc-image-fixtures --network >/dev/null 2>&1 || true
  wp_test plugin delete msc-image-fixtures >/dev/null 2>&1 || true
  wp_test config delete MSC_IMAGE_TEST_FIXTURES >/dev/null 2>&1 || true
  wp_test eval '$old=get_network_option(get_current_network_id(),"msc_image_search_test_backup",false);if(false===$old){delete_network_option(get_current_network_id(),"msc_image_search_key");}else{update_network_option(get_current_network_id(),"msc_image_search_key",$old);}delete_network_option(get_current_network_id(),"msc_image_search_test_backup");' >/dev/null 2>&1 || true
}
trap cleanup EXIT HUP INT TERM
docker compose cp tests/shared-content/image-fixtures.php wordpress:/var/www/html/wp-content/plugins/msc-image-fixtures.php
wp_test config set MSC_IMAGE_TEST_FIXTURES true --raw
wp_test plugin activate msc-image-fixtures --network
python3 tests/shared-content/image-browser.py
