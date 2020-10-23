<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Integrations\MainWP;

use FernleafSystems\Wordpress\Plugin\Shield\Integrations\MainWP\Server;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class Controller {

	use PluginControllerConsumer;

	private $childKey;

	public function run() {
		try {
			$this->runServerSide();
		}
		catch ( \Exception $e ) {
			try {
				$this->runClientSide();
			}
			catch ( \Exception $e ) {
				$this->setCon( null );
			}
		}
	}

	/**
	 * @throws \Exception
	 */
	private function runClientSide() {
	}

	/**
	 * @throws \Exception
	 */
	private function runServerSide() {
		if ( !$this->isMainWPServerActive() ) {
			throw new \Exception( 'MainWP not active' );
		}
		$this->childKey = ( new Server\Init() )
			->setCon( $this->getCon() )
			->run();
	}

	private function isMainWPServerActive() :bool {
		return (bool)apply_filters( 'mainwp_activated_check', false );
	}
}
