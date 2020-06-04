<?php

use FernleafSystems\Wordpress\Plugin\Shield;

/** @var string $sRootFile */
global $oICWP_Wpsf;

if ( isset( $oICWP_Wpsf ) ) {
	error_log( 'Attempting to load the Shield Plugin twice?' );
	return;
}

class ICWP_WPSF_Shield_Security {

	/**
	 * @var ICWP_WPSF_Shield_Security
	 */
	private static $oInstance = null;

	/**
	 * @param Shield\Controller\Controller $oController
	 */
	private function __construct( Shield\Controller\Controller $oController ) {
		$oController->loadAllFeatures();
	}

	/**
	 * @return Shield\Controller\Controller
	 * @throws \Exception
	 */
	public function getController() {
		return Shield\Controller\Controller::GetInstance();
	}

	/**
	 * @param Shield\Controller\Controller $oController
	 * @return self
	 * @throws \Exception
	 */
	public static function GetInstance( Shield\Controller\Controller $oController = null ) {
		if ( is_null( self::$oInstance ) ) {
			if ( !$oController instanceof Shield\Controller\Controller ) {
				throw new \Exception( 'Trying to create a Shield Plugin instance without a valid Controller' );
			}
			self::$oInstance = new self( $oController );
		}
		return self::$oInstance;
	}
}

try {
	$oICWP_Wpsf_Controller = Shield\Controller\Controller::GetInstance( $sRootFile );
	$oICWP_Wpsf = ICWP_WPSF_Shield_Security::GetInstance( $oICWP_Wpsf_Controller );
}
catch ( \Exception $oE ) {
	if ( is_admin() ) {
		error_log( 'Perhaps due to a failed upgrade, the Shield plugin failed to load certain component(s) - you should remove the plugin and reinstall.' );
		error_log( $oE->getMessage() );
	}
}

if ( !function_exists( 'shield_security_get_plugin' ) ) {
	/**
	 * @return ICWP_WPSF_Shield_Security|null
	 */
	function shield_security_get_plugin() {
		global $oICWP_Wpsf;
		return ( $oICWP_Wpsf instanceof \ICWP_WPSF_Shield_Security ) ? $oICWP_Wpsf : null;
	}
}
