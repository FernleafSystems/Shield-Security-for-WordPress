<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Reporting;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ActivityLogs\LogRecord;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Report\Changes\{
	BaseZoneReport,
	BaseZoneReportPluginsThemes,
	BaseZoneReportPosts,
	BaseZoneReportUsers,
	ZoneReportComments,
	ZoneReportWordpress
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState,
	UnitTestGeneral
};
use FernleafSystems\Wordpress\Services\Core\Users;

class ReportChangeRowsContractTest extends BaseUnitTest {

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();

		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( 'esc_url_raw' )->alias( static function ( $url ) :string {
			$url = (string)$url;
			return \stripos( $url, 'javascript:' ) === 0 ? '' : $url;
		} );

		$this->installControllerStub();
		$this->servicesSnapshot = ServicesState::snapshot();
		ServicesState::installItems( [
			'service_wpgeneral' => new UnitTestGeneral( '/admin-ajax.php', 'display:' ),
			'service_wpusers'   => new class extends Users {
				public function getUserById( $id ) {
					return (object)[
						'user_login' => 'Admin <script>alert(1)</script>',
					];
				}
			},
		] );
	}

	protected function tearDown() :void {
		ServicesState::restore( $this->servicesSnapshot );
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_report_rows_keep_malicious_values_as_plain_contract_data() :void {
		$payload = '<script>alert(1)</script><img src=x onerror=alert(1)>';
		$cases = [
			[
				new ReportChangeRowsGenericReporterTestDouble( [ $this->log( 'generic_event', [
					'payload'  => $payload,
					'payload2' => 'second '.$payload,
				] ) ] ),
				$payload,
			],
			[
				new ReportChangeRowsPluginsThemesReporterTestDouble( [ $this->log( 'plugin_upgraded', [
					'from' => '1.0 '.$payload,
					'to'   => '2.0 '.$payload,
				] ) ] ),
				$payload,
			],
			[
				new ReportChangeRowsPostsReporterTestDouble( [ $this->log( 'post_updated_title', [
					'title_old' => 'old '.$payload,
					'title_new' => 'new '.$payload,
					'type'      => 'post',
				] ) ] ),
				$payload,
			],
			[
				new ReportChangeRowsPostsReporterTestDouble( [ $this->log( 'post_updated_slug', [
					'slug_old' => 'old-slug-'.$payload,
					'slug_new' => 'new-slug-'.$payload,
					'type'     => 'post',
				] ) ] ),
				$payload,
			],
			[
				new ReportChangeRowsCommentsReporterTestDouble( [ $this->log( 'comment_status_updated', [
					'comment_id' => 7,
					'status_old' => 'pending '.$payload,
					'status_new' => 'approved '.$payload,
				] ) ] ),
				$payload,
			],
			[
				new ReportChangeRowsWordpressReporterTestDouble( [ $this->log( 'wp_option_blogname', [
					'from' => 'old '.$payload,
					'to'   => 'new '.$payload,
				] ) ] ),
				$payload,
			],
			[
				new ReportChangeRowsUsersReporterTestDouble( [ $this->log( 'user_registered', [
					'user_login' => 'user '.$payload,
				] ) ] ),
				'user '.$payload,
			],
		];

		foreach ( $cases as [ $reporter, $expectedText ] ) {
			$data = $reporter->buildChangeReportData( false );
			$item = \array_values( $data )[ 0 ];
			$rows = $item[ 'rows' ];

			$this->assertIsString( $item[ 'name' ] );
			$this->assertStringContainsString( '<script>', $item[ 'name' ]."\n".$this->joinedRowText( $rows ) );
			$this->assertStringContainsString( $expectedText, $item[ 'name' ]."\n".$this->joinedRowText( $rows ) );
			$this->assertRowsUsePlainDataContract( $rows );
		}
	}

	public function test_summary_duplicates_use_count_field_instead_of_mutating_row_text() :void {
		$reporter = new ReportChangeRowsGenericReporterTestDouble( [
			$this->log( 'generic_event', [ 'payload' => 'same', 'payload2' => 'line' ] ),
			$this->log( 'generic_event', [ 'payload' => 'same', 'payload2' => 'line' ] ),
		] );

		$item = \array_values( $reporter->buildChangeReportData( true ) )[ 0 ];

		$this->assertCount( 1, $item[ 'rows' ] );
		$this->assertSame( 2, $item[ 'rows' ][ 0 ][ 'count' ] );
		$this->assertSame( [ 'Generic same', 'Second line' ], $item[ 'rows' ][ 0 ][ 'lines' ] );
	}

	public function test_unsafe_link_protocol_is_removed_from_report_contract() :void {
		$reporter = new ReportChangeRowsGenericReporterTestDouble( [
			$this->log( 'generic_event', [
				'payload'  => 'value',
				'payload2' => 'second',
			] ),
		], 'javascript:alert(1)' );

		$item = \array_values( $reporter->buildChangeReportData( false ) )[ 0 ];

		$this->assertSame( [], $item[ 'link' ] );
	}

	private function assertRowsUsePlainDataContract( array $rows ) :void {
		foreach ( $rows as $row ) {
			$this->assertIsArray( $row );
			$this->assertArrayHasKey( 'lines', $row );
			$this->assertArrayHasKey( 'count', $row );
			$this->assertIsArray( $row[ 'lines' ] );
			$this->assertIsInt( $row[ 'count' ] );
			$this->assertStringNotContainsString( '<code>', \implode( "\n", $row[ 'lines' ] ) );
			$this->assertStringNotContainsString( '<br', \implode( "\n", $row[ 'lines' ] ) );
			$this->assertStringNotContainsString( '&rarr;', \implode( "\n", $row[ 'lines' ] ) );
		}
	}

	private function joinedRowText( array $rows ) :string {
		return \implode( "\n", \array_map(
			static fn( array $row ) :string => \implode( "\n", $row[ 'lines' ] ),
			$rows
		) );
	}

	private function log( string $event, array $metaData ) :LogRecord {
		$log = new LogRecord();
		$log->event_slug = $event;
		$log->meta_data = \array_merge( [
			'uid' => 1,
		], $metaData );
		$log->ip = '203.0.113.44';
		$log->created_at = 1713278000;
		return $log;
	}

	private function installControllerStub() :void {
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->comps = new class {
			public object $events;

			public function __construct() {
				$this->events = new class {
					public function getEventAuditStrings( string $event ) :array {
						return [
							'Generic {{payload}}',
							'Second {{payload2}}',
						];
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
}

trait ReportChangeRowsLogsTestTrait {

	private array $testLogs;

	public function __construct( array $logs ) {
		parent::__construct();
		$this->testLogs = $logs;
	}

	protected function loadLogs() :array {
		return $this->testLogs;
	}

	protected function getLoadLogsWheres() :array {
		return [];
	}

	protected function getUniqFromLog( LogRecord $log ) :string {
		return 'test';
	}

	protected function getLinkForLog( LogRecord $log ) :array {
		return [
			'href' => '/wp-admin/index.php',
			'text' => 'View',
		];
	}

	public function getZoneName() :string {
		return 'Test';
	}
}

class ReportChangeRowsGenericReporterTestDouble extends BaseZoneReport {

	use ReportChangeRowsLogsTestTrait {
		ReportChangeRowsLogsTestTrait::__construct as private constructWithLogs;
	}

	private string $href;

	public function __construct( array $logs, string $href = '/wp-admin/index.php' ) {
		$this->constructWithLogs( $logs );
		$this->href = $href;
	}

	protected function getNameForLog( LogRecord $log ) :string {
		return (string)( $log->meta_data[ 'payload' ] ?? 'Generic' );
	}

	protected function getLinkForLog( LogRecord $log ) :array {
		return [
			'href' => $this->href,
			'text' => 'View',
		];
	}
}

class ReportChangeRowsPluginsThemesReporterTestDouble extends BaseZoneReportPluginsThemes {

	use ReportChangeRowsLogsTestTrait;

	protected function getNameForLog( LogRecord $log ) :string {
		return '<script>alert(1)</script> Plugin';
	}
}

class ReportChangeRowsPostsReporterTestDouble extends BaseZoneReportPosts {

	use ReportChangeRowsLogsTestTrait;

	protected function loadLogsFilterPostType() :string {
		return 'post';
	}

	protected function getNameForLog( LogRecord $log ) :string {
		return '<script>alert(1)</script> Post';
	}
}

class ReportChangeRowsCommentsReporterTestDouble extends ZoneReportComments {

	use ReportChangeRowsLogsTestTrait;

	protected function getNameForLog( LogRecord $log ) :string {
		return '<script>alert(1)</script> Comment';
	}
}

class ReportChangeRowsWordpressReporterTestDouble extends ZoneReportWordpress {

	use ReportChangeRowsLogsTestTrait;
}

class ReportChangeRowsUsersReporterTestDouble extends BaseZoneReportUsers {

	use ReportChangeRowsLogsTestTrait;

	protected function getNameForLog( LogRecord $log ) :string {
		return (string)$log->meta_data[ 'user_login' ];
	}
}
