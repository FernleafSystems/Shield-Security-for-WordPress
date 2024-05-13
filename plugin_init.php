<?php

use FernleafSystems\Wordpress\Plugin\Shield\Controller;
use FernleafSystems\Wordpress\Services\Services;

/** @var string $rootFile */
global $oICWP_Wpsf;
if ( isset( $oICWP_Wpsf ) ) {
	error_log( 'Attempting to load the Shield Plugin twice?' );
	return;
}
if ( empty( $rootFile ) ) {
	error_log( 'Attempt to directly access plugin init file.' );
	return;
}

class ICWP_WPSF_Shield_Security {

	/**
	 * @var \ICWP_WPSF_Shield_Security
	 */
	private static $oInstance = null;

	/**
	 * @var Controller\Controller
	 */
	private $con;

	private function __construct( Controller\Controller $controller ) {
		$this->con = $controller;
	}

	/**
	 * @throws \Exception
	 */
	public function start() {
		$this->con->boot();
	}

	public function getController() :Controller\Controller {
		return Controller\Controller::GetInstance();
	}

	/**
	 * @throws \Exception
	 */
	public static function GetInstance( ?Controller\Controller $con = null ) :\ICWP_WPSF_Shield_Security {
		if ( \is_null( self::$oInstance ) ) {
			if ( !$con instanceof Controller\Controller ) {
				throw new \Exception( 'Trying to create a Shield Plugin instance without a valid Controller' );
			}
			self::$oInstance = new self( $con );
		}
		return self::$oInstance;
	}
}

Services::GetInstance();

try {
	$oICWP_Wpsf_Controller = Controller\Controller::GetInstance( $rootFile );
	$oICWP_Wpsf = ICWP_WPSF_Shield_Security::GetInstance( $oICWP_Wpsf_Controller );
	$oICWP_Wpsf->start();
}
catch ( Controller\Exceptions\VersionMismatchException $e ) {
	add_action( 'admin_notices', function () use ( $e ) {
		echo sprintf( '<div class="notice error"><p>%s</p></div>',
			'Shield Security: There appears to be a configuration issue - please reinstall the Shield Security plugin.' );
	} );
}
catch ( Controller\Exceptions\PluginConfigInvalidException $e ) {
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