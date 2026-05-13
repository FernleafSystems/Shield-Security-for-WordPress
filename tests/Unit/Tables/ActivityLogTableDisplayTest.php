<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Tables;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ActivityLogs\LogRecord;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\ActivityLog\BuildActivityLogTableData;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\Investigation\InvestigationActivityLogTableData;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState,
	UnitTestGeneral,
	UnitTestIpUtils,
	UnitTestPluginUrls,
	UnitTestRequest,
	UnitTestSvgs
};
use FernleafSystems\Wordpress\Services\Core\Users;
use FernleafSystems\Wordpress\Services\Utilities\Net\IpID;

class ActivityLogTableDisplayTest extends BaseUnitTest {

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();

		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( 'esc_html' )->alias( static fn( $text ) :string => \htmlspecialchars( (string)$text, \ENT_QUOTES ) );
		Functions\when( 'esc_attr' )->alias( static fn( $text ) :string => \htmlspecialchars( (string)$text, \ENT_QUOTES ) );
		Functions\when( 'esc_url' )->alias( static fn( $text ) :string => (string)$text );
		Functions\when( 'sanitize_textarea_field' )->alias( static fn( $text ) :string => (string)$text );

		$this->installControllerStub();
		$this->servicesSnapshot = ServicesState::snapshot();
		ServicesState::installItems( [
			'service_ip'        => new UnitTestIpUtils(),
			'service_request'   => new UnitTestRequest( [], '127.0.0.1', 1700000000 ),
			'service_wpgeneral' => new UnitTestGeneral( '/admin-ajax.php', 'display:' ),
			'service_wpusers'   => new class extends Users {
				public function getAdminUrl_ProfileEdit( $user = null ) :string {
					$uid = \is_object( $user ) && isset( $user->ID ) ? (int)$user->ID : 0;
					return '/wp-admin/user-edit.php?user_id='.$uid;
				}
			},
		] );
	}

	protected function tearDown() :void {
		ServicesState::restore( $this->servicesSnapshot );
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_activity_identity_uses_badges_for_visitor_ip_raw_ip_and_user() :void {
		$row = $this->buildRow(
			$this->createBuilder(
				[ '2001:db8::1|' => [ IpID::VISITOR, 'Visitor' ] ],
				[ 7 => $this->makeUser( 7, 'admin-user' ) ]
			),
			$this->makeLogRecord( '2001:db8::1', [ 'uid' => '7' ] )
		);

		$identity = $row[ 'identity' ];
		$this->assertStringContainsString( 'activity-log-identity__badge', $identity );
		$this->assertStringContainsString( 'Your IP', $identity );
		$this->assertStringContainsString( 'data-ip="2001:db8::1"', $identity );
		$this->assertStringContainsString( '>2001:db8::1</a>', $identity );
		$this->assertStringContainsString( 'admin-user', $identity );
		$this->assertStringNotContainsString( 'Your Current IP', $identity );
		$this->assertStringNotContainsString( 'and authenticated as', $identity );
		$this->assertStringNotContainsString( 'and not authenticated', $identity );
	}

	public function test_activity_identity_uses_service_identity_badge_without_unauthenticated_filler() :void {
		$row = $this->buildRow(
			$this->createBuilder( [
				'203.0.113.44|' => [ 'google', 'Googlebot' ],
			] ),
			$this->makeLogRecord( '203.0.113.44' )
		);

		$identity = $row[ 'identity' ];
		$this->assertStringContainsString( 'Googlebot', $identity );
		$this->assertStringContainsString( '203.0.113.44', $identity );
		$this->assertStringNotContainsString( 'and authenticated as', $identity );
		$this->assertStringNotContainsString( 'and not authenticated', $identity );
	}

	public function test_activity_identity_renders_known_actor_badge() :void {
		$row = $this->buildRow(
			$this->createBuilder( [
				'203.0.113.60|' => [ IpID::UNKNOWN, 'Unknown' ],
			] ),
			$this->makeLogRecord( '203.0.113.60', [ 'uid' => 'cron' ] )
		);

		$identity = $row[ 'identity' ];
		$this->assertStringContainsString( 'WP Cron', $identity );
		$this->assertStringContainsString( 'activity-log-identity__badge--user', $identity );
		$this->assertStringNotContainsString( 'and authenticated as', $identity );
		$this->assertStringNotContainsString( 'and not authenticated', $identity );
	}

	public function test_activity_identity_suppresses_unknown_identity_badge_but_keeps_raw_ip() :void {
		$row = $this->buildRow(
			$this->createBuilder( [
				'198.51.100.20|' => [ IpID::UNKNOWN, 'Unknown' ],
			] ),
			$this->makeLogRecord( '198.51.100.20' )
		);

		$identity = $row[ 'identity' ];
		$this->assertStringContainsString( '198.51.100.20', $identity );
		$this->assertStringNotContainsString( 'Unknown', $identity );
		$this->assertStringNotContainsString( 'Unidentified', $identity );
	}

	public function test_activity_identity_keeps_actor_badge_when_ip_is_missing() :void {
		$row = $this->buildRow(
			$this->createBuilder( [] ),
			$this->makeLogRecord( '', [ 'uid' => 'cron' ] )
		);

		$identity = $row[ 'identity' ];
		$this->assertStringContainsString( 'No IP', $identity );
		$this->assertStringContainsString( 'WP Cron', $identity );
		$this->assertStringContainsString( 'activity-log-identity__badge--user', $identity );
		$this->assertStringNotContainsString( 'and authenticated as', $identity );
		$this->assertStringNotContainsString( 'and not authenticated', $identity );
	}

	public function test_activity_date_displays_relative_time_with_full_timestamp_tooltip() :void {
		$row = $this->buildRow(
			$this->createBuilder( [
				'198.51.100.20|' => [ IpID::UNKNOWN, 'Unknown' ],
			] ),
			$this->makeLogRecord( '198.51.100.20', [], 1713278100 )
		);

		$date = $row[ 'created_since' ];
		$this->assertStringContainsString( 'class="activity-log-date"', $date );
		$this->assertStringContainsString( 'data-bs-toggle="tooltip"', $date );
		$this->assertStringContainsString( 'data-bs-title="display:1713278100"', $date );
		$this->assertStringNotContainsString( '<br', $date );
		$this->assertStringNotContainsString( '<small', $date );
	}

	public function test_investigation_activity_table_inherits_shared_identity_and_date_display() :void {
		$row = $this->buildRow(
			$this->createInvestigationBuilder( [
				'203.0.113.7|' => [ IpID::THIS_SERVER, 'Server' ],
			] ),
			$this->makeLogRecord( '203.0.113.7', [], 1713278100 )
		);

		$this->assertStringContainsString( 'This Server', $row[ 'identity' ] );
		$this->assertStringContainsString( 'activity-log-identity__badge', $row[ 'identity' ] );
		$this->assertStringContainsString( 'data-bs-title="display:1713278100"', $row[ 'created_since' ] );
	}

	private function buildRow( BuildActivityLogTableData $builder, LogRecord $record ) :array {
		return $builder->exportBuildTableRowsFromRawRecords( [ $record ] )[ 0 ];
	}

	private function createBuilder( array $identityResults, array $users = [] ) :BuildActivityLogTableData {
		return new class( $identityResults, $users ) extends BuildActivityLogTableData {
			use ActivityLogTableDisplayBuilderOverrides;
		};
	}

	private function createInvestigationBuilder( array $identityResults, array $users = [] ) :InvestigationActivityLogTableData {
		return new class( $identityResults, $users ) extends InvestigationActivityLogTableData {
			use ActivityLogTableDisplayBuilderOverrides;
		};
	}

	private function makeLogRecord( string $ip, array $metaData = [], int $timestamp = 1713278000 ) :LogRecord {
		$record = new LogRecord();
		$record->event_slug = 'test_event';
		$record->ip = $ip;
		$record->rid = 'req-test';
		$record->created_at = $timestamp;
		$record->updated_at = $timestamp;
		$record->meta_data = $metaData;
		return $record;
	}

	private function makeUser( int $id, string $login ) :object {
		return (object)[
			'ID'         => $id,
			'user_login' => $login,
		];
	}

	private function installControllerStub() :void {
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->plugin_urls = new UnitTestPluginUrls();
		$controller->svgs = new UnitTestSvgs();
		$controller->comps = new class {
			public object $events;

			public function __construct() {
				$this->events = new class {
					public function getEventName( string $event ) :string {
						return $event;
					}

					public function getEventAuditStrings( string $event ) :array {
						return [ 'Event '.$event ];
					}

					public function getEventDef( string $event ) :array {
						return [
							'audit_countable' => false,
							'level'           => 'info',
						];
					}
				};
			}
		};

		PluginControllerInstaller::install( $controller );
	}
}

trait ActivityLogTableDisplayBuilderOverrides {

	private array $identityResults;

	private array $users;

	public function __construct( array $identityResults, array $users ) {
		$this->identityResults = $identityResults;
		$this->users = $users;
	}

	protected function createIpIdentifier( string $ip, ?string $userAgent = null ) :IpID {
		$key = $ip.'|'.\trim( (string)$userAgent );
		if ( !\array_key_exists( $key, $this->identityResults ) ) {
			throw new \RuntimeException( 'Unexpected identity lookup for '.$key );
		}

		return new class( $this->identityResults[ $key ] ) extends IpID {
			private array $result;

			public function __construct( array $result ) {
				parent::__construct( '127.0.0.1' );
				$this->result = $result;
			}

			public function run() :array {
				return $this->result;
			}
		};
	}

	protected function resolveUser( int $uid ) {
		return $this->users[ $uid ] ?? null;
	}
}
