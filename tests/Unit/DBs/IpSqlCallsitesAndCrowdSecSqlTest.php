<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\DBs;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ActivityLogs\LoadLogs;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\BotSignal\LoadBotSignalRecords;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\Common\{
	IpAddressSql,
	SqlBackend
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\BotSignalsRecord;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec\Decisions\Scopes\V1\ProcessIPs as ProcessIPsV1;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec\Decisions\Scopes\V2\ProcessIPs as ProcessIPsV2;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\Traffic\BuildTrafficTableData;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore;
use FernleafSystems\Wordpress\Services\Core\{
	Db,
	Request
};
use FernleafSystems\Wordpress\Services\Services;

class IpSqlCallsitesAndCrowdSecSqlTest extends BaseUnitTest {

	private $origServiceItems;

	private $origServices;

	private $origWpdb;

	protected function setUp() :void {
		parent::setUp();
		$this->origServiceItems = $this->getServicesProperty( 'items' )->getValue();
		$this->origServices = $this->getServicesProperty( 'services' )->getValue();
		global $wpdb;
		$this->origWpdb = $wpdb ?? null;
	}

	protected function tearDown() :void {
		IpAddressSql::resetForTests();
		SqlBackend::resetForTests();
		PluginStore::$plugin = null;
		$this->getServicesProperty( 'items' )->setValue( null, $this->origServiceItems );
		$this->getServicesProperty( 'services' )->setValue( null, $this->origServices );
		global $wpdb;
		$wpdb = $this->origWpdb;
		parent::tearDown();
	}

	public function testLoadLogsCountAllSwitchesBackendExpressions() :void {
		$this->installController();
		$db = new CapturingDb();
		$this->injectServices( $db );

		$loader = ( new LoadLogs() )->setIP( '127.0.0.1' );

		SqlBackend::setSqliteOverrideForTests( false );
		$loader->countAll();
		$mysqlSql = $db->lastGetVarSql;

		SqlBackend::setSqliteOverrideForTests( true );
		$loader->countAll();
		$sqliteSql = $db->lastGetVarSql;

		$this->assertStringContainsString( "ips.ip=INET6_ATON('127.0.0.1')", $mysqlSql );
		$this->assertStringContainsString( "ips.ip=X'7f000001'", $sqliteSql );
		$this->assertStringNotContainsString( 'INET6_ATON(', $sqliteSql );
	}

	public function testLoadBotSignalRecordsSelectRawSwitchesBackendExpressions() :void {
		$this->installController();
		$db = new CapturingDb();
		$this->injectServices( $db );

		$loader = ( new LoadBotSignalRecords() )->setIP( '127.0.0.1' );
		$method = new \ReflectionMethod( $loader, 'selectRaw' );
		$method->setAccessible( true );

		SqlBackend::setSqliteOverrideForTests( false );
		$method->invoke( $loader );
		$mysqlSql = $db->lastSelectRowSql;

		SqlBackend::setSqliteOverrideForTests( true );
		$method->invoke( $loader );
		$sqliteSql = $db->lastSelectRowSql;

		$this->assertStringContainsString( "`ips`.`ip`=INET6_ATON('127.0.0.1')", $mysqlSql );
		$this->assertStringContainsString( "`ips`.`ip`=X'7f000001'", $sqliteSql );
		$this->assertStringNotContainsString( 'INET6_ATON(', $sqliteSql );
	}

	public function testBotSignalsRecordRetrieveNotBotAtSwitchesBackendExpressions() :void {
		$this->installController();
		$db = new CapturingDb();
		$this->injectServices( $db );

		$record = ( new BotSignalsRecord() )->setIP( '127.0.0.1' );

		SqlBackend::setSqliteOverrideForTests( false );
		$record->retrieveNotBotAt();
		$mysqlSql = $db->lastGetVarSql;

		SqlBackend::setSqliteOverrideForTests( true );
		$record->retrieveNotBotAt();
		$sqliteSql = $db->lastGetVarSql;

		$this->assertStringContainsString( "`ips`.`ip`=INET6_ATON('127.0.0.1')", $mysqlSql );
		$this->assertStringContainsString( "`ips`.`ip`=X'7f000001'", $sqliteSql );
		$this->assertStringNotContainsString( 'INET6_ATON(', $sqliteSql );
	}

	public function testBuildTrafficWhereSwitchesBackendExpressions() :void {
		$builder = new class extends BuildTrafficTableData {
			public function __construct() {
				$this->table_data = [
					'searchPanes' => [ 'ip' => [ '127.0.0.1' ] ],
				];
			}

			protected function parseSearchText() :array {
				return [
					'remaining'  => '',
					'ip'         => '',
					'request_id' => '',
					'user_id'    => '',
					'user_name'  => '',
					'user_email' => '',
				];
			}

			public function exposeBuildWheresFromSearchParams() :array {
				return $this->buildWheresFromSearchParams();
			}
		};

		SqlBackend::setSqliteOverrideForTests( false );
		$mysqlWheres = $builder->exposeBuildWheresFromSearchParams();

		SqlBackend::setSqliteOverrideForTests( true );
		$sqliteWheres = $builder->exposeBuildWheresFromSearchParams();

		$this->assertContains( "`ips`.ip=INET6_ATON('127.0.0.1')", $mysqlWheres );
		$this->assertContains( "`ips`.ip=X'7f000001'", $sqliteWheres );
	}

	public function testLoadLogsCountAllDetectsSqliteFromWpdbDbhWithoutOverride() :void {
		$this->installController();
		$db = new CapturingDb();
		$this->injectServices( $db );

		$dbhClass = $this->ensureGlobalClass( 'WP_SQLite_Translator_Callsites_Alias' );
		global $wpdb;
		$wpdb = (object)[
			'dbh' => new $dbhClass(),
		];
		SqlBackend::resetForTests();

		( new LoadLogs() )
			->setIP( '127.0.0.1' )
			->countAll();

		$this->assertStringContainsString( "ips.ip=X'7f000001'", $db->lastGetVarSql );
		$this->assertStringNotContainsString( 'INET6_ATON(', $db->lastGetVarSql );
	}

	public function testLoadLogsCountAllDoesNotDetectSqliteFromArbitraryDbhClassWithoutOverride() :void {
		$this->installController();
		$db = new CapturingDb();
		$this->injectServices( $db );

		$dbhClass = $this->ensureGlobalClass( 'AcmeSqliteDbhCallsitesAlias' );
		global $wpdb;
		$wpdb = (object)[
			'dbh' => new $dbhClass(),
		];
		SqlBackend::resetForTests();

		( new LoadLogs() )
			->setIP( '127.0.0.1' )
			->countAll();

		$this->assertStringContainsString( "ips.ip=INET6_ATON('127.0.0.1')", $db->lastGetVarSql );
		$this->assertStringNotContainsString( "ips.ip=X'7f000001'", $db->lastGetVarSql );
	}

	public function testCrowdsecV1AndV2GenerateValidSqlForMixedValidInvalidIpsInSqliteMode() :void {
		$this->installController();
		$decisions = [
			'127.0.0.1' => [ 'expires_at' => 2000 ],
			'not-an-ip' => [ 'expires_at' => 3000 ],
		];

		SqlBackend::setSqliteOverrideForTests( true );

		$dbV1 = new CapturingDb();
		$this->injectServices( $dbV1 );
		( new class extends ProcessIPsV1 {
			public function exposeProcessNew( array $newDecisions ) :int {
				$this->newDecisions = $newDecisions;
				return $this->processNew();
			}

			protected function removeDuplicatesFromNewStream() {
			}
		} )->exposeProcessNew( $decisions );

		$this->assertNotEmpty( $dbV1->doSqlQueries );
		$this->assertNotEmpty( $dbV1->selectCustomQueries );
		$this->assertStringContainsString( "X'7f000001'", $dbV1->doSqlQueries[ 0 ] );
		$this->assertStringContainsString( 'NULL', $dbV1->doSqlQueries[ 0 ] );
		$this->assertStringNotContainsString( ',,', $dbV1->doSqlQueries[ 0 ] );
		$this->assertStringContainsString( "IN (X'7f000001', NULL)", $dbV1->selectCustomQueries[ 0 ] );

		$dbV2 = new CapturingDb();
		$this->injectServices( $dbV2 );
		( new class extends ProcessIPsV2 {
			public function exposeProcessNew( array $newDecisions ) :int {
				$this->newDecisions = $newDecisions;
				return $this->processNew();
			}

			protected function removeDuplicatesFromNewStream() {
			}
		} )->exposeProcessNew( $decisions );

		$this->assertNotEmpty( $dbV2->doSqlQueries );
		$this->assertNotEmpty( $dbV2->selectCustomQueries );
		$this->assertStringContainsString( "X'7f000001'", $dbV2->doSqlQueries[ 0 ] );
		$this->assertStringContainsString( 'NULL', $dbV2->doSqlQueries[ 0 ] );
		$this->assertStringNotContainsString( ',,', $dbV2->doSqlQueries[ 0 ] );
		$this->assertStringContainsString( "IN (X'7f000001',NULL)", $dbV2->selectCustomQueries[ 0 ] );
	}

	private function installController() :void {
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->db_con = (object)[
			'activity_logs' => $this->buildTableAccessor( 'wp_icwp_wpsf_activity_logs' ),
			'req_logs'      => $this->buildTableAccessor( 'wp_icwp_wpsf_req_logs' ),
			'ips'           => $this->buildTableAccessor( 'wp_icwp_wpsf_ips' ),
			'bot_signals'   => $this->buildTableAccessor( 'wp_icwp_wpsf_bot_signals' ),
			'ip_rules'      => $this->buildTableAccessor( 'wp_icwp_wpsf_ip_rules' ),
		];

		PluginStore::$plugin = new class( $controller ) {
			private Controller $controller;

			public function __construct( Controller $controller ) {
				$this->controller = $controller;
			}

			public function getController() :Controller {
				return $this->controller;
			}
		};
	}

	private function buildTableAccessor( string $table ) :object {
		return new class( $table ) {
			private string $table;

			public function __construct( string $table ) {
				$this->table = $table;
			}

			public function getTable() :string {
				return $this->table;
			}

			public function getTableSchema() {
				return (object)[
					'table' => $this->table,
				];
			}
		};
	}

	private function injectServices( Db $db ) :void {
		$this->getServicesProperty( 'items' )->setValue( null, [
			'service_wpdb'    => $db,
			'service_request' => new class extends Request {
				public function __construct() {
				}

				public function ts( bool $update = true ) :int {
					return 1771498280;
				}
			},
		] );
		$this->getServicesProperty( 'services' )->setValue( null, null );
	}

	private function getServicesProperty( string $propertyName ) :\ReflectionProperty {
		$reflection = new \ReflectionClass( Services::class );
		$property = $reflection->getProperty( $propertyName );
		$property->setAccessible( true );
		return $property;
	}

	private function ensureGlobalClass( string $className ) :string {
		$fqn = '\\'.$className;
		if ( !\class_exists( $fqn, false ) ) {
			eval( \sprintf( 'namespace { class %s {} }', $className ) );
		}
		return $fqn;
	}
}

class CapturingDb extends Db {

	public string $lastSelectCustomSql = '';

	public string $lastSelectRowSql = '';

	public string $lastGetVarSql = '';

	public array $doSqlQueries = [];

	public array $selectCustomQueries = [];

	public array $selectRowQueries = [];

	public array $getVarQueries = [];

	public array $selectCustomResponse = [];

	public array $selectRowResponse = [];

	public $getVarResponse = 0;

	public function doSql( string $sqlQuery ) {
		$this->doSqlQueries[] = $sqlQuery;
		return 1;
	}

	public function selectCustom( $query, $format = null ) {
		$this->lastSelectCustomSql = (string)$query;
		$this->selectCustomQueries[] = (string)$query;
		return $this->selectCustomResponse;
	}

	public function selectRow( string $query, $format = null ) {
		$this->lastSelectRowSql = $query;
		$this->selectRowQueries[] = $query;
		return $this->selectRowResponse;
	}

	public function getVar( $sql ) {
		$this->lastGetVarSql = (string)$sql;
		$this->getVarQueries[] = (string)$sql;
		return $this->getVarResponse;
	}

	public function getMysqlServerInfo() :string {
		return '8.0.36';
	}
}
