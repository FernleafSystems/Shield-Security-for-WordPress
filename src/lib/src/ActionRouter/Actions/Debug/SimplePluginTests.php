<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Debug;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\BaseAction;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionException;
use FernleafSystems\Wordpress\Plugin\Shield\Modules;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\RunTests;
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
		$this->con()->getModule_License()->getWpHashesTokenManager()
			 ->setCanRequestOverride( true )
			 ->getToken();
	}

	private function dbg_changetrack() {
		$params = [
			'fields'             =>
				[
					0 => 'id',
					1 => 'user_pass',
					2 => 'user_email',
				],
			'number'             => 50,
			'paged'              => 1,
			'capability__not_in' =>
				[
					0 => 'manage_options',
				],
		];
		$args = wp_parse_args(
			$params,
			[
			]
		);

		var_dump( $args );
		var_dump( $params );

		$users = get_users( $args );
		var_dump( $users );
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
}