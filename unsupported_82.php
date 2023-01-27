<?php

add_action( 'admin_notices', 'icwp_wpsf_unsupported_php' );
add_action( 'network_admin_notices', 'icwp_wpsf_unsupported_php' );

function icwp_wpsf_unsupported_php() {
	global $sIcwpWpsfPluginFile;
	$text = [
		"Sorry, your website is running PHP 8.2 and Shield doesn't yet support this version of PHP.",
		"Rather than cause any errors on your site, we've prevented Shield from running.",
		'As it stands today, there is no good reason to run your WordPress site on PHP 8.2. We recommend switching to PHP 8.0 or 8.1'
	];
	echo sprintf(
		'<div class="error"><h4>%s</h4><p>%s</p><p><a href="%s" style="font-weight: bolder">%s</a></p></div>',

		sprintf( 'Shield Security Plugin - Unsupported PHP Version: %s', PHP_VERSION ),
		implode( '<br/>', $text ),
		add_query_arg(
			[
				'action'   => 'deactivate',
				'plugin'   => urlencode( $sIcwpWpsfPluginFile ),
				'_wpnonce' => wp_create_nonce( 'deactivate-plugin_'.$sIcwpWpsfPluginFile )
			],
			self_admin_url( 'plugins.php' )
		),
		'Deactivate the Shield Security plugin'
	);
}