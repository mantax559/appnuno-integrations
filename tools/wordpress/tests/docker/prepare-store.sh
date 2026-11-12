#!/bin/sh
set -eu

if ! wp core is-installed --allow-root --path=/var/www/html >/dev/null 2>&1; then
    if [ ! -f /var/www/html/wp-config.php ]; then
        wp config create \
            --allow-root \
            --path=/var/www/html \
            --dbname="$WORDPRESS_DB_NAME" \
            --dbuser="$WORDPRESS_DB_USER" \
            --dbpass="$WORDPRESS_DB_PASSWORD" \
            --dbhost="$WORDPRESS_DB_HOST" \
            --skip-check
    fi
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

if [ -z "${WP_CLI_VERSION:-}" ]; then
    wp config set FS_METHOD direct --type=constant --allow-root --path=/var/www/html
fi
wp core is-installed --allow-root --path=/var/www/html
wp core version --allow-root --path=/var/www/html | grep -Fx "$WORDPRESS_VERSION"
if ! wp plugin is-installed woocommerce --allow-root --path=/var/www/html >/dev/null 2>&1; then
    if ! wp plugin install woocommerce \
        --version="$WOOCOMMERCE_VERSION" \
        --allow-root \
        --path=/var/www/html; then
        curl -fsSL -o /tmp/woocommerce.zip "https://github.com/woocommerce/woocommerce/archive/refs/tags/${WOOCOMMERCE_VERSION}.zip"
        rm -rf /tmp/woocommerce-src /var/www/html/wp-content/plugins/woocommerce
        mkdir -p /tmp/woocommerce-src
        cat > /tmp/extract-woocommerce.php <<'EOF'
<?php
$zip = new ZipArchive();
if ($zip->open('/tmp/woocommerce.zip') !== true) {
    fwrite(STDERR, "Unable to open the WooCommerce archive.\n");
    exit(1);
}
$zip->extractTo('/tmp/woocommerce-src');
$zip->close();
EOF
        php /tmp/extract-woocommerce.php
        mv "/tmp/woocommerce-src/woocommerce-${WOOCOMMERCE_VERSION}" /var/www/html/wp-content/plugins/woocommerce
    fi
fi
geolocation="/var/www/html/wp-content/plugins/woocommerce/includes/class-wc-geolocation.php"
if [ -f "$geolocation" ]; then
    sed -i 's/->get_message()/->get_error_message()/g' "$geolocation"
fi
if [ -n "${WP_CLI_VERSION:-}" ]; then
    legacy_order_class="/var/www/html/wp-content/plugins/woocommerce/classes/order.class.php"
    if [ -f "$legacy_order_class" ]; then
        sed -i 's/ &\$this,/ $this,/g' "$legacy_order_class"
    fi
fi
wp plugin activate woocommerce --allow-root --path=/var/www/html
if [ -z "${WP_CLI_VERSION:-}" ]; then
    wp plugin is-active woocommerce --allow-root --path=/var/www/html
    wp plugin get woocommerce --field=version --allow-root --path=/var/www/html | grep -Fx "$WOOCOMMERCE_VERSION"
fi

if [ "${APPNUNO_INSTALL_PLUGIN:-}" = "1" ]; then
    package_line="${APPNUNO_WOOCOMMERCE_LINE:-11.x}"
    if [ ! -f "/appnuno/package/appnuno-woocommerce-${package_line}.zip" ]; then
        echo "AppNuno WooCommerce package is missing." >&2
        exit 1
    fi
    if [ "$package_line" = "1.x" ]; then
        wp plugin install "/appnuno/package/appnuno-woocommerce-${package_line}.zip" --activate --allow-root --path=/var/www/html
        exit 0
    fi
    wp rewrite structure '/%postname%/' --hard --allow-root --path=/var/www/html
    if ! wp wc tool run install_pages --user=admin --allow-root --path=/var/www/html; then
        wp eval 'if (!function_exists("wc_create_page")) { foreach (array("wc-admin-functions.php", "woocommerce-admin-functions.php") as $file) { $path = WP_PLUGIN_DIR . "/woocommerce/includes/admin/" . $file; if (file_exists($path)) { include_once $path; break; } } } if (class_exists("WC_Install") && function_exists("wc_create_page")) { WC_Install::create_pages(); } else { global $woocommerce; if (is_object($woocommerce) && method_exists($woocommerce, "install")) { $woocommerce->install(); } }' --user=admin --allow-root --path=/var/www/html
    fi
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
    if ! wp user get limited --field=ID --allow-root --path=/var/www/html >/dev/null 2>&1; then
        wp user create limited limited@example.test --role=contributor --user_pass='appnuno-local' --allow-root --path=/var/www/html >/dev/null
    fi
    wp user set-role limited contributor --allow-root --path=/var/www/html >/dev/null
    wp option update woocommerce_enable_myaccount_registration yes --allow-root --path=/var/www/html
    wp option update woocommerce_registration_generate_password no --allow-root --path=/var/www/html
    if ! wp language core install lt_LT --allow-root --path=/var/www/html; then
        echo "Lithuanian language pack was not installed."
    fi
    mkdir -p /var/www/html/wp-content/mu-plugins /var/www/html/wp-content/uploads /var/www/html/wp-content/upgrade
    cp /scripts/appnuno-languages.php /var/www/html/wp-content/mu-plugins/appnuno-languages.php
    wp plugin install "/appnuno/package/appnuno-woocommerce-${package_line}.zip" --activate --force --allow-root --path=/var/www/html
    chown -R 33:33 /var/www/html/wp-content
    wp plugin is-active appnuno --allow-root --path=/var/www/html
    wp option delete module_appnuno_status --allow-root --path=/var/www/html || true
    wp option delete module_appnuno_config --allow-root --path=/var/www/html || true
    wp rewrite flush --hard --allow-root --path=/var/www/html
    wp transient delete _wc_activation_redirect --allow-root --path=/var/www/html || true
    wp eval 'if ( class_exists( "WC_Admin_Notices" ) && method_exists( "WC_Admin_Notices", "remove_notice" ) ) { WC_Admin_Notices::remove_notice( "install" ); }' --allow-root --path=/var/www/html || true
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
