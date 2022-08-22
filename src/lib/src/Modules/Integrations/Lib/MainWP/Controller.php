<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Common\ExecOnceModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Client;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Common\MainWPVO;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Server;
use FernleafSystems\Wordpress\Services\Services;

class Controller extends ExecOnceModConsumer {

	const MIN_VERSION_MAINWP = '4.1';

	protected function run() {
		try {
			$this->runServerSide();
		}
		catch ( \Exception $e ) {
//			error_log( 'server side exception: '.$e->getMessage() );
		}
		try {
			$this->runClientSide();
		}
		catch ( \Exception $e ) {
//			error_log( 'client side exception: '.$e->getMessage() );
		}
	}

	/**
	 * @throws \Exception
	 */
	private function runClientSide() {
		$con = $this->getCon();
		$mwpVO = $con->mwpVO ?? new MainWPVO();
		$mwpVO->is_client = $this->isMainWPChildActive();

		if ( !$mwpVO->is_client ) {
			throw new \Exception( 'MainWP Child not active' );
		}

		( new Client\Actions\Init() )
			->setMod( $this->getMod() )
			->run();

		$con->mwpVO = $mwpVO;
	}

	/**
	 * @throws \Exception
	 */
	private function runServerSide() {
		$con = $this->getCon();
		$mwpVO = $con->mwpVO ?? new MainWPVO();
		$mwpVO->is_server = false;

		if ( !$this->isMainWPServerActive() ) {
			throw new \Exception( 'MainWP not active' );
		}

		$mwpVO->child_key = ( new Server\Init() )
			->setMod( $this->getMod() )
			->run();
		$mwpVO->child_file = $con->getRootFile();

		$mwpVO->is_server = true;

		$con->mwpVO = $mwpVO;
	}

	private function isMainWPChildActive() :bool {
		return @class_exists( '\MainWP\Child\MainWP_Child' );
	}

	private function isMainWPServerActive() :bool {
		return (bool)apply_filters( 'mainwp_activated_check', false );
	}

	public static function isMainWPChildVersionSupported() :bool {
		return version_compare( \MainWP\Child\MainWP_Child::$version, self::MIN_VERSION_MAINWP, '>=' );
	}

	public static function isMainWPServerVersionSupported() :bool {
		return defined( 'MAINWP_VERSION' )
			   && version_compare( MAINWP_VERSION, self::MIN_VERSION_MAINWP, '>=' );
	}
}