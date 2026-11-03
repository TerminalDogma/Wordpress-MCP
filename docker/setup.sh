#!/bin/sh
set -eu

mode="${1:-single}"
case "$mode" in
    single|multisite) ;;
    *) echo 'Setup mode must be single or multisite.' >&2; exit 2 ;;
esac
wp_cli() { wp --url="$WORDPRESS_URL" "$@"; }

if wp_cli core is-installed --network >/dev/null 2>&1; then
    # A normal restart must retain an existing network and its rewrite rules.
    mode=multisite
elif wp_cli core is-installed >/dev/null 2>&1; then
    if [ "$mode" = multisite ]; then
        wp_cli --skip-plugins core multisite-convert --title="$WORDPRESS_TITLE" --base=/
    fi
else
    command=install
    if [ "$mode" = multisite ]; then command=multisite-install; fi
    # Do not enable shell tracing or WP-CLI debug output: credentials come
    # from the private environment, with no interactive password prompt.
    wp_cli core "$command" \
        --title="$WORDPRESS_TITLE" \
        --admin_user="$WORDPRESS_ADMIN_USER" \
        --admin_email="$WORDPRESS_ADMIN_EMAIL" \
        --admin_password="$WORDPRESS_ADMIN_PASSWORD" \
        --skip-email
fi

# This project manages the ACF dependency pinned in Dockerfile.
mkdir -p wp-content/plugins/advanced-custom-fields
cp -a /opt/acf/. wp-content/plugins/advanced-custom-fields/

if [ "$mode" = multisite ]; then
    wp_cli plugin activate advanced-custom-fields --network
    cat > .htaccess <<'HTACCESS'
# BEGIN WordPress Multisite
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
RewriteBase /
RewriteRule ^index\.php$ - [L]
RewriteRule ^([_0-9a-zA-Z-]+/)?wp-admin$ $1wp-admin/ [R=301,L]
RewriteCond %{REQUEST_FILENAME} -f [OR]
RewriteCond %{REQUEST_FILENAME} -d
RewriteRule ^ - [L]
RewriteRule ^([_0-9a-zA-Z-]+/)?(wp-(content|admin|includes).*) $2 [L]
RewriteRule ^([_0-9a-zA-Z-]+/)?(.*\.php)$ $2 [L]
RewriteRule . index.php [L]
</IfModule>
# END WordPress Multisite
HTACCESS
    for slug in site-a site-b; do
        if ! wp_cli site list --field=path | grep -Fxq "/$slug/"; then
            wp_cli site create --slug="$slug" --title="Test $slug" --private
            wp --url="${WORDPRESS_URL%/}/$slug/" rewrite structure '/%postname%/'
        fi
    done
    wp_cli site list --fields=blog_id,url
else
    wp_cli plugin activate advanced-custom-fields
    wp_cli rewrite structure '/%postname%/'
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
fi

wp_cli core version
wp_cli plugin get advanced-custom-fields --fields=name,status,version
