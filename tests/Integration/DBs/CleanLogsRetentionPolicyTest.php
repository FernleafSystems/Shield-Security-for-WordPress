<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\DBs;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Database\CleanDatabases;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\IPs\IPRecords;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ReqLogs\Ops\Handler as ReqLogsHandler;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ReqLogs\RequestRecords;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\ActivityLogRetentionPolicy;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\Lib\LogHandlers\LocalDbWriter as TrafficLocalDbWriter;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\Lib\RequestLogRetentionPolicy;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Services\Core\Request as ServicesRequest;
use FernleafSystems\Wordpress\Services\Services;

class CleanLogsRetentionPolicyTest extends ShieldIntegrationTestCase {

	private function insertRequestLog( bool $isTransient ) :int {
		$con = $this->requireController();
		$ipRecord = ( new IPRecords() )->loadIP( '198.51.100.'.wp_rand( 10, 250 ) );
		$reqRecord = ( new RequestRecords() )->loadReq( \substr( \wp_generate_uuid4(), 0, 10 ), $ipRecord->id );

		$con->db_con->req_logs->getQueryUpdater()->updateById( $reqRecord->id, [
			'type'      => ReqLogsHandler::TYPE_HTTP,
			'verb'      => 'GET',
			'path'      => '/index.php',
			'code'      => 200,
			'uid'       => 0,
			'offense'   => 0,
			'transient' => $isTransient ? 1 : 0,
			'meta'      => \base64_encode( '{}' ),
		] );

		return (int)$reqRecord->id;
	}

	private function insertActivityLogForRequest( int $reqRef, string $eventSlug ) :int {
		$dbh = $this->requireController()->db_con->activity_logs;
		$record = $dbh->getRecord();
		$record->event_slug = $eventSlug;
		$record->site_id = \get_current_blog_id();
		$record->req_ref = $reqRef;
		$dbh->getQueryInserter()->insert( $record );

		global $wpdb;
		return (int)$wpdb->get_var( 'SELECT LAST_INSERT_ID()' );
	}

	private function setCreatedAt( string $dbKey, int $id, int $timestamp ) :void {
		$this->requireController()->db_con->{$dbKey}->getQueryUpdater()->updateById( $id, [
			'created_at' => $timestamp,
		] );
	}

	private function existsById( string $dbKey, int $id ) :bool {
		return !empty( $this->requireController()->db_con->{$dbKey}->getQuerySelector()->byId( $id ) );
	}

	private function rowCount( string $dbKey ) :int {
		global $wpdb;
		return (int)$wpdb->get_var(
			sprintf(
				'SELECT COUNT(*) FROM `%s`',
				$this->requireController()->db_con->{$dbKey}->getTable()
			)
		);
	}

	private function requestTransientFlag( int $reqId ) :int {
		global $wpdb;
		return (int)$wpdb->get_var(
			sprintf(
				'SELECT `transient` FROM `%s` WHERE `id`=%d',
				$this->requireController()->db_con->req_logs->getTable(),
				$reqId
			)
		);
	}

	private function withRequestLoggerDependentState( bool $isDependent, callable $callback ) {
		$logger = $this->requireController()->comps->requests_log;
		$property = new \ReflectionProperty( $logger, 'isDependentLog' );
		$property->setAccessible( true );
		$snapshot = (bool)$property->getValue( $logger );
		$property->setValue( $logger, $isDependent );

		try {
			return $callback();
		}
		finally {
			$property->setValue( $logger, $snapshot );
		}
	}

	private function withFixedRequestTimestamp( int $timestamp, callable $callback ) {
		$ref = new \ReflectionClass( Services::class );
		$servicesProp = $ref->getProperty( 'services' );
		$servicesProp->setAccessible( true );

		$servicesSnapshot = $servicesProp->getValue();
		$services = $servicesSnapshot ?? [];
		if ( !\is_array( $services ) ) {
			$services = [];
		}

		$services[ 'service_request' ] = new class( $timestamp ) extends ServicesRequest {

			private int $fixedTimestamp;

			public function __construct( int $fixedTimestamp ) {
				$this->fixedTimestamp = $fixedTimestamp;
				parent::__construct();
			}

			public function ts( bool $update = true ) :int {
				return $this->fixedTimestamp;
			}
		};

		$servicesProp->setValue( null, $services );

		try {
			return $callback();
		}
		finally {
			$servicesProp->setValue( null, $servicesSnapshot );
		}
	}

