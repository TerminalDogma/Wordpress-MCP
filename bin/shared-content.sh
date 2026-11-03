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
    wp plugin activate multisite-shared-content --network
    wp eval 'wp_set_current_user(1); foreach(get_sites(["number"=>0]) as $site){if(in_array($site->path,["/site-a/","/site-b/","/site-c/"],true)){\MSC\Schema::participate((int)$site->blog_id,array_keys(\MSC\Schema::all()));}}'
    wp eval 'for($i=0;$i<100 && \MSC\Queue::status()["pending"];$i++){$r=\MSC\Queue::run(1000);if($r["errors"]||!$r["processed"]){throw new RuntimeException("Queue needs recovery");}}if(array_sum(\MSC\Queue::status())){throw new RuntimeException("Queue did not drain");}echo "Shared content ready on Sites A, B and C.\n";'
    docker compose up -d shared-worker
    ;;
  wp) shift; wp "$@" ;;
  queue) wp msc queue --limit=1000 ;;
  demo) wp eval-file /msc-examples/create-demo.php ;;
  test)
    [ "${MSC_DISPOSABLE_TESTS:-}" = 1 ] || { echo 'Tests create fixtures. Use MSC_DISPOSABLE_TESTS=1 only in a disposable test network.' >&2; exit 2; }
    docker compose stop shared-worker
    wp eval-file /msc-tests/integration.php "${MSC_TEST_RECORDS:-205}"
    site_a_url="$(wp site list --field=url | grep '/site-a/$')"
    wp --url="$site_a_url" eval-file /msc-tests/http.php
    wp eval-file /msc-tests/media.php
    wp eval-file /msc-tests/acf.php
    ;;
  stop) docker compose down ;;
  *) echo 'Usage: sh bin/shared-content.sh setup|demo|queue|test|stop|wp [WP-CLI arguments]' >&2; exit 2 ;;
esac
