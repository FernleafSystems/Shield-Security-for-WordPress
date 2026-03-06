<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\DBs;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Database\CleanDatabases;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\IPs\IPRecords;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ReqLogs\Ops\Handler as ReqLogsHandler;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ReqLogs\RequestRecords;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\ActivityLogRetentionPolicy;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\Lib\RequestLogRetentionPolicy;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

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
}