	private function writeRequestLogViaWriter(
		bool $hasParams,
		bool $offense,
		bool $isDependent,
		string $verb = 'GET'
	) :int {
		$ip = '203.0.113.'.wp_rand( 10, 250 );
		$ipRecord = ( new IPRecords() )->loadIP( $ip );
		$rid = \substr( \wp_generate_uuid4(), 0, 10 );
		$reqRecord = ( new RequestRecords() )->loadReq( $rid, $ipRecord->id );

		$writer = new class() extends TrafficLocalDbWriter {
			public function writePrimaryForTest( array $record ) :bool {
				return $this->createPrimaryLogRecord( $record );
			}
		};

		$this->withRequestLoggerDependentState(
			$isDependent,
			fn() => $writer->writePrimaryForTest( [
				'extra' => [
					'meta_shield'  => [
						'offense' => $offense ? 1 : 0,
					],
					'meta_request' => [
						'ip'         => $ip,
						'rid'        => $rid,
						'verb'       => $verb,
						'code'       => 200,
						'path'       => '/matrix/'.\strtolower( $verb ),
						'type'       => ReqLogsHandler::TYPE_HTTP,
						'has_params' => $hasParams ? 1 : 0,
					],
					'meta_user'    => [
						'uid' => 0,
					],
					'meta_wp'      => [],
				],
			] )
		);

		return (int)$reqRecord->id;
	}

	private function firstEventForLevel( string $level, array $exclude = [] ) :string {
		foreach ( $this->requireController()->comps->events->getEvents() as $event => $def ) {
			if ( ( $def[ 'level' ] ?? 'notice' ) === $level && !\in_array( $event, $exclude, true ) ) {
				return $event;
			}
		}
		$this->markTestSkipped( sprintf( "No event found for level '%s'.", $level ) );
		return '';
	}

	public function set_up() {
		parent::set_up();
		$this->requireDb( 'ips' );
		$this->requireDb( 'req_logs' );
		$this->requireDb( 'activity_logs' );
	}

