<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Debug;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\BaseAction;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\SecurityAdminRequired;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionException;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\{
	AuditTrail,
	Events,
	Plugin
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\RunTests;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Integrations\WpHashes\Verify\Email;
use FernleafSystems\Wordpress\Services\Utilities\Net\IpID;

class SimplePluginTests extends BaseAction {

	use SecurityAdminRequired;

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

	private function dbg_eventsSum() {
		$dbhEvents = self::con()->getModule_Events()->getDbH_Events();
		/** @var Events\DB\Event\Ops\Select $select */
		$select = $dbhEvents->getQuerySelector();
		$res = $select->filterByBoundary( 1692677238, Services::Request()->carbon()->timestamp )
					  ->sumEventsSeparately( \array_keys( ( new Events\Lib\EventsParser() )->wordpress() ) );
		var_dump( $res );
	}

	private function dbg_db() {
		$column = 'data';
		$schema = self::con()->getModule_AuditTrail()->getDbH_Snapshots()->getTableSchema();
		$state = Services::WpDb()->selectCustom( sprintf( 'DESCRIBE %s', $schema->table ) );
		$def = $schema->getColumnDef( $column );

		foreach ( $state as $columnState ) {
			if ( \strtolower( $columnState[ 'Field' ] ?? '' ) === $column && !empty( $columnState[ 'Type' ] ) ) {
				if ( \strtolower( $columnState[ 'Type' ] ) !== $def[ 'type' ] ) {
					throw new \Exception( 'Column type is different.' );
				}
			}
		}

		var_dump( $def );
		var_dump( $state );
	}

	private function dbg_snapshots() {
		$audCon = self::con()->getModule_AuditTrail()->getAuditCon();
		$slug = AuditTrail\Auditors\Comments::Slug();
		try {
			$current = ( new AuditTrail\Lib\Snapshots\Ops\Build() )->run( $slug );
			var_dump( $current );
			$audCon->updateStoredSnapshot( $audCon->getAuditors()[ $slug ], $current );
			var_dump( $audCon->getSnapshot( $slug )->data );
		}
		catch ( \Exception $e ) {
			var_dump( $e->getMessage() );
		}
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
		self::con()->getModule_License()->getWpHashesTokenManager()
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
			( new Plugin\Lib\ImportExport\NotifyWhitelist() )->execute();
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

	private function dbg_plugin_tests() {
		( new RunTests() )->run();
	}

	private function dbg_telemetry() {
		( new Plugin\Lib\PluginTelemetry() )->collectAndSend( true );
	}
}