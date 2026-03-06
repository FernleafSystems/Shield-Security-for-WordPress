<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Traffic\LiveLogRowsBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ActivityLogs\LogRecord as ActivityLogRecord;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ReqLogs\LogRecord as RequestLogRecord;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginControllerInstaller;

class LiveLogRowsBuilderTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();

		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( 'wp_date' )->alias(
			static fn( string $format, int $timestamp ) :string => \gmdate( $format, $timestamp )
		);

		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->plugin_urls = new class {
			public function ipAnalysis( string $ip ) :string {
				return '/ip/'.$ip;
			}
		};
		$controller->comps = new class {
			public object $events;

			public function __construct() {
				$this->events = new class {
					public function getEventName( string $event ) :string {
						return $event === 'ip_blocked' ? 'IP Blocked' : $event;
					}

					public function getEventAuditStrings( string $event ) :array {
						return $event === 'ip_blocked'
							? [ 'IP address blocked by Shield.' ]
							: [ $event ];
					}

					public function getEventDef( string $event ) :array {
						return [
							'audit_countable' => false,
						];
					}
				};
			}
		};

		PluginControllerInstaller::install( $controller );
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_compact_timestamp_uses_time_only_for_same_day() :void {
		$builder = new LiveLogRowsBuilder();

		$this->assertSame(
			'14:35',
			$builder->buildCompactTimestamp( 1713278100, 1713290000 )
		);
	}

	public function test_compact_timestamp_uses_short_date_for_older_day_without_timezone() :void {
		$builder = new LiveLogRowsBuilder();
		$timestamp = $builder->buildCompactTimestamp( 1713278100, 1713380000 );

		$this->assertSame( 'Apr 16, 14:35', $timestamp );
		$this->assertStringNotContainsString( '+', $timestamp );
	}

	public function test_build_activity_row_maps_timestamp_ip_title_and_description() :void {
		$builder = new LiveLogRowsBuilder();
		$record = new ActivityLogRecord();
		$record->event_slug = 'ip_blocked';
		$record->created_at = 1713278100;
		$record->ip = '198.51.100.40';
		$record->meta_data = [];
		$row = $builder->buildActivityRow( $record );

		$this->assertSame( 'IP Blocked', $row[ 'title' ] );
		$this->assertSame( '198.51.100.40', $row[ 'ip' ] );
		$this->assertSame( '/ip/198.51.100.40', $row[ 'ip_href' ] );
		$this->assertIsString( $row[ 'timestamp' ] );
		$this->assertNotSame( '', $row[ 'timestamp' ] );
		$this->assertIsString( $row[ 'description' ] );
	}

	public function test_build_traffic_row_maps_request_summary_ip_and_badges() :void {
		$builder = new LiveLogRowsBuilder();
		$record = new RequestLogRecord();
		$record->created_at = 1713278100;
		$record->ip = '203.0.113.55';
		$record->verb = 'POST';
		$record->path = '/wp-login.php';
		$record->code = 403;
		$record->type = 'H';
		$record->offense = true;
		$record->uid = 0;
		$record->meta = [
			'query' => 'reauth=1',
		];
		$row = $builder->buildTrafficRow( $record );

		$this->assertSame( '203.0.113.55', $row[ 'ip' ] );
		$this->assertSame( '/ip/203.0.113.55', $row[ 'ip_href' ] );
		$this->assertSame( 'POST /wp-login.php?reauth=1', $row[ 'title' ] );
		$this->assertSame( 'Response: 403 | Offense detected', $row[ 'description' ] );
		$this->assertSame( [ 'HTTP', '403', 'Offense' ], \array_column( $row[ 'badges' ], 'label' ) );
	}
}