	public function test_policy_prunes_activity_and_request_logs_by_tier_and_references() :void {
		$now = \time();
		$highValueEvents = ( new ActivityLogRetentionPolicy() )->highValueEventSlugs();

		$infoEvent = $this->firstEventForLevel( 'info', $highValueEvents );
		$warningEvent = $this->firstEventForLevel( 'warning', $highValueEvents );
		$highValueEvent = \current( $highValueEvents );
		if ( !\is_string( $highValueEvent ) || empty( $highValueEvent ) ) {
			$this->markTestSkipped( 'No high-value event slugs were available.' );
		}

		$transientOldReq = $this->insertRequestLog( true );
		$this->setCreatedAt( 'req_logs', $transientOldReq, $now - 8*\DAY_IN_SECONDS );

		$transientRecentReq = $this->insertRequestLog( true );
		$this->setCreatedAt( 'req_logs', $transientRecentReq, $now - 2*\DAY_IN_SECONDS );

		$standardOldUnreferencedReq = $this->insertRequestLog( false );
		$this->setCreatedAt( 'req_logs', $standardOldUnreferencedReq, $now - 31*\DAY_IN_SECONDS );

		$standardOldReqWithWarning = $this->insertRequestLog( false );
		$this->setCreatedAt( 'req_logs', $standardOldReqWithWarning, $now - 31*\DAY_IN_SECONDS );
		$warningActivity = $this->insertActivityLogForRequest( $standardOldReqWithWarning, $warningEvent );
		$this->setCreatedAt( 'activity_logs', $warningActivity, $now - 40*\DAY_IN_SECONDS );

		$standardOldReqWithInfo = $this->insertRequestLog( false );
		$this->setCreatedAt( 'req_logs', $standardOldReqWithInfo, $now - 31*\DAY_IN_SECONDS );
		$infoActivity = $this->insertActivityLogForRequest( $standardOldReqWithInfo, $infoEvent );
		$this->setCreatedAt( 'activity_logs', $infoActivity, $now - 2*\DAY_IN_SECONDS );

		$standardOldReqWithHighValue = $this->insertRequestLog( false );
		$this->setCreatedAt( 'req_logs', $standardOldReqWithHighValue, $now - 31*\DAY_IN_SECONDS );
		$highValueActivity = $this->insertActivityLogForRequest( $standardOldReqWithHighValue, $highValueEvent );
		$this->setCreatedAt( 'activity_logs', $highValueActivity, $now - 400*\DAY_IN_SECONDS );

		$standardOldReqWithExpiredHighValue = $this->insertRequestLog( false );
		$this->setCreatedAt( 'req_logs', $standardOldReqWithExpiredHighValue, $now - 31*\DAY_IN_SECONDS );
		$expiredHighValueActivity = $this->insertActivityLogForRequest( $standardOldReqWithExpiredHighValue, $highValueEvent );
		$this->setCreatedAt( 'activity_logs', $expiredHighValueActivity, $now - 800*\DAY_IN_SECONDS );

		( new CleanDatabases() )->all();

		$this->assertFalse( $this->existsById( 'req_logs', $transientOldReq ) );
		$this->assertTrue( $this->existsById( 'req_logs', $transientRecentReq ) );
		$this->assertFalse( $this->existsById( 'req_logs', $standardOldUnreferencedReq ) );

		$this->assertTrue( $this->existsById( 'activity_logs', $warningActivity ) );
		$this->assertTrue( $this->existsById( 'req_logs', $standardOldReqWithWarning ) );

		$this->assertFalse( $this->existsById( 'activity_logs', $infoActivity ) );
		$this->assertFalse( $this->existsById( 'req_logs', $standardOldReqWithInfo ) );

		$this->assertTrue( $this->existsById( 'activity_logs', $highValueActivity ) );
		$this->assertTrue( $this->existsById( 'req_logs', $standardOldReqWithHighValue ) );

		$this->assertFalse( $this->existsById( 'activity_logs', $expiredHighValueActivity ) );
		$this->assertFalse( $this->existsById( 'req_logs', $standardOldReqWithExpiredHighValue ) );
	}

