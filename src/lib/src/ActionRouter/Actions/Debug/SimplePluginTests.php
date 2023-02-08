<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Debug;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\BaseAction;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionException;
use FernleafSystems\Wordpress\Plugin\Shield\Modules;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\RunTests;

class SimplePluginTests extends BaseAction {

	public const SLUG = 'debug_simple_plugin_tests';

	protected function exec() {
		$testMethod = $this->action_data[ 'test' ];
		if ( !method_exists( $this, $testMethod ) ) {
			throw new ActionException( sprintf( 'There is no test method: %s', $testMethod ) );
		}
		ob_start();
		$this->{$testMethod}();
		$this->response()->action_response_data = [
			'debug_output' => ob_get_clean()
		];
	}

	protected function postExec() {
		var_dump( $this->response()->action_response_data[ 'debug_output' ] );
		die( 'end tests' );
	}

	private function dbg_reporting() {
		try {
			echo ( new Modules\Plugin\Lib\Reporting\ReportGenerator() )
				->setMod( $this->getCon()->getModule_Plugin() )
				->adHoc();
		}
		catch ( \Exception $e ) {
			var_dump( $e->getMessage() );
		}
	}

	private function dbg_plugin_tests() {
		( new RunTests() )
			->setCon( $this->getCon() )
			->run();
	}

	private function dbg_telemetry() {
		( new Modules\Plugin\Lib\PluginTelemetry() )
			->setMod( $this->getCon()->getModule_Plugin() )
			->collectAndSend( true );
	}

	private function crowdsec() {
		$modIPs = $this->getCon()->getModule_IPs();
		$csCon = $modIPs->getCrowdSecCon();
		$API = $csCon->getApi();

//		$auth = $API->getCsAuth();
//		var_dump($auth);

		try {
//			$res = $this->getCon()
//						->getModule_License()
//						->getLicenseHandler()
//						->getLicense()->crowdsec[ 'scenarios' ] ?? [];

			error_log( 'memory: '.round( memory_get_usage()/1024/1024 ) );
			var_dump( 'api ready: '.var_export( $API->isReady(), true ) );
//			$res = ( new DecisionsDownload( $api->getAuthorizationToken(), 'crowdsec/1.2.1' ) )->run();

//			var_dump( $modIPs->getOptions()->getOpt('crowdsec_cfg') );
//			var_dump( $csCon->getApi()->getAuthStatus() );
//			var_dump( $csCon->getApi()->getAuthorizationToken() );
//			$csCon->getApi()->machineEnroll( false );
//			var_dump( $csCon->getApi()->getAuthStatus() );
//			var_dump( $modIPs->getCrowdSecCon()->cfg );
//			var_dump( $csCon->getApi()->getAuthorizationToken() );
//			( new Modules\IPs\Lib\CrowdSec\Signals\PushSignalsToCS() )
//				->setMod( $this->getCon()->getModule_IPs() )
//				->execute();
			( new Modules\IPs\Lib\CrowdSec\Decisions\ImportDecisions() )
				->setMod( $modIPs )
				->runImport();
//			var_dump( $d );
//			$res = ( new Modules\IPs\Lib\CrowdSec\Api\DecisionsDownload(
//				$csCon->getApi()->getAuthorizationToken(),
//				$csCon->getApi()->getApiUserAgent()
//			) )->run();
//			$res = ( new RetrieveScenarios() )
//				->setMod( $this->getMod() )
//				->retrieve();
//			$res = ( new DecisionsDownload( $csCon->getApi()->getAuthorizationToken() ) )->run();
		}
		catch ( \Exception $e ) {
			var_dump( $e->getMessage() );
		}
		var_dump( $res ?? 'unset' );
		var_dump( 'ready: '.var_export( $API->isReady(), true ) );
	}
}