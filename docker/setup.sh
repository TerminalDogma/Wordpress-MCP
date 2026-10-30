#!/bin/sh
set -eu

if ! wp --url="$WORDPRESS_URL" core is-installed; then
    # Noninteractive setup avoids WP-CLI echoing password prompts to logs.
    # Do not enable shell tracing or WP-CLI debug output here.
    wp core install \
        --url="$WORDPRESS_URL" \
        --title="$WORDPRESS_TITLE" \
        --admin_user="$WORDPRESS_ADMIN_USER" \
        --admin_email="$WORDPRESS_ADMIN_EMAIL" \
        --admin_password="$WORDPRESS_ADMIN_PASSWORD" \
        --skip-email
fi

# This project manages this dependency at the version pinned in Dockerfile.
# Keep ACF field data in the database; do not modify the dependency's source.
mkdir -p wp-content/plugins/advanced-custom-fields
cp -a /opt/acf/. wp-content/plugins/advanced-custom-fields/
wp plugin activate advanced-custom-fields
wp rewrite structure '/%postname%/'
cat > .htaccess <<'HTACCESS'
# BEGIN WordPress
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
RewriteBase /
RewriteRule ^index\.php$ - [L]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . /index.php [L]
</IfModule>
# END WordPress
HTACCESS
wp core version
wp plugin get advanced-custom-fields --fields=name,status,version
