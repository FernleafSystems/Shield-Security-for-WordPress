#!/bin/sh
# Runs inside the wp-cli container: installs a throwaway WordPress site, installs
# Plugin Check, activates the plugin under test, then checks it and reports.
# Exit codes: 0 no new findings, 1 new findings, 2 setup failure.
set -eu

SLUG="${PLUGIN_CHECK_SLUG}"
VERSION="${PLUGIN_CHECK_VERSION}"
MODE="${PLUGIN_CHECK_MODE}"
LOG=/tmp/plugin-check-setup.log

cd /var/www/html

provision() {
	wp core download --skip-content --force --allow-root
	wp config create --dbname=wordpress --dbuser=root --dbpass=testpass --dbhost=db --skip-check --allow-root
	wp core install --url=http://plugin-check.local --title="Plugin Check" --admin_user=admin \
		--admin_password=password --admin_email=devnull@example.com --skip-email --allow-root
	wp plugin install plugin-check --version="${VERSION}" --activate --allow-root
	wp plugin activate "${SLUG}" --allow-root
}

echo "Provisioning WordPress with Plugin Check ${VERSION}..."
if ! provision >"${LOG}" 2>&1; then
	cat "${LOG}" >&2
	echo "Plugin Check setup failed." >&2
	exit 2
fi
echo "WordPress $(wp core version --allow-root) ready; ${SLUG} is active."

echo "Running Plugin Check (${MODE} mode) against ${SLUG}. Large plugins can take several minutes..."
set +e
wp plugin check "${SLUG}" \
	--format=json \
	--require=./wp-content/plugins/plugin-check/cli.php \
	--slug="${SLUG}" \
	--mode="${MODE}" \
	--include-experimental \
	--include-low-severity-errors \
	--include-low-severity-warnings \
	--allow-root >/tmp/plugin-check.out 2>/tmp/plugin-check.err
CHECK_EXIT=$?
set -e

if [ "${CHECK_EXIT}" -ne 0 ]; then
	cat /tmp/plugin-check.out /tmp/plugin-check.err >&2
	echo "wp plugin check exited with code ${CHECK_EXIT}." >&2
	exit 2
fi

exec php /plugin-check/report.php /tmp/plugin-check.out
