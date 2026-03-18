#!/usr/bin/env sh
set -eu

cd /var/www/html

SITE_URL="${SHIELD_LOCAL_SITE_URL:-http://127.0.0.1:8888}"
SITE_TITLE="${SHIELD_LOCAL_SITE_TITLE:-Shield Local Site}"
ADMIN_USER="${SHIELD_LOCAL_SITE_ADMIN_USER:-admin}"
ADMIN_PASSWORD="${SHIELD_LOCAL_SITE_ADMIN_PASSWORD:-password}"
ADMIN_EMAIL="${SHIELD_LOCAL_SITE_ADMIN_EMAIL:-devnull@example.com}"
SHIELD_BROWSER_TEST="${SHIELD_BROWSER_TEST_INTRO:-0}"
PLUGIN_SLUG="wp-simple-firewall"
PLUGIN_MAIN="wp-simple-firewall/icwp-wpsf.php"

if [ ! -f "wp-content/plugins/${PLUGIN_MAIN}" ]; then
	echo "Shield plugin source mount was not found at wp-content/plugins/${PLUGIN_MAIN}." >&2
	exit 1
fi

if ! wp core is-installed --allow-root >/dev/null 2>&1; then
	wp core install \
		--url="${SITE_URL}" \
		--title="${SITE_TITLE}" \
		--admin_user="${ADMIN_USER}" \
		--admin_password="${ADMIN_PASSWORD}" \
		--admin_email="${ADMIN_EMAIL}" \
		--skip-email \
		--allow-root
fi

if ! wp user get "${ADMIN_USER}" --field=ID --allow-root >/dev/null 2>&1; then
	wp user create "${ADMIN_USER}" "${ADMIN_EMAIL}" \
		--role=administrator \
		--user_pass="${ADMIN_PASSWORD}" \
		--allow-root
else
	wp user update "${ADMIN_USER}" \
		--user_pass="${ADMIN_PASSWORD}" \
		--user_email="${ADMIN_EMAIL}" \
		--allow-root
fi

if ! wp plugin is-installed "${PLUGIN_SLUG}" --allow-root >/dev/null 2>&1; then
	echo "Shield plugin slug ${PLUGIN_SLUG} is not available to WP-CLI." >&2
	exit 1
fi

wp plugin activate "${PLUGIN_SLUG}" --allow-root

if [ "${SHIELD_BROWSER_TEST}" = "1" ]; then
	wp eval '
		$optionName = "icwp_wpsf_opts_all";
		$all = get_option( $optionName, [] );
		if ( !is_array( $all ) ) {
			$all = [
				"version" => 0,
				"values"  => [
					"free" => [],
					"pro"  => [],
				],
				"xfer_excluded" => [],
			];
		}
		else {
			$all[ "values" ] = ( is_array( $all[ "values" ] ) ) ? $all[ "values" ] : [];
		}

		$now = time();
		$all[ "values" ][ "free" ][ "v20_intro_closed_at" ] = $now;
		$all[ "values" ][ "pro" ][ "v20_intro_closed_at" ] = $now;
		update_option( $optionName, $all );

		$adminUser = get_user_by( "login", getenv( "SHIELD_LOCAL_SITE_ADMIN_USER" ) ?: "admin" );
		if ( $adminUser instanceof WP_User ) {
			$metaKeys = [
				"icwp-wpsf-meta",
				"icwp_wpsf-meta",
				"icwp-meta",
			];
			foreach ( $metaKeys as $metaKey ) {
				$userMeta = get_user_meta( (int)$adminUser->ID, $metaKey, true );
				if ( !is_array( $userMeta ) ) {
					$userMeta = [];
				}
				$userMeta[ "tours" ] = [ "navigation_v1" => $now ];
				update_user_meta( (int)$adminUser->ID, $metaKey, $userMeta );
			}
		}
	' --allow-root
fi