	public function test_policy_filters_override_pruning_windows() :void {
		$now = \time();
		$highValueEvents = ( new ActivityLogRetentionPolicy() )->highValueEventSlugs();
		$infoEvent = $this->firstEventForLevel( 'info', $highValueEvents );
		$warningEvent = $this->firstEventForLevel( 'warning', $highValueEvents );
		$highValueEvent = \current( $highValueEvents );
		if ( !\is_string( $highValueEvent ) || empty( $highValueEvent ) ) {
			$this->markTestSkipped( 'No high-value event slugs were available.' );
		}

		$activityFilter = static function ( array $policy ) :array {
			$policy[ 'retention_seconds_by_level' ][ 'info' ] = 12*\HOUR_IN_SECONDS;
			$policy[ 'retention_seconds_by_level' ][ 'notice' ] = 14*\DAY_IN_SECONDS;
			$policy[ 'retention_seconds_by_level' ][ 'warning' ] = 45*\DAY_IN_SECONDS;
			$policy[ 'high_value_retention_seconds' ] = 90*\DAY_IN_SECONDS;
			return $policy;
		};
		$requestFilter = static function ( array $policy ) :array {
			$policy[ 'retention_days' ][ 'transient' ] = 2;
			$policy[ 'retention_days' ][ 'standard' ] = 5;
			return $policy;
		};
		add_filter( ActivityLogRetentionPolicy::FILTER_ACTIVITY_POLICY, $activityFilter );
		add_filter( RequestLogRetentionPolicy::FILTER_REQUEST_POLICY, $requestFilter );

		try {
			$transientOldReq = $this->insertRequestLog( true );
			$this->setCreatedAt( 'req_logs', $transientOldReq, $now - 3*\DAY_IN_SECONDS );

			$transientRecentReq = $this->insertRequestLog( true );
			$this->setCreatedAt( 'req_logs', $transientRecentReq, $now - 1*\DAY_IN_SECONDS );

			$standardOldUnreferencedReq = $this->insertRequestLog( false );
			$this->setCreatedAt( 'req_logs', $standardOldUnreferencedReq, $now - 6*\DAY_IN_SECONDS );

			$standardOldReqWithWarning = $this->insertRequestLog( false );
			$this->setCreatedAt( 'req_logs', $standardOldReqWithWarning, $now - 6*\DAY_IN_SECONDS );
			$warningActivity = $this->insertActivityLogForRequest( $standardOldReqWithWarning, $warningEvent );
			$this->setCreatedAt( 'activity_logs', $warningActivity, $now - 20*\DAY_IN_SECONDS );

			$standardOldReqWithInfo = $this->insertRequestLog( false );
			$this->setCreatedAt( 'req_logs', $standardOldReqWithInfo, $now - 6*\DAY_IN_SECONDS );
			$infoActivity = $this->insertActivityLogForRequest( $standardOldReqWithInfo, $infoEvent );
			$this->setCreatedAt( 'activity_logs', $infoActivity, $now - 13*\HOUR_IN_SECONDS );

			$standardOldReqWithHighValue = $this->insertRequestLog( false );
			$this->setCreatedAt( 'req_logs', $standardOldReqWithHighValue, $now - 6*\DAY_IN_SECONDS );
			$highValueActivity = $this->insertActivityLogForRequest( $standardOldReqWithHighValue, $highValueEvent );
			$this->setCreatedAt( 'activity_logs', $highValueActivity, $now - 80*\DAY_IN_SECONDS );

			$standardOldReqWithExpiredHighValue = $this->insertRequestLog( false );
			$this->setCreatedAt( 'req_logs', $standardOldReqWithExpiredHighValue, $now - 6*\DAY_IN_SECONDS );
			$expiredHighValueActivity = $this->insertActivityLogForRequest( $standardOldReqWithExpiredHighValue, $highValueEvent );
			$this->setCreatedAt( 'activity_logs', $expiredHighValueActivity, $now - 100*\DAY_IN_SECONDS );

			( new CleanDatabases() )->all();

			$this->assertFalse( $this->existsById( 'req_logs', $transientOldReq ) );
			$this->assertTrue( $this->existsById( 'req_logs', $transientRecentReq ) );
			$this->assertFalse( $this->existsById( 'req_logs', $standardOldUnreferencedReq ) );

			$this->assertTrue( $this->existsById( 'activity_logs', $warningActivity ) );
			$this->assertTrue( $this->existsById( 'req_logs', $standardOldReqWithWarning ) );

			$this->assertFalse( $this->existsById( 'activity_logs', $infoActivity ) );
			$this->assertFalse( $this->existsById( 'req_logs', $standardOldReqWithInfo ) );

			$this->assertTrue( $this->existsById( 'activity_logs', $highValueActivity ) );
			$this->assertTrue( $this->existsById( 'req_logs', $standardOldReqWithHighValue ) );

			$this->assertFalse( $this->existsById( 'activity_logs', $expiredHighValueActivity ) );
			$this->assertFalse( $this->existsById( 'req_logs', $standardOldReqWithExpiredHighValue ) );
		}
		finally {
			remove_filter( ActivityLogRetentionPolicy::FILTER_ACTIVITY_POLICY, $activityFilter );
			remove_filter( RequestLogRetentionPolicy::FILTER_REQUEST_POLICY, $requestFilter );
		}
	}

	public function test_unknown_event_slug_uses_notice_fallback_retention() :void {
		$now = \time();
		$activityFilter = static function ( array $policy ) :array {
			$policy[ 'retention_seconds_by_level' ][ 'notice' ] = 40*\DAY_IN_SECONDS;
			return $policy;
		};
		add_filter( ActivityLogRetentionPolicy::FILTER_ACTIVITY_POLICY, $activityFilter );

		try {
			$oldReq = $this->insertRequestLog( false );
			$this->setCreatedAt( 'req_logs', $oldReq, $now - 50*\DAY_IN_SECONDS );
			$oldUnknownActivity = $this->insertActivityLogForRequest( $oldReq, 'unknown_custom_event' );
			$this->setCreatedAt( 'activity_logs', $oldUnknownActivity, $now - 50*\DAY_IN_SECONDS );

			$recentReq = $this->insertRequestLog( false );
			$this->setCreatedAt( 'req_logs', $recentReq, $now - 30*\DAY_IN_SECONDS );
			$recentUnknownActivity = $this->insertActivityLogForRequest( $recentReq, 'unknown_custom_event' );
			$this->setCreatedAt( 'activity_logs', $recentUnknownActivity, $now - 30*\DAY_IN_SECONDS );

			( new CleanDatabases() )->all();

			$this->assertFalse( $this->existsById( 'activity_logs', $oldUnknownActivity ) );
			$this->assertFalse( $this->existsById( 'req_logs', $oldReq ) );

			$this->assertTrue( $this->existsById( 'activity_logs', $recentUnknownActivity ) );
			$this->assertTrue( $this->existsById( 'req_logs', $recentReq ) );
		}
		finally {
			remove_filter( ActivityLogRetentionPolicy::FILTER_ACTIVITY_POLICY, $activityFilter );
		}
	}

