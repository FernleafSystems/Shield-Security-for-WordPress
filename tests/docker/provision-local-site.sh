#!/usr/bin/env sh
set -eu

cd /var/www/html

SITE_URL="${SHIELD_LOCAL_SITE_URL:-http://127.0.0.1:8888}"
SITE_TITLE="${SHIELD_LOCAL_SITE_TITLE:-Shield Local Site}"
ADMIN_USER="${SHIELD_LOCAL_SITE_ADMIN_USER:-admin}"
ADMIN_PASSWORD="${SHIELD_LOCAL_SITE_ADMIN_PASSWORD:-password}"
ADMIN_EMAIL="${SHIELD_LOCAL_SITE_ADMIN_EMAIL:-devnull@example.com}"
SITE_PROFILE="${SHIELD_LOCAL_SITE_PROFILE:-dev}"
PROVISION_MODE="${SHIELD_LOCAL_SITE_PROVISION_MODE:-current-runtime}"
PLUGIN_SLUG="wp-simple-firewall"
PLUGIN_MAIN="wp-simple-firewall/icwp-wpsf.php"

if [ "${PROVISION_MODE}" != "core-only" ] && [ ! -f "wp-content/plugins/${PLUGIN_MAIN}" ]; then
	echo "Shield plugin runtime was not found at wp-content/plugins/${PLUGIN_MAIN}." >&2
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

wp rewrite structure '/%postname%/' --allow-root
wp rewrite flush --hard --allow-root

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

if [ "${PROVISION_MODE}" != "core-only" ]; then
	if ! wp plugin is-installed "${PLUGIN_SLUG}" --allow-root >/dev/null 2>&1; then
		echo "Shield plugin slug ${PLUGIN_SLUG} is not available to WP-CLI." >&2
		exit 1
	fi

	wp plugin activate "${PLUGIN_SLUG}" --allow-root
fi

if [ "${PROVISION_MODE}" != "core-only" ] && [ "${SITE_PROFILE}" = "test" ]; then
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

		update_option( $optionName, $all );

		shield_security_get_plugin()->getController()->db_con->loadAll();
	' --allow-root
fi

# A site that tests drive is a new install, on which the setup guide would open over the dashboard by itself and
# take the clicks meant for the page. Tests open pages to work with the page, so the guide starts minimised on the
# local test site and on every browser lane. A new install is also scanned within minutes, which would change what
# those pages show partway through a run, so that scan is called off. Other profiles can run a Shield from before
# there was either.
if [ "${PROVISION_MODE}" != "core-only" ]; then
	case "${SITE_PROFILE}" in
		test|browser-lane-*)
			wp eval '
				$con = shield_security_get_plugin()->getController();
				( new \FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\SetupGuideState() )->minimise();
				$con->opts->store();
				wp_clear_scheduled_hook( $con->prefix( \FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\ScansController::HOOK_FIRST_SCAN ) );
			' --allow-root
			;;
	esac
fi
