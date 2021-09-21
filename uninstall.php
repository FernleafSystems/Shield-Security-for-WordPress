<?php declare( strict_types=1 );

if ( !defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	die;
}

// Ensure Shield isn't active elsewhere.
if ( !@class_exists( '\FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller' ) ) {
	return;
	require_once( dirname( __FILE__ ).'/src/lib/vendor/autoload.php' );
	try {
		\FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller::GetInstance(
			path_join( __DIR__, 'icwp-wpsf.php' )
		)->deletePlugin();
	}
	catch ( \Exception $e ) {
	}
}