	public function test_request_pruning_uses_strict_less_than_cutoff_boundaries() :void {
		$fixedNow = 1710000000;
		$requestFilter = static function ( array $policy ) :array {
			$policy[ 'retention_days' ] = [
				'transient' => 2,
				'standard'  => 5,
			];
			return $policy;
		};
		add_filter( RequestLogRetentionPolicy::FILTER_REQUEST_POLICY, $requestFilter );

		try {
			$this->withFixedRequestTimestamp( $fixedNow, function () use ( $fixedNow ) {
				$transientCutoff = $fixedNow - 2*\DAY_IN_SECONDS;
				$standardCutoff = $fixedNow - 5*\DAY_IN_SECONDS;

				$transientBefore = $this->insertRequestLog( true );
				$this->setCreatedAt( 'req_logs', $transientBefore, $transientCutoff - 1 );
				$transientAt = $this->insertRequestLog( true );
				$this->setCreatedAt( 'req_logs', $transientAt, $transientCutoff );
				$transientAfter = $this->insertRequestLog( true );
				$this->setCreatedAt( 'req_logs', $transientAfter, $transientCutoff + 1 );

				$standardBefore = $this->insertRequestLog( false );
				$this->setCreatedAt( 'req_logs', $standardBefore, $standardCutoff - 1 );
				$standardAt = $this->insertRequestLog( false );
				$this->setCreatedAt( 'req_logs', $standardAt, $standardCutoff );
				$standardAfter = $this->insertRequestLog( false );
				$this->setCreatedAt( 'req_logs', $standardAfter, $standardCutoff + 1 );

				( new CleanDatabases() )->all();

				$this->assertFalse( $this->existsById( 'req_logs', $transientBefore ) );
				$this->assertTrue( $this->existsById( 'req_logs', $transientAt ) );
				$this->assertTrue( $this->existsById( 'req_logs', $transientAfter ) );

				$this->assertFalse( $this->existsById( 'req_logs', $standardBefore ) );
				$this->assertTrue( $this->existsById( 'req_logs', $standardAt ) );
				$this->assertTrue( $this->existsById( 'req_logs', $standardAfter ) );
			} );
		}
		finally {
			remove_filter( RequestLogRetentionPolicy::FILTER_REQUEST_POLICY, $requestFilter );
		}
	}

