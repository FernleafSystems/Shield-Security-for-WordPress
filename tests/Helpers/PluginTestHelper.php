<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use Symfony\Component\Filesystem\Path;

class PluginTestHelper {

	private static ?Controller $controller = null;
	private DebugLogger $logger;

	public function __construct() {
		$this->logger = new DebugLogger();
	}

	public function getController() :?Controller {
		if ( self::$controller === null ) {
			$this->logger->debug( 'Initializing Shield Controller for tests' );
			
			// Ensure plugin is loaded
			if ( !defined( 'ICWP_WPSF_FULL_PATH' ) ) {
				define( 'ICWP_WPSF_FULL_PATH', Path::join( dirname( __DIR__, 2 ), 'icwp-wpsf.php' ) );
			}

			// Initialize controller
			try {
				self::$controller = Controller::GetInstance();
				$this->logger->debug( 'Shield Controller initialized successfully' );
			}
			catch ( \Exception $e ) {
				$this->logger->error( 'Failed to initialize Shield Controller: '.$e->getMessage() );
			}
		}

		return self::$controller;
	}

	public function getModuleCon( string $moduleSlug ) {
		$controller = $this->getController();
		if ( $controller ) {
			return $controller->getModule( $moduleSlug );
		}
		return null;
	}

	public function isPluginLoaded() :bool {
		return self::$controller !== null;
	}

	public function resetController() :void {
		self::$controller = null;
		$this->logger->debug( 'Shield Controller reset' );
	}
}
