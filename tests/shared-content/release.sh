#!/bin/sh
set -eu
cp -R /var/www/html /tmp/msc-release-wordpress
cd /tmp/msc-release-wordpress
wp --url="$WORDPRESS_URL" plugin deactivate multisite-shared-content --network
wp --url="$WORDPRESS_URL" plugin delete multisite-shared-content
wp --url="$WORDPRESS_URL" plugin install /msc-releases/multisite-shared-content-1.1.0.zip --activate-network
wp --url="$WORDPRESS_URL" plugin is-active multisite-shared-content --network
wp --url="$WORDPRESS_URL" eval 'if(MSC_VERSION!=="1.1.0"||!get_network_option(get_current_network_id(),"msc_schemas")){throw new RuntimeException("Release activation failed");}echo "PASS: Plugin ZIP installed and network activated with schemas retained.\n";'