	public function test_activity_pruning_uses_strict_less_than_cutoff_boundaries() :void {
		$fixedNow = 1715000000;
		$highValueEvents = ( new ActivityLogRetentionPolicy() )->highValueEventSlugs();
		$infoEvent = $this->firstEventForLevel( 'info', $highValueEvents );

		$activityFilter = static function ( array $policy ) :array {
			$policy[ 'retention_seconds_by_level' ][ 'info' ] = 2*\DAY_IN_SECONDS;
			$policy[ 'retention_seconds_by_level' ][ 'notice' ] = 3*\DAY_IN_SECONDS;
			$policy[ 'retention_seconds_by_level' ][ 'warning' ] = 7*\DAY_IN_SECONDS;
			return $policy;
		};
		add_filter( ActivityLogRetentionPolicy::FILTER_ACTIVITY_POLICY, $activityFilter );

		try {
			$this->withFixedRequestTimestamp( $fixedNow, function () use ( $fixedNow, $infoEvent ) {
				$activityCutoff = $fixedNow - 2*\DAY_IN_SECONDS;

				$beforeReq = $this->insertRequestLog( false );
				$this->setCreatedAt( 'req_logs', $beforeReq, $fixedNow - 45*\DAY_IN_SECONDS );
				$beforeActivity = $this->insertActivityLogForRequest( $beforeReq, $infoEvent );
				$this->setCreatedAt( 'activity_logs', $beforeActivity, $activityCutoff - 1 );

				$atReq = $this->insertRequestLog( false );
				$this->setCreatedAt( 'req_logs', $atReq, $fixedNow - 45*\DAY_IN_SECONDS );
				$atActivity = $this->insertActivityLogForRequest( $atReq, $infoEvent );
				$this->setCreatedAt( 'activity_logs', $atActivity, $activityCutoff );

				$afterReq = $this->insertRequestLog( false );
				$this->setCreatedAt( 'req_logs', $afterReq, $fixedNow - 45*\DAY_IN_SECONDS );
				$afterActivity = $this->insertActivityLogForRequest( $afterReq, $infoEvent );
				$this->setCreatedAt( 'activity_logs', $afterActivity, $activityCutoff + 1 );

				( new CleanDatabases() )->all();

				$this->assertFalse( $this->existsById( 'activity_logs', $beforeActivity ) );
				$this->assertFalse( $this->existsById( 'req_logs', $beforeReq ) );

				$this->assertTrue( $this->existsById( 'activity_logs', $atActivity ) );
				$this->assertTrue( $this->existsById( 'req_logs', $atReq ) );

				$this->assertTrue( $this->existsById( 'activity_logs', $afterActivity ) );
				$this->assertTrue( $this->existsById( 'req_logs', $afterReq ) );
			} );
		}
		finally {
			remove_filter( ActivityLogRetentionPolicy::FILTER_ACTIVITY_POLICY, $activityFilter );
		}
	}

