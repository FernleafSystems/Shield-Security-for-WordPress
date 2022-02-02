<?php

use FernleafSystems\Wordpress\Plugin\Shield;

/** @var string $rootFile */
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
	 * @var Shield\Controller\Controller
	 */
	private $con;

	/**
	 * @param Shield\Controller\Controller $controller
	 */
	private function __construct( Shield\Controller\Controller $controller ) {
		$this->con = $controller;
	}

	public function start() {
		$this->con->loadAllFeatures();
	}

	/**
	 * @throws \Exception
	 */
	public function getController() :Shield\Controller\Controller {
		return Shield\Controller\Controller::GetInstance();
	}

	/**
	 * @return self
	 * @throws \Exception
	 */
	public static function GetInstance( Shield\Controller\Controller $con = null ) {
		if ( is_null( self::$oInstance ) ) {
			if ( !$con instanceof Shield\Controller\Controller ) {
				throw new \Exception( 'Trying to create a Shield Plugin instance without a valid Controller' );
			}
			self::$oInstance = new self( $con );
		}
		return self::$oInstance;
	}
}

try {
	$oICWP_Wpsf_Controller = Shield\Controller\Controller::GetInstance( $rootFile );
	$oICWP_Wpsf = ICWP_WPSF_Shield_Security::GetInstance( $oICWP_Wpsf_Controller );
	$oICWP_Wpsf->start();
}
catch ( \Exception $e ) {
	if ( is_admin() ) {
		error_log( 'Perhaps due to a failed upgrade, the Shield plugin failed to load certain component(s) - you should remove the plugin and reinstall.' );
		error_log( $e->getMessage() );
	}
}