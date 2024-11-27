<?php

add_action( 'admin_notices', 'icwp_wpsf_unsupported_php' );
add_action( 'network_admin_notices', 'icwp_wpsf_unsupported_php' );

function icwp_wpsf_unsupported_php() {
	global $sIcwpWpsfPluginFile;
	$text = array(
		'Sorry, your website runs an incredibly old version of PHP that Shield Security no longer supports, as of Shield v9.0',
		"Your PHP no longer gets upgrades and it's difficult to maintain code for.",
		'We recommend that you contact your website hosting provider on how to upgrade to at least PHP 7.4'
	);
	echo sprintf(
		'<div class="error"><h4>%s</h4><p>%s</p>' .
		'<p><a href="%s" target="_blank" style="font-weight: bolder">%s</a> ' .
		'/ <a href="%s">%s</a></p></div>',

		sprintf( 'Shield Security Plugin - Unsupported PHP Version: %s', PHP_VERSION ),
		implode( '<br/>', $text ),
		'https://clk.shldscrty.com/dl',
		'Click here for more info',
		add_query_arg(
			array(
				'action'   => 'deactivate',
				'plugin'   => urlencode( $sIcwpWpsfPluginFile ),
				'_wpnonce' => wp_create_nonce( 'deactivate-plugin_'.$sIcwpWpsfPluginFile )
			),
			self_admin_url( 'plugins.php' )
		),
		'Or, deactivate the Shield Security plugin for now'
	);
}