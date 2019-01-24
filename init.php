<?php
/** @var string $sRootFile */
global $oICWP_Wpsf;

if ( isset( $oICWP_Wpsf ) ) {
	error_log( 'Attempting to load the Shield Plugin twice?' );
	return;
}

// By requiring this file here, we assume we wont need to require it anywhere else.

class ICWP_WPSF_Shield_Security extends ICWP_WPSF_Foundation {

	/**
	 * @var ICWP_WPSF_Shield_Security
	 */
	private static $oInstance = null;

	/**
	 * @param ICWP_WPSF_Plugin_Controller $oController
	 */
	private function __construct( ICWP_WPSF_Plugin_Controller $oController ) {
		$oController->loadAllFeatures();
	}

	/**
	 * @return ICWP_WPSF_Plugin_Controller
	 * @throws \Exception
	 */
	public function getController() {
		return ICWP_WPSF_Plugin_Controller::GetInstance();
	}

	/**
	 * @param ICWP_WPSF_Plugin_Controller $oController
	 * @return self
	 * @throws \Exception
	 */
	public static function GetInstance( $oController = null ) {
		if ( is_null( self::$oInstance ) ) {
			if ( !$oController instanceof ICWP_WPSF_Plugin_Controller ) {
				throw new \Exception( 'Trying to create a Shield Plugin instance without a valid Controller' );
			}
			self::$oInstance = new self( $oController );
		}
		return self::$oInstance;
	}
}

try {
	$oICWP_Wpsf_Controller = ICWP_WPSF_Plugin_Controller::GetInstance( $sRootFile );
	$oICWP_Wpsf = ICWP_WPSF_Shield_Security::GetInstance( $oICWP_Wpsf_Controller );
}
catch ( \Exception $oE ) {
	if ( is_admin() ) {
		error_log( 'Perhaps due to a failed upgrade, the Shield plugin failed to load certain component(s) - you should remove the plugin and reinstall.' );
		error_log( $oE->getMessage() );
	}
}