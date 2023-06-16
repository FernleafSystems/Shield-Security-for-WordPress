<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Debug;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\BaseAction;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionException;
use FernleafSystems\Wordpress\Plugin\Shield\Modules;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Scans\LocateNeedles;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Utilities\MalwareScanPatterns;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\RunTests;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Integrations\WpHashes\Malai\MalwareScan;
use FernleafSystems\Wordpress\Services\Utilities\Integrations\WpHashes\Verify\Email;
use FernleafSystems\Wordpress\Services\Utilities\Net\IpID;

class SimplePluginTests extends BaseAction {

	public const SLUG = 'debug_simple_plugin_tests';

	protected function exec() {
		$testMethod = $this->action_data[ 'test' ];
		if ( !\method_exists( $this, $testMethod ) ) {
			throw new ActionException( sprintf( 'There is no test method: %s', $testMethod ) );
		}
		\ob_start();
		$this->{$testMethod}();
		$this->response()->action_response_data = [
			'debug_output' => \ob_get_clean()
		];
	}

	protected function postExec() {
		var_dump( $this->response()->action_response_data[ 'debug_output' ] );
		die( 'end tests' );
	}

	private function dbg_ipid() {
		try {
			$id = ( new IpID( '207.46.13.207', 'Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)' ) )->run();
			var_dump( $id );
		}
		catch ( \Exception $e ) {
			var_dump( $e->getMessage() );
		}
	}

	private function dbg_apitoken() {
		$this->con()->getModule_License()->getWpHashesTokenManager()->setCanRequestOverride( true )->getToken();
	}

	private function dbg_changetrack() {
		$mod = $this->con()->getModule_AuditTrail();
		$dbh = $mod->getDbH_Logs();
		$loader = new Modules\AuditTrail\DB\LoadLogs();
		$loader->wheres = [
			sprintf( "`log`.`event_slug` IN ('%s')", implode( "','", [
				'plugin_activated',
				'plugin_deactivated',
				'plugin_installed',
				'plugin_upgraded',
			] ) ),
		];
		$logs = $loader->run();
		var_dump( $logs );
	}

	private function dbg_submitmalwarereports() {
		$FS = Services::WpFs();

//		$path = path_join( ABSPATH, 'wp-includes/class-wp-query.php' );
//		$status = ( new MalwareScan() )->scan( basename( $path ), $FS->getFileContent( $path ), 'php' );
//
//		var_dump($status);
//		die();

		$count = 0;
		foreach ( $FS->getFilesInDir( ABSPATH, 0 ) as $splFile ) {
			$path = $splFile->getPathname();
			if ( \str_ends_with( $path, '.php' ) ) {
				$status = ( new MalwareScan() )->scan( basename( $path ), $FS->getFileContent( $path ), 'php' );
				var_dump( $status.': '.str_replace( ABSPATH, '', $path ) );
				$count++;
			}
			if ( $count > 10 ) {
				break;
			}
		}

		die();
		$patterns = ( new MalwareScanPatterns() )->retrieve();

		$locator = ( new LocateNeedles() )->setPath( path_join( ABSPATH, 'wp-content/maltestxyz.php' ) );
		foreach ( $patterns[ 'raw' ] as $sig ) {
			if ( $locator->raw( $sig ) ) {
				var_dump( $sig );
			}
		}
		foreach ( $patterns[ 'iraw' ] as $sig ) {
			if ( $locator->iRaw( $sig ) ) {
				var_dump( $sig );
			}
		}
		foreach ( $patterns[ 're' ] as $sig ) {
			if ( $locator->regex( $sig ) ) {
				var_dump( $sig );
			}
		}

//		var_dump( ( new Patterns() )->retrieve() );

//		( new ReportToMalai() )->run();

//		$res = ( new QueryMalwareStatus() )->retrieve( '405558D45DAC03062A76FFE384DDC3DD8ED7FC3B5932E100791AEF8F8E5C5D7E' );
//		$reports = ( new ReportToMalai() )->run( 20 );
//		var_dump( $reports );
	}

	private function dbg_importnotify() {
		try {
			( new Modules\Plugin\Lib\ImportExport\NotifyWhitelist() )->execute();
		}
		catch ( \Exception $e ) {
			var_dump( $e->getMessage() );
		}
	}

	private function dbg_emailverify() {
		try {
			var_dump( ( new Email() )->getEmailVerification( 'paul@asdf.co.adf' ) );
		}
		catch ( \Exception $e ) {
			var_dump( $e->getMessage() );
		}
	}

	private function dbg_reporting() {
		try {
			echo ( new Modules\Plugin\Lib\Reporting\ReportGenerator() )->adHoc();
		}
		catch ( \Exception $e ) {
			var_dump( $e->getMessage() );
		}
	}

	private function dbg_plugin_tests() {
		( new RunTests() )->run();
	}

	private function dbg_telemetry() {
		( new Modules\Plugin\Lib\PluginTelemetry() )->collectAndSend( true );
	}

	private function crowdsec() {
		$modIPs = $this->con()->getModule_IPs();
		$csCon = $modIPs->getCrowdSecCon();
		$API = $csCon->getApi();

//		$auth = $API->getCsAuth();
//		var_dump($auth);

		try {
//			$res = $this->con()
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
//				->setMod( $this->con()->getModule_IPs() )
//				->execute();
			( new Modules\IPs\Lib\CrowdSec\Decisions\ImportDecisions() )->runImport();
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