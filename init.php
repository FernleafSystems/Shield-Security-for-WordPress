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

	/**
	 * @throws Exception
	 */
	public function start() {
		$this->con->boot();
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
catch ( Shield\Controller\Exceptions\VersionMismatchException $e ) {
	add_action( 'admin_notices', function () use ( $e ) {
		echo sprintf( '<div class="notice error"><p>%s</p></div>',
			'Shield Security: There appears to be a configuration issue - please reinstall the Shield Security plugin.' );
	} );
}
catch ( Shield\Controller\Exceptions\PluginConfigInvalidException $e ) {
	add_action( 'admin_notices', function () use ( $e ) {
		echo sprintf( '<div class="notice error"><p>%s</p><p>%s</p></div>',
			'Shield Security: Could not load the plugin modules configuration. Please refresh and if the problem persists, please reinstall the Shield plugin.',
			$e->getMessage()
		);
	} );
}
catch ( \Exception $e ) {
	error_log( 'Perhaps due to a failed upgrade, the Shield plugin failed to load certain component(s) - you should remove the plugin and reinstall.' );
	error_log( $e->getMessage() );
}