	public function test_activity_retention_precedence_event_then_high_value_then_level_then_notice_fallback() :void {
		$now = \time();
		$defaultHighValueEvents = ( new ActivityLogRetentionPolicy() )->highValueEventSlugs();
		$highValueEvent = \current( $defaultHighValueEvents );
		if ( !\is_string( $highValueEvent ) || empty( $highValueEvent ) ) {
			$this->markTestSkipped( 'No high-value event slugs were available.' );
		}

		$warningEvent = $this->firstEventForLevel( 'warning', $defaultHighValueEvents );
		$infoEvent = $this->firstEventForLevel( 'info', $defaultHighValueEvents );

		$activityFilter = static function ( array $policy ) use ( $highValueEvent, $warningEvent ) :array {
			$policy[ 'retention_seconds_by_level' ] = [
				'info'    => 2*\DAY_IN_SECONDS,
				'notice'  => 3*\DAY_IN_SECONDS,
				'warning' => 4*\DAY_IN_SECONDS,
			];
			$policy[ 'high_value_events' ] = [
				$highValueEvent,
			];
			$policy[ 'high_value_retention_seconds' ] = 5*\DAY_IN_SECONDS;
			$policy[ 'retention_seconds_by_event' ] = [
				$highValueEvent => 7*\DAY_IN_SECONDS,
				$warningEvent   => 6*\DAY_IN_SECONDS,
			];
			return $policy;
		};
		add_filter( ActivityLogRetentionPolicy::FILTER_ACTIVITY_POLICY, $activityFilter );

		try {
			$eventOverrideReq = $this->insertRequestLog( false );
			$this->setCreatedAt( 'req_logs', $eventOverrideReq, $now - 40*\DAY_IN_SECONDS );
			$eventOverrideActivity = $this->insertActivityLogForRequest( $eventOverrideReq, $warningEvent );
			$this->setCreatedAt( 'activity_logs', $eventOverrideActivity, $now - 5*\DAY_IN_SECONDS );

			$highValueReq = $this->insertRequestLog( false );
			$this->setCreatedAt( 'req_logs', $highValueReq, $now - 40*\DAY_IN_SECONDS );
			$highValueActivity = $this->insertActivityLogForRequest( $highValueReq, $highValueEvent );
			$this->setCreatedAt( 'activity_logs', $highValueActivity, $now - 6*\DAY_IN_SECONDS );

			$levelReq = $this->insertRequestLog( false );
			$this->setCreatedAt( 'req_logs', $levelReq, $now - 40*\DAY_IN_SECONDS );
			$levelActivity = $this->insertActivityLogForRequest( $levelReq, $infoEvent );
			$this->setCreatedAt( 'activity_logs', $levelActivity, $now - 3*\DAY_IN_SECONDS );

			$noticeFallbackReqOld = $this->insertRequestLog( false );
			$this->setCreatedAt( 'req_logs', $noticeFallbackReqOld, $now - 40*\DAY_IN_SECONDS );
			$noticeFallbackActivityOld = $this->insertActivityLogForRequest( $noticeFallbackReqOld, 'unknown_event_old' );
			$this->setCreatedAt( 'activity_logs', $noticeFallbackActivityOld, $now - 4*\DAY_IN_SECONDS );

			$noticeFallbackReqRecent = $this->insertRequestLog( false );
			$this->setCreatedAt( 'req_logs', $noticeFallbackReqRecent, $now - 40*\DAY_IN_SECONDS );
			$noticeFallbackActivityRecent = $this->insertActivityLogForRequest( $noticeFallbackReqRecent, 'unknown_event_recent' );
			$this->setCreatedAt( 'activity_logs', $noticeFallbackActivityRecent, $now - 2*\DAY_IN_SECONDS );

			( new CleanDatabases() )->all();

			$this->assertTrue( $this->existsById( 'activity_logs', $eventOverrideActivity ) );
			$this->assertTrue( $this->existsById( 'req_logs', $eventOverrideReq ) );

			$this->assertTrue( $this->existsById( 'activity_logs', $highValueActivity ) );
			$this->assertTrue( $this->existsById( 'req_logs', $highValueReq ) );

			$this->assertFalse( $this->existsById( 'activity_logs', $levelActivity ) );
			$this->assertFalse( $this->existsById( 'req_logs', $levelReq ) );

			$this->assertFalse( $this->existsById( 'activity_logs', $noticeFallbackActivityOld ) );
			$this->assertFalse( $this->existsById( 'req_logs', $noticeFallbackReqOld ) );

			$this->assertTrue( $this->existsById( 'activity_logs', $noticeFallbackActivityRecent ) );
			$this->assertTrue( $this->existsById( 'req_logs', $noticeFallbackReqRecent ) );
		}
		finally {
			remove_filter( ActivityLogRetentionPolicy::FILTER_ACTIVITY_POLICY, $activityFilter );
		}
	}

	public function test_request_log_write_classification_matrix_and_prune_outcomes() :void {
		$now = \time();

		$dependentReq = $this->writeRequestLogViaWriter( false, false, true );
		$queryReq = $this->writeRequestLogViaWriter( true, false, false, 'GET' );
		$postReq = $this->writeRequestLogViaWriter( true, false, false, 'POST' );
		$noParamsReq = $this->writeRequestLogViaWriter( false, false, false );
		$offenseReq = $this->writeRequestLogViaWriter( false, true, false );

		foreach ( [ $dependentReq, $queryReq, $postReq, $noParamsReq, $offenseReq ] as $reqId ) {
			$this->setCreatedAt( 'req_logs', $reqId, $now - 8*\DAY_IN_SECONDS );
		}

		$this->assertSame( 0, $this->requestTransientFlag( $dependentReq ) );
		$this->assertSame( 0, $this->requestTransientFlag( $queryReq ) );
		$this->assertSame( 0, $this->requestTransientFlag( $postReq ) );
		$this->assertSame( 1, $this->requestTransientFlag( $noParamsReq ) );
		$this->assertSame( 0, $this->requestTransientFlag( $offenseReq ) );

		( new CleanDatabases() )->all();

		$this->assertTrue( $this->existsById( 'req_logs', $dependentReq ) );
		$this->assertTrue( $this->existsById( 'req_logs', $queryReq ) );
		$this->assertTrue( $this->existsById( 'req_logs', $postReq ) );
		$this->assertFalse( $this->existsById( 'req_logs', $noParamsReq ) );
		$this->assertTrue( $this->existsById( 'req_logs', $offenseReq ) );
	}

