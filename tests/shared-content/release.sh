#!/bin/sh
set -eu
cp -R /var/www/html /tmp/msc-release-wordpress
cd /tmp/msc-release-wordpress
MSC_EXPECTED_RELEASE="$(wp --url="$WORDPRESS_URL" eval 'echo MSC_VERSION;')"
export MSC_EXPECTED_RELEASE
wp --url="$WORDPRESS_URL" plugin deactivate multisite-shared-content --network
wp --url="$WORDPRESS_URL" plugin delete multisite-shared-content
wp --url="$WORDPRESS_URL" plugin install "/msc-releases/multisite-shared-content-$MSC_EXPECTED_RELEASE.zip" --activate-network
wp --url="$WORDPRESS_URL" plugin is-active multisite-shared-content --network
wp --url="$WORDPRESS_URL" eval 'if(MSC_VERSION!==getenv("MSC_EXPECTED_RELEASE")||!get_network_option(get_current_network_id(),"msc_schemas")){throw new RuntimeException("Release activation failed");}echo "PASS: Plugin ZIP installed and network activated with schemas retained.\n";'
