#!/bin/sh
set -eu

if ! wp core is-installed --allow-root --path=/var/www/html >/dev/null 2>&1; then
    wp config create \
        --allow-root \
        --path=/var/www/html \
        --dbname="$WORDPRESS_DB_NAME" \
        --dbuser="$WORDPRESS_DB_USER" \
        --dbpass="$WORDPRESS_DB_PASSWORD" \
        --dbhost="$WORDPRESS_DB_HOST" \
        --skip-check
    wp core install \
        --allow-root \
        --path=/var/www/html \
        --url="$WORDPRESS_SITE_URL" \
        --title="AppNuno WordPress test store" \
        --admin_user="$WORDPRESS_ADMIN_USER" \
        --admin_password="$WORDPRESS_ADMIN_PASSWORD" \
        --admin_email="$WORDPRESS_ADMIN_EMAIL" \
        --skip-email
fi

wp core is-installed --allow-root --path=/var/www/html
wp core version --allow-root --path=/var/www/html | grep -Fx "$WORDPRESS_VERSION"
wp plugin install woocommerce \
    --version="$WOOCOMMERCE_VERSION" \
    --activate \
    --allow-root \
    --path=/var/www/html
wp plugin is-active woocommerce --allow-root --path=/var/www/html
wp plugin get woocommerce --field=version --allow-root --path=/var/www/html | grep -Fx "$WOOCOMMERCE_VERSION"
