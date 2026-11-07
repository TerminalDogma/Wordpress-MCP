#!/bin/sh
set -eu
cd "$(dirname "$0")/.."
[ "${MSC_DISPOSABLE_TESTS:-}" = 1 ] || { echo 'Disposable local installation required.' >&2; exit 2; }
export COMPOSE_FILE="$PWD/compose.yaml:$PWD/compose.shared-content.yaml"
wp_test() { docker compose exec -T --user 33 wordpress /bin/sh -c 'wp --url="$WORDPRESS_URL" "$@"' sh "$@"; }
wp_test eval 'if(wp_get_environment_type()!=="local"||defined("MSC_MOVIE_SEARCH_FIXTURES")){throw new RuntimeException("Disposable local installation without existing Movie search fixtures required.");}'
cleanup() {
  wp_test plugin deactivate msc-movie-search-fixtures --network >/dev/null 2>&1 || true
  wp_test plugin delete msc-movie-search-fixtures >/dev/null 2>&1 || true
  wp_test config delete MSC_MOVIE_SEARCH_FIXTURES >/dev/null 2>&1 || true
  wp_test eval 'delete_network_option(get_current_network_id(),"msc_movie_search_fixture_seed");delete_site_transient("msc_open_rate_wikidata");delete_site_transient("msc_open_backoff_wikidata");' >/dev/null 2>&1 || true
}
trap cleanup EXIT HUP INT TERM
docker compose cp tests/shared-content/movie-search-fixtures.php wordpress:/var/www/html/wp-content/plugins/msc-movie-search-fixtures.php
wp_test config set MSC_MOVIE_SEARCH_FIXTURES true --raw
wp_test plugin activate msc-movie-search-fixtures --network
wp_test eval 'update_network_option(get_current_network_id(),"msc_movie_search_fixture_seed",random_int(930000000,950000000));delete_site_transient("msc_open_rate_wikidata");delete_site_transient("msc_open_backoff_wikidata");'
python3 tests/shared-content/movie-search-browser.py
