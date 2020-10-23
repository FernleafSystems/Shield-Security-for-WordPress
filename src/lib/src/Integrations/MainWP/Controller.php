<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Integrations\MainWP;

use FernleafSystems\Wordpress\Plugin\Shield\Integrations\MainWP\Common\MainWPVO;
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
		}
		try {
			$this->runClientSide();
		}
		catch ( \Exception $e ) {
		}
	}

	/**
	 * @throws \Exception
	 */
	private function runClientSide() {
		$con = $this->getCon();
		$mwpVO = $con->mwpVO ?? new MainWPVO();
		$mwpVO->is_client = false;
		$con->mwpVO = $mwpVO;
	}

	/**
	 * @throws \Exception
	 */
	private function runServerSide() {
		$con = $this->getCon();
		$mwpVO = new MainWPVO();
		$mwpVO->is_server = false;

		if ( !$this->isMainWPServerActive() ) {
			throw new \Exception( 'MainWP not active' );
		}

		$mwpVO->child_key = ( new Server\Init() )
			->setCon( $con )
			->run();

		$mwpVO->is_server = true;

		$con->mwpVO = $mwpVO;
	}

	private function isMainWPServerActive() :bool {
		return (bool)apply_filters( 'mainwp_activated_check', false );
	}
}