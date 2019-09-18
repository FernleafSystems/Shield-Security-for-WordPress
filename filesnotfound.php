<?php

foreach (
	[ 'ICWP_WPSF_FeatureHandler_Base', 'ICWP_WPSF_FeatureHandler_BaseWpsf', ] as $sClass
) {
	if ( !@class_exists( $sClass ) ) {
		add_action( 'admin_notices', 'icwp_wpsf_checkfilesnotfound' );
		add_action( 'network_admin_notices', 'icwp_wpsf_checkfilesnotfound' );
		return false;
	}
}

function icwp_wpsf_checkfilesnotfound() {
	echo sprintf( '<div class="error"><h4>%s</h4><p>%s</p></div>',
		'Shield Security Plugin - Broken Installation',
		implode( '<br/>', [
			'It appears the Shield Security plugin was not upgraded/installed correctly.',
			"We run a quick check to make sure certain important files are present in-case a faulty installation breaks your site.",
			'Try refreshing this page, and if you continue to see this notice, we recommend that you reinstall the Shield Security plugin.'
		] )
	);
}

return true;