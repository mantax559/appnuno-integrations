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
if ! wp plugin is-installed woocommerce --allow-root --path=/var/www/html >/dev/null 2>&1; then
    wp plugin install woocommerce \
        --version="$WOOCOMMERCE_VERSION" \
        --activate \
        --allow-root \
        --path=/var/www/html
else
    wp plugin activate woocommerce --allow-root --path=/var/www/html
fi
wp plugin is-active woocommerce --allow-root --path=/var/www/html
wp plugin get woocommerce --field=version --allow-root --path=/var/www/html | grep -Fx "$WOOCOMMERCE_VERSION"

if [ "${APPNUNO_INSTALL_PLUGIN:-}" = "1" ]; then
    if [ ! -f /appnuno/package/appnuno-woocommerce-11.x.zip ]; then
        echo "AppNuno WooCommerce package is missing." >&2
        exit 1
    fi
    wp rewrite structure '/%postname%/' --hard --allow-root --path=/var/www/html
    wp wc tool run install_pages --user=admin --allow-root --path=/var/www/html
    wp option update woocommerce_coming_soon no --allow-root --path=/var/www/html
    wp option update woocommerce_store_pages_only no --allow-root --path=/var/www/html
    wp option update woocommerce_onboarding_profile '{"completed":true,"skipped":true}' --format=json --allow-root --path=/var/www/html
    contact_id=$(wp post list --post_type=page --name=contact --field=ID --allow-root --path=/var/www/html)
    if [ -z "$contact_id" ]; then
        wp post create --post_type=page --post_status=publish --post_title='Contact' --post_name=contact --allow-root --path=/var/www/html >/dev/null
    fi
    product_id=$(wp post list --post_type=product --name=appnuno-sample --field=ID --allow-root --path=/var/www/html)
    if [ -z "$product_id" ]; then
        product_id=$(wp post create --post_type=product --post_status=publish --post_title='AppNuno Sample' --post_name=appnuno-sample --porcelain --allow-root --path=/var/www/html)
        wp post meta update "$product_id" _regular_price 10 --allow-root --path=/var/www/html
        wp post meta update "$product_id" _price 10 --allow-root --path=/var/www/html
        wp post meta update "$product_id" _stock_status instock --allow-root --path=/var/www/html
        wp post meta update "$product_id" _visibility visible --allow-root --path=/var/www/html
        wp post term set "$product_id" product_type simple --allow-root --path=/var/www/html
    fi
    if ! wp user get customer --field=ID --allow-root --path=/var/www/html >/dev/null 2>&1; then
        wp user create customer customer@example.test --role=customer --user_pass='appnuno-local' --allow-root --path=/var/www/html >/dev/null
    fi
    wp plugin install /appnuno/package/appnuno-woocommerce-11.x.zip --activate --force --allow-root --path=/var/www/html
    wp plugin is-active appnuno --allow-root --path=/var/www/html
    wp rewrite flush --hard --allow-root --path=/var/www/html
    if [ ! -f /var/www/html/.htaccess ]; then
        cat > /var/www/html/.htaccess <<'EOF'
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
EOF
    fi
fi