	public function test_referenced_request_logs_are_protected_until_activity_is_pruned() :void {
		$now = \time();
		$highValueEvents = ( new ActivityLogRetentionPolicy() )->highValueEventSlugs();
		$warningEvent = $this->firstEventForLevel( 'warning', $highValueEvents );

		$activityFilter = static function ( array $policy ) :array {
			$policy[ 'retention_seconds_by_level' ][ 'warning' ] = 14*\DAY_IN_SECONDS;
			return $policy;
		};
		$requestFilter = static function ( array $policy ) :array {
			$policy[ 'retention_days' ][ 'standard' ] = 5;
			return $policy;
		};
		add_filter( ActivityLogRetentionPolicy::FILTER_ACTIVITY_POLICY, $activityFilter );
		add_filter( RequestLogRetentionPolicy::FILTER_REQUEST_POLICY, $requestFilter );

		try {
			$reqId = $this->insertRequestLog( false );
			$this->setCreatedAt( 'req_logs', $reqId, $now - 6*\DAY_IN_SECONDS );

			$activityId = $this->insertActivityLogForRequest( $reqId, $warningEvent );
			$this->setCreatedAt( 'activity_logs', $activityId, $now - 2*\DAY_IN_SECONDS );

			( new CleanDatabases() )->all();

			$this->assertTrue( $this->existsById( 'activity_logs', $activityId ) );
			$this->assertTrue( $this->existsById( 'req_logs', $reqId ) );

			$this->setCreatedAt( 'activity_logs', $activityId, $now - 20*\DAY_IN_SECONDS );

			( new CleanDatabases() )->all();

			$this->assertFalse( $this->existsById( 'activity_logs', $activityId ) );
			$this->assertFalse( $this->existsById( 'req_logs', $reqId ) );
		}
		finally {
			remove_filter( ActivityLogRetentionPolicy::FILTER_ACTIVITY_POLICY, $activityFilter );
			remove_filter( RequestLogRetentionPolicy::FILTER_REQUEST_POLICY, $requestFilter );
		}
	}

	public function test_cleaner_is_idempotent_across_back_to_back_runs() :void {
		$now = \time();
		$highValueEvents = ( new ActivityLogRetentionPolicy() )->highValueEventSlugs();
		$warningEvent = $this->firstEventForLevel( 'warning', $highValueEvents );

		$transientOldReq = $this->insertRequestLog( true );
		$this->setCreatedAt( 'req_logs', $transientOldReq, $now - 8*\DAY_IN_SECONDS );

		$standardOldReq = $this->insertRequestLog( false );
		$this->setCreatedAt( 'req_logs', $standardOldReq, $now - 31*\DAY_IN_SECONDS );

		$retainedReq = $this->insertRequestLog( false );
		$this->setCreatedAt( 'req_logs', $retainedReq, $now - 31*\DAY_IN_SECONDS );
		$retainedActivity = $this->insertActivityLogForRequest( $retainedReq, $warningEvent );
		$this->setCreatedAt( 'activity_logs', $retainedActivity, $now - 2*\DAY_IN_SECONDS );

		( new CleanDatabases() )->all();

		$afterFirst = [
			'req_count'         => $this->rowCount( 'req_logs' ),
			'activity_count'    => $this->rowCount( 'activity_logs' ),
			'transient_exists'  => $this->existsById( 'req_logs', $transientOldReq ),
			'standard_exists'   => $this->existsById( 'req_logs', $standardOldReq ),
			'retained_req'      => $this->existsById( 'req_logs', $retainedReq ),
			'retained_activity' => $this->existsById( 'activity_logs', $retainedActivity ),
		];

		( new CleanDatabases() )->all();

		$afterSecond = [
			'req_count'         => $this->rowCount( 'req_logs' ),
			'activity_count'    => $this->rowCount( 'activity_logs' ),
			'transient_exists'  => $this->existsById( 'req_logs', $transientOldReq ),
			'standard_exists'   => $this->existsById( 'req_logs', $standardOldReq ),
			'retained_req'      => $this->existsById( 'req_logs', $retainedReq ),
			'retained_activity' => $this->existsById( 'activity_logs', $retainedActivity ),
		];

		$this->assertSame( $afterFirst, $afterSecond );
	}
}
