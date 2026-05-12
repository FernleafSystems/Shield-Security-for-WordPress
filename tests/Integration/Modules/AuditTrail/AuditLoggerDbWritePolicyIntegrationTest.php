<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Modules\AuditTrail;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\{
	ActivityLogRetentionPolicy,
	AuditLogger
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\ServicesState;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Support\CurrentRequestFixture;
use FernleafSystems\Wordpress\Services\Core\General;

class AuditLoggerDbWritePolicyIntegrationTest extends ShieldIntegrationTestCase {

	use CurrentRequestFixture;

	private array $requestSnapshot = [];

	public function set_up() {
		parent::set_up();

		$this->requireDb( 'ips' );
		$this->requireDb( 'req_logs' );
		$this->requireDb( 'activity_logs' );
		$this->requireDb( 'activity_logs_meta' );

		$this->requestSnapshot = $this->snapshotCurrentRequestState();
		$this->applyCurrentRequestState( [
			'REQUEST_METHOD' => 'GET',
			'REQUEST_URI'    => '/',
			'REMOTE_ADDR'     => '198.51.100.77',
		] );

		$this->requireController()->opts
			->optSet( 'enable_logger', 'Y' )
			->optSet( 'enable_live_log', 'N' )
			->optSet( 'enable_limiter', 'N' )
			->optSet( 'live_log_started_at', 0 );
	}

	public function tear_down() {
		$this->restoreCurrentRequestState( $this->requestSnapshot );
		parent::tear_down();
	}

	public function test_db_write_levels_include_info_only_when_wp_debug_is_enabled() :void {
		$this->setWpDebug( false );
		$this->assertSame( [ 'warning', 'notice' ], ( new ActivityLogRetentionPolicy() )->dbWriteLevels() );

		$this->setWpDebug( true );
		$this->assertSame( [ 'warning', 'notice', 'info' ], ( new ActivityLogRetentionPolicy() )->dbWriteLevels() );
	}

	public function test_db_write_level_filter_can_include_info_when_wp_debug_is_disabled() :void {
		$this->setWpDebug( false );
		$filter = fn( array $levels ) => \array_merge( $levels, [ 'info' ] );
		add_filter( 'shield/activity_logs/db_write_levels', $filter );

		try {
			$this->assertSame( [ 'warning', 'notice', 'info' ], ( new ActivityLogRetentionPolicy() )->dbWriteLevels() );
		}
		finally {
			remove_filter( 'shield/activity_logs/db_write_levels', $filter );
		}
	}

	public function test_db_write_level_filter_has_final_precedence_after_wp_debug_adds_info() :void {
		$this->setWpDebug( true );
		$filter = function ( array $levels ) {
			$this->assertSame( [ 'warning', 'notice', 'info' ], $levels );
			return [ 'warning', 'notice' ];
		};
		add_filter( 'shield/activity_logs/db_write_levels', $filter );

		try {
			$this->assertSame( [ 'warning', 'notice' ], ( new ActivityLogRetentionPolicy() )->dbWriteLevels() );
		}
		finally {
			remove_filter( 'shield/activity_logs/db_write_levels', $filter );
		}
	}

	public function test_audit_logger_primes_upgrade_sensitive_logging_classes() :void {
		$logger = $this->makeLogger();
		$this->runAuditLoggerInit( $logger );

		foreach ( $this->upgradeSensitiveLoggingClasses( $logger ) as $class ) {
			$this->assertTrue( \class_exists( $class, false ), $class.' should be loaded.' );
		}
	}

	public function test_info_audit_event_does_not_create_db_rows_when_wp_debug_is_disabled() :void {
		$this->setWpDebug( false );

		$this->writeAuditEvents( [
			$this->eventSpec( 'debug_log', 'info' ),
		] );

		$this->assertSame( 0, $this->rowCount( 'activity_logs' ) );
		$this->assertSame( 0, $this->rowCount( 'req_logs' ) );
	}

	public function test_info_audit_event_creates_db_rows_when_wp_debug_is_enabled() :void {
		$this->setWpDebug( true );

		$this->writeAuditEvents( [
			$this->eventSpec( 'debug_log', 'info' ),
		] );

		$this->assertSame( 1, $this->rowCount( 'activity_logs' ) );
		$this->assertSame( 1, $this->rowCount( 'req_logs' ) );
	}

	public function test_notice_and_warning_events_create_db_rows_when_wp_debug_is_disabled() :void {
		$this->setWpDebug( false );

		$this->writeAuditEvents( [
			$this->eventSpec( 'lic_activation_success', 'notice' ),
			$this->eventSpec( 'lic_check_fail', 'warning' ),
		] );

		$this->assertSame( 2, $this->rowCount( 'activity_logs' ) );
		$this->assertSame( 1, $this->rowCount( 'req_logs' ) );
	}

	public function test_multiple_audit_events_reuse_one_dependent_request_log() :void {
		$this->setWpDebug( false );

		$this->writeAuditEvents( [
			$this->eventSpec( 'lic_activation_success', 'notice' ),
			$this->eventSpec( 'lic_check_fail', 'warning' ),
		] );

		$this->assertSame( 2, $this->rowCount( 'activity_logs' ) );
		$this->assertSame( 1, $this->rowCount( 'req_logs' ) );
		$this->assertCount( 1, \array_unique( $this->activityLogRequestRefs() ) );
	}

	public function test_audit_metadata_is_persisted_for_written_event() :void {
		$this->setWpDebug( false );

		$this->writeAuditEvents( [
			$this->eventSpec( 'comment_deleted', 'notice', [
				'first_key'  => 'first-value',
				'second_key' => 'second-value',
			] ),
		] );

		$this->assertSame( [
			'first_key'  => 'first-value',
			'second_key' => 'second-value',
		], $this->latestActivityMeta() );
	}

	private function writeAuditEvents( array $events ) :void {
		$logger = $this->makeLogger();
		foreach ( $events as $event ) {
			$this->captureEvent( $logger, $event[ 'slug' ], [
				'audit_params' => $event[ 'params' ],
			], [
				'audit'           => true,
				'audit_countable' => false,
				'audit_multiple'  => true,
				'level'           => $event[ 'level' ],
			] );
		}
		$this->shutdownLogger( $logger );
	}

	private function eventSpec( string $slug, string $level, array $params = [] ) :array {
		return [
			'slug'   => $slug,
			'level'  => $level,
			'params' => $params,
		];
	}

	private function makeLogger() :AuditLogger {
		$ref = new \ReflectionClass( AuditLogger::class );
		/** @var AuditLogger $logger */
		$logger = $ref->newInstanceWithoutConstructor();
		return $logger;
	}

	private function captureEvent( AuditLogger $logger, string $event, array $meta, array $def ) :void {
		$method = new \ReflectionMethod( $logger, 'captureEvent' );
		$method->setAccessible( true );
		$method->invoke( $logger, $event, $meta, $def );
	}

	private function shutdownLogger( AuditLogger $logger ) :void {
		$method = new \ReflectionMethod( $logger, 'onShutdown' );
		$method->setAccessible( true );
		$method->invoke( $logger );
	}

	private function runAuditLoggerInit( AuditLogger $logger ) :void {
		$method = new \ReflectionMethod( $logger, 'init' );
		$method->setAccessible( true );
		$method->invoke( $logger );
	}

	private function upgradeSensitiveLoggingClasses( AuditLogger $logger ) :array {
		$method = new \ReflectionMethod( $logger, 'upgradeSensitiveLoggingClasses' );
		$method->setAccessible( true );
		return $method->invoke( $logger );
	}

	private function rowCount( string $dbKey ) :int {
		global $wpdb;
		return (int)$wpdb->get_var( \sprintf(
			'SELECT COUNT(*) FROM `%s`',
			$this->requireController()->db_con->{$dbKey}->getTable()
		) );
	}

	private function activityLogRequestRefs() :array {
		global $wpdb;
		return \array_map( 'intval', (array)$wpdb->get_col( \sprintf(
			'SELECT `req_ref` FROM `%s` ORDER BY `id` ASC',
			$this->requireController()->db_con->activity_logs->getTable()
		) ) );
	}

	private function latestActivityMeta() :array {
		global $wpdb;
		$logID = (int)$wpdb->get_var( \sprintf(
			'SELECT `id` FROM `%s` ORDER BY `id` DESC LIMIT 1',
			$this->requireController()->db_con->activity_logs->getTable()
		) );
		$rows = (array)$wpdb->get_results( $wpdb->prepare(
			\sprintf(
				'SELECT `meta_key`,`meta_value` FROM `%s` WHERE `log_ref`=%%d ORDER BY `meta_key` ASC',
				$this->requireController()->db_con->activity_logs_meta->getTable()
			),
			$logID
		), \ARRAY_A );

		$meta = [];
		foreach ( $rows as $row ) {
			$meta[ (string)$row[ 'meta_key' ] ] = (string)$row[ 'meta_value' ];
		}
		return $meta;
	}

	private function setWpDebug( bool $debug ) :void {
		ServicesState::mergeItems( [
			'service_wpgeneral' => new class( $debug ) extends General {
				private bool $debug;

				public function __construct( bool $debug ) {
					$this->debug = $debug;
				}

				public function isDebug() :bool {
					return $this->debug;
				}
			},
		] );
	}

}
