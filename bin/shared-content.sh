#!/bin/sh
set -eu
cd "$(dirname "$0")/.."
export COMPOSE_FILE="$PWD/compose.yaml:$PWD/compose.shared-content.yaml"
wp() { docker compose run --rm -T cli "$@"; }
case "${1:-setup}" in
  setup)
    mkdir -p dist
    sh bin/start.sh multisite
    if ! wp site list --field=path | grep -Fxq /site-c/; then wp site create --slug=site-c --title="Test Site C" --private; fi
    if ! wp site list --field=path | grep -Fxq /site-d/; then wp site create --slug=site-d --title="Test Site D" --private; fi
    wp plugin activate multisite-shared-content --network
    wp --user=1 msc sites
    wp eval 'for($i=0;$i<100 && \MSC\Queue::status()["pending"];$i++){$r=\MSC\Queue::run(1000);if($r["errors"]||!$r["processed"]){throw new RuntimeException("Queue needs recovery");}}if(array_sum(\MSC\Queue::status())){throw new RuntimeException("Queue did not drain");}echo "Shared content ready on Sites A, B, C and D.\n";'
    wp --user=1 msc reconcile
    wp eval 'for($i=0;$i<100 && \MSC\Queue::status()["pending"];$i++){$r=\MSC\Queue::run(1000);if($r["errors"]||!$r["processed"]){throw new RuntimeException("Site A catch-up queue needs recovery");}}if(array_sum(\MSC\Queue::status())){throw new RuntimeException("Site A catch-up is incomplete");}'
    wp eval-file /msc-examples/network-demo.php
    docker compose up -d shared-worker
    ;;
  wp) shift; wp "$@" ;;
  queue) wp msc queue --limit=1000 ;;
  training) shift; wp --user=1 msc training "$@" ;;
  demo) wp eval-file /msc-examples/network-demo.php ;;
  legacy-demo) wp eval-file /msc-examples/style-demo.php; wp eval-file /msc-examples/create-demo.php ;;
  style) wp eval-file /msc-examples/style-demo.php ;;
  test)
    [ "${MSC_DISPOSABLE_TESTS:-}" = 1 ] || { echo 'Tests create fixtures. Use MSC_DISPOSABLE_TESTS=1 only in a disposable test network.' >&2; exit 2; }
    docker compose stop shared-worker
    wp eval-file /msc-tests/legacy-participation.php
    wp eval-file /msc-tests/integration.php "${MSC_TEST_RECORDS:-205}"
    site_a_url="$(wp site list --field=url | grep '/site-a/$')"
    wp --url="$site_a_url" eval-file /msc-tests/http.php
    wp eval-file /msc-tests/media.php
    wp eval-file /msc-tests/acf.php
    wp --url="$site_a_url" eval-file /msc-tests/catalog.php
    wp --url="$site_a_url" eval-file /msc-tests/presentation.php
    wp --url="$site_a_url" eval-file /msc-tests/paragraph-spacing.php
    wp --url="$site_a_url" eval-file /msc-tests/omdb.php
    wp --url="$site_a_url" eval-file /msc-tests/open-import.php
    wp --url="$site_a_url" eval-file /msc-tests/movie-search.php
    wp --url="$site_a_url" eval-file /msc-tests/episode-admin.php
    wp --url="$site_a_url" eval-file /msc-tests/import-review.php
    wp --url="$site_a_url" eval-file /msc-tests/network-games.php
    wp --url="$site_a_url" eval-file /msc-tests/image-import.php
    wp --url="$site_a_url" eval-file /msc-tests/image-search.php
    wp --url="$site_a_url" eval-file /msc-tests/acf-publication.php
    wp --url="$site_a_url" eval-file /msc-tests/entities-calendar.php
    wp --url="$site_a_url" eval-file /msc-tests/credit-visibility.php
    wp --url="$site_a_url" eval-file /msc-tests/catalog-archives.php
    if ! docker compose run --rm -T -e MSC_DISPOSABLE_TESTS=1 cli --url="$site_a_url" eval-file /msc-tests/comics.php > /tmp/msc-comics-fixtures.log 2>&1; then cat /tmp/msc-comics-fixtures.log; exit 1; fi
    cat /tmp/msc-comics-fixtures.log
    docker compose run --rm -T -e MSC_DISPOSABLE_TESTS=1 cli --url="$site_a_url" eval-file /msc-tests/upcoming-releases.php
    ;;
  stop) docker compose down ;;
  *) echo 'Usage: sh bin/shared-content.sh setup|training|demo|style|queue|test|stop|wp [WP-CLI arguments]' >&2; exit 2 ;;
esac
