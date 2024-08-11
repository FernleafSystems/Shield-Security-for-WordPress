<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Common\MainWPVO;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class Controller {

	use ExecOnce;
	use PluginControllerConsumer;

	public const MIN_VERSION_MAINWP = '4.1';

	protected function canRun() :bool {
		return true;
	}

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

	public function isServerExtensionLoaded() :bool {
		$extData = self::con()->mwpVO->official_extension_data;
		return !empty( $extData );
	}

	/**
	 * @throws \Exception
	 */
	private function runClientSide() {
		$con = self::con();
		$mwpVO = $con->mwpVO ?? new MainWPVO();
		$mwpVO->is_client = $this->isMainWPChildActive();

		if ( !$mwpVO->is_client ) {
			throw new \Exception( 'MainWP Child not active' );
		}

		( new Client\Actions\Init() )->run();

		$con->mwpVO = $mwpVO;
	}

	/**
	 * @throws \Exception
	 */
	private function runServerSide() {
		$con = self::con();
		$mwpVO = $con->mwpVO ?? new MainWPVO();
		$con->mwpVO = $mwpVO;

		$mwpVO->is_server = false;

		if ( !$this->isMainWPServerActive() ) {
			throw new \Exception( 'MainWP not active' );
		}

		$mwpVO->child_key = ( new Server\Init() )->run();
		$mwpVO->child_file = $con->getRootFile();

		$mwpVO->is_server = true;
	}

	private function isMainWPChildActive() :bool {
		return @\class_exists( '\MainWP\Child\MainWP_Child' );
	}

	private function isMainWPServerActive() :bool {
		return (bool)apply_filters( 'mainwp_activated_check', false );
	}

	public static function isMainWPChildVersionSupported() :bool {
		return \version_compare( \MainWP\Child\MainWP_Child::$version, self::MIN_VERSION_MAINWP, '>=' );
	}

	public static function isMainWPServerVersionSupported() :bool {
		return \defined( 'MAINWP_VERSION' ) && \version_compare( MAINWP_VERSION, self::MIN_VERSION_MAINWP, '>=' );
	}
}