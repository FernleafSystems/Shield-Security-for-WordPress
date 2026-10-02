<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Scans\Afs;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Updates\HandleUpgrade;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\ScansController;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Processing\MalaiEarlyRecheck;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Processing\MalwareStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TestDataFactory;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Integrations\WpHashes\ApiBase;

class MalaiEarlyRecheckIntegrationTest extends ShieldIntegrationTestCase {

	private ?array $cronSnapshot = null;

	private MalaiEarlyRecheck $scheduler;

	private int $scanID;

	public function set_up() {
		parent::set_up();
		foreach ( [ 'malware', 'scans', 'scan_results', 'scan_result_items', 'scan_result_item_meta' ] as $dbKey ) {
			$this->requireDb( $dbKey );
		}
		$this->enablePremiumCapabilities( [ 'scan_pluginsthemes_local', 'scan_malware_local', 'scan_malware_malai' ] );
		$this->scheduler = new MalaiEarlyRecheck();
		$this->cronSnapshot = $this->snapshotCronArray();
		\wp_clear_scheduled_hook( $this->scheduler->hook() );
		$this->scanID = TestDataFactory::insertCompletedScan( 'afs' );
	}

	public function tear_down() {
		if ( $this->cronSnapshot !== null ) {
			$this->restoreCronArray( $this->cronSnapshot );
		}
		parent::tear_down();
	}

	public function test_schedules_at_ten_minutes_and_replaces_a_later_event_without_duplicates() :void {
		$now = Services::Request()->ts();
		$this->seedFinding( 'later.php', $now );
		$this->scheduler->schedule();
		$this->assertSame( $now + 600, \wp_next_scheduled( $this->scheduler->hook() ) );

		$this->seedFinding( 'earlier.php', $now - 120 );
		$this->scheduler->schedule();
		$this->scheduler->schedule();
		$this->assertSame( [ $now + 480 ], $this->scheduledTimes() );
	}

	public function test_existing_earlier_event_is_retained() :void {
		$now = Services::Request()->ts();
		$this->seedFinding( 'future.php', $now );
		$this->assertTrue( \wp_schedule_single_event( $now + 100, $this->scheduler->hook() ) );
		$this->scheduler->schedule();
		$this->assertSame( [ $now + 100 ], $this->scheduledTimes() );
	}

	public function test_overdue_work_gets_one_catchup_but_does_not_create_an_immediate_retry_loop() :void {
		$now = Services::Request()->ts();
		$this->seedFinding( 'overdue.php', $now - 601 );
		$this->seedFinding( 'future.php', $now - 60 );
		$this->scheduler->schedule();
		$this->assertSame( $now + 5, \wp_next_scheduled( $this->scheduler->hook() ) );

		\wp_clear_scheduled_hook( $this->scheduler->hook() );
		$this->scheduler->schedule( false );
		$this->assertSame( [ $now + 540 ], $this->scheduledTimes() );
	}

	public function test_finished_followups_and_inactive_findings_do_not_schedule() :void {
		$now = Services::Request()->ts();
		$this->seedFinding( 'already-checked.php', $now - 900, [ 'last_malai_status_at' => $now - 300 ] );
		$this->seedFinding( 'clean.php', $now, [ 'malai_status' => MalwareStatus::STATUS_CLEAN ] );
		$this->seedFinding( 'unreported.php', 0 );
		foreach ( [ 'ignored_at', 'resolved_at', 'auto_filtered_at' ] as $column ) {
			$finding = $this->seedFinding( $column.'.php', $now );
			global $wpdb;
			$this->assertNotFalse( $wpdb->update(
				self::con()->db_con->scan_result_items->getTable(),
				[ $column => $now ],
				[ 'id' => $finding[ 'result_item_id' ] ]
			) );
		}

		$this->scheduler->schedule();
		$this->assertFalse( \wp_next_scheduled( $this->scheduler->hook() ) );
	}

	public function test_a_check_before_the_due_time_does_not_consume_followup() :void {
		$now = Services::Request()->ts();
		$this->seedFinding( 'not-due.php', $now - 599, [ 'last_malai_status_at' => $now ] );
		$this->scheduler->schedule();
		$this->assertSame( $now + 1, \wp_next_scheduled( $this->scheduler->hook() ) );
	}

	public function test_capability_loss_prevents_scheduling_and_attempt_writes() :void {
		$finding = $this->seedFinding( 'overdue.php', Services::Request()->ts() - 601 );
		$this->disablePremiumCapabilities();
		$this->scheduler->schedule();
		$this->scheduler->run();
		$this->assertFalse( \wp_next_scheduled( $this->scheduler->hook() ) );
		$record = self::con()->db_con->malware->getQuerySelector()->byId( $finding[ 'malware_record_id' ] );
		$this->assertSame( 0, (int)$record->last_malai_status_at );
	}

	public function test_scheduling_reads_beyond_the_first_page() :void {
		$now = Services::Request()->ts();
		for ( $i = 0; $i < 200; $i++ ) {
			$this->seedFinding( 'settled-'.$i.'.php', $now, [ 'malai_status' => MalwareStatus::STATUS_MALWARE ] );
		}
		$this->seedFinding( 'last-page.php', $now - 120 );
		$this->scheduler->schedule();
		$this->assertSame( $now + 480, \wp_next_scheduled( $this->scheduler->hook() ) );
	}

	public function test_registered_callback_preserves_future_candidates_and_does_not_submit_files() :void {
		$now = Services::Request()->ts();
		$future = $this->seedFinding( 'future.php', $now );
		$unreported = $this->seedFinding( 'unreported.php', 0 );
		self::con()->comps->scans->execute();
		$this->assertNotFalse( \has_action( $this->scheduler->hook() ) );
		\do_action( $this->scheduler->hook() );
		$this->assertSame( [ $now + 600 ], $this->scheduledTimes() );
		$futureRecord = self::con()->db_con->malware->getQuerySelector()->byId( $future[ 'malware_record_id' ] );
		$unreportedRecord = self::con()->db_con->malware->getQuerySelector()->byId( $unreported[ 'malware_record_id' ] );
		$this->assertSame( 0, (int)$futureRecord->last_malai_status_at );
		$this->assertSame( 0, (int)$unreportedRecord->reported_at );
	}

	public function test_scheduled_callback_reconciles_due_results_once_and_rearms_later_findings() :void {
		$con = self::con();
		$now = Services::Request()->ts();
		$clean = $this->seedFinding( 'due-clean.php', $now - 601 );
		$pending = $this->seedFinding( 'due-pending.php', $now - 601 );
		$future = $this->seedFinding( 'future.php', $now - 60 );
		$excluded = [ $this->seedFinding( 'predicted.php', $now - 601, [
			'malai_status' => MalwareStatus::STATUS_PREDICTED_CLEAN,
		] ) ];
		global $wpdb;
		foreach ( [ 'ignored_at', 'resolved_at', 'auto_filtered_at' ] as $column ) {
			$finding = $this->seedFinding( $column.'.php', $now - 601 );
			$excluded[] = $finding;
			$this->assertNotFalse( $wpdb->update(
				$con->db_con->scan_result_items->getTable(),
				[ $column => $now ],
				[ 'id' => $finding[ 'result_item_id' ] ]
			) );
		}
		$cleanHash = $con->db_con->malware->getQuerySelector()->byId( $clean[ 'malware_record_id' ] )->hash_sha256;
		$pendingHash = $con->db_con->malware->getQuerySelector()->byId( $pending[ 'malware_record_id' ] )->hash_sha256;
		$optionsSnapshot = $this->snapshotSelectedOptions( [ 'wphashes_api_token' ] );
		$tokenProperty = new \ReflectionProperty( ApiBase::class, 'API_TOKEN' );
		$tokenProperty->setAccessible( true );
		$previousToken = $tokenProperty->getValue();
		$con->opts->optSet( 'wphashes_api_token', [
			'token'             => \str_repeat( 'a', 40 ),
			'expires_at'        => $now + \DAY_IN_SECONDS,
			'next_attempt_from' => $now + \DAY_IN_SECONDS,
			'valid_license'     => true,
		] );
		\set_transient( 'apto-wphashes-api-available-routes', '#.*#', 300 );
		$batches = [];
		$filter = static function ( $pre, array $args, string $url ) use ( &$batches, $cleanHash, $pendingHash ) {
			if ( \strpos( $url, '/v2/malai/malware/statuses' ) === false ) {
				return $pre;
			}
			$batches[] = $args[ 'body' ][ 'hashes_sha256' ];
			return [
				'headers' => [],
				'body' => \wp_json_encode( [
					'error_code' => 0,
					'statuses' => [ $cleanHash => 'clean', $pendingHash => 'unclassified' ],
				] ),
				'response' => [ 'code' => 200, 'message' => '' ],
				'cookies' => [],
			];
		};
		\add_filter( 'pre_http_request', $filter, 10, 3 );
		try {
			$con->comps->scans->execute();
			$beforeSchedule = Services::Request()->ts();
			$this->scheduler->schedule();
			$event = \wp_get_scheduled_event( $this->scheduler->hook() );
			$this->assertNotFalse( $event );
			$this->assertGreaterThanOrEqual( $beforeSchedule + 5, $event->timestamp );
			$this->assertLessThanOrEqual( Services::Request()->ts() + 5, $event->timestamp );
			// WordPress removes single events before dispatching their registered callback.
			$this->assertTrue( \wp_unschedule_event( $event->timestamp, $event->hook, $event->args ) );
			$beforeDispatch = Services::Request()->ts();
			\do_action_ref_array( $event->hook, $event->args );
			\do_action_ref_array( $event->hook, $event->args );
			$afterDispatch = Services::Request()->ts();
			$this->assertSame( [ [ $cleanHash, $pendingHash ] ], $batches );
			$this->assertSame( [ $now + 540 ], $this->scheduledTimes() );

			$cleanRecord = $con->db_con->malware->getQuerySelector()->byId( $clean[ 'malware_record_id' ] );
			$pendingRecord = $con->db_con->malware->getQuerySelector()->byId( $pending[ 'malware_record_id' ] );
			$this->assertSame( MalwareStatus::STATUS_CLEAN, $cleanRecord->malai_status );
			$this->assertSame( MalwareStatus::STATUS_UNCLASSIFIED, $pendingRecord->malai_status );
			foreach ( [ $cleanRecord, $pendingRecord ] as $record ) {
				$this->assertGreaterThanOrEqual( $beforeDispatch, (int)$record->last_malai_status_at );
				$this->assertLessThanOrEqual( $afterDispatch, (int)$record->last_malai_status_at );
			}
			$result = $con->db_con->scan_result_items->getQuerySelector()->byId( $clean[ 'result_item_id' ] );
			$this->assertGreaterThan( 0, (int)$result->auto_filtered_at );
			$this->assertSame( '0', (string)$wpdb->get_var( $wpdb->prepare(
				"SELECT meta_value FROM `{$con->db_con->scan_result_item_meta->getTable()}` WHERE ri_ref=%d AND meta_key='is_mal'",
				$clean[ 'result_item_id' ]
			) ) );
			foreach ( \array_merge( [ $future ], $excluded ) as $finding ) {
				$record = $con->db_con->malware->getQuerySelector()->byId( $finding[ 'malware_record_id' ] );
				$this->assertSame( 0, (int)$record->last_malai_status_at );
			}
		}
		finally {
			\remove_filter( 'pre_http_request', $filter, 10 );
			$tokenProperty->setValue( null, $previousToken );
			$this->restoreSelectedOptions( $optionsSnapshot );
		}
	}

	public function test_postscan_reporting_exception_still_schedules_existing_candidates() :void {
		$now = Services::Request()->ts();
		$this->seedFinding( 'future.php', $now );
		self::con()->comps->scans->execute();
		$hook = self::con()->prefix( ScansController::HOOK_POST_SCAN_MALAI );
		$this->assertNotFalse( \has_action( $hook ) );
		$table = self::con()->db_con->malware->getTable();
		$failure = new \RuntimeException( 'Reporting read failed.' );
		$filter = static function ( string $query ) use ( $table, $failure ) :string {
			if ( \stripos( $query, 'SELECT' ) === 0
				 && \strpos( $query, $table ) !== false
				 && \strpos( $query, 'reported_at' ) !== false ) {
				throw $failure;
			}
			return $query;
		};
		$caught = null;
		\add_filter( 'query', $filter );
		try {
			\do_action( $hook );
		}
		catch ( \RuntimeException $exception ) {
			$caught = $exception;
		}
		finally {
			\remove_filter( 'query', $filter );
		}
		$this->assertSame( $failure, $caught );
		$this->assertSame( [ $now + 600 ], $this->scheduledTimes() );
	}

	public function test_failed_attempt_stops_the_pass_and_only_rearms_future_candidates() :void {
		$now = Services::Request()->ts();
		$this->seedFinding( 'due.php', $now - 601 );
		$this->seedFinding( 'future.php', $now - 60 );
		$table = self::con()->db_con->malware->getTable();
		$failed = false;
		$filter = static function ( string $query ) use ( $table, &$failed ) :string {
			if ( \stripos( $query, 'UPDATE' ) === 0 && \strpos( $query, $table ) !== false ) {
				$failed = true;
				throw new \RuntimeException( 'Early-attempt write failed.' );
			}
			return $query;
		};
		\add_filter( 'query', $filter );
		try {
			$this->scheduler->run();
		}
		finally {
			\remove_filter( 'query', $filter );
		}
		$this->assertTrue( $failed );
		$this->assertSame( [ $now + 540 ], $this->scheduledTimes() );
	}

	public function test_upgrade_cron_cleanup_rearms_the_followup() :void {
		$con = self::con();
		$now = Services::Request()->ts();
		$this->seedFinding( 'upgrade.php', $now );
		$this->scheduler->schedule();
		$unrelatedHook = $con->prefix( 'malai-recheck-upgrade-fixture' );
		$this->assertTrue( \wp_schedule_single_event( $now + 100, $unrelatedHook ) );
		$previousVersion = $con->cfg->previous_version;
		$previousPersistRequired = $con->cfg->persist_required;
		$upgradeHook = $con->prefix( 'plugin-upgrade' );
		global $wp_filter;
		$previousHook = $wp_filter[ $upgradeHook ] ?? null;
		try {
			\remove_all_actions( $upgradeHook );
			$con->cfg->previous_version = '0.0.1';
			( new HandleUpgrade() )->execute();
			\do_action( $upgradeHook, '0.0.1' );
			$this->assertFalse( \wp_next_scheduled( $unrelatedHook ) );
			$this->assertSame( [ $now + 600 ], $this->scheduledTimes() );
		}
		finally {
			$con->cfg->previous_version = $previousVersion;
			$con->cfg->persist_required = $previousPersistRequired;
			\remove_all_actions( $upgradeHook );
			if ( $previousHook !== null ) {
				$wp_filter[ $upgradeHook ] = $previousHook;
			}
		}
	}

	/** @return list<int> */
	private function scheduledTimes() :array {
		$times = [];
		foreach ( $this->snapshotCronArray() as $timestamp => $hooks ) {
			if ( isset( $hooks[ $this->scheduler->hook() ] ) ) {
				foreach ( $hooks[ $this->scheduler->hook() ] as $event ) {
					$times[] = (int)$timestamp;
					$this->assertSame( [], $event[ 'args' ] );
				}
			}
		}
		return $times;
	}

	/** @return array{malware_record_id:int,result_item_id:int} */
	private function seedFinding( string $name, int $reportedAt, array $overrides = [] ) :array {
		$path = 'wp-content/plugins/fixture/'.$name;
		$id = TestDataFactory::insertMalwareRecord( $path, $name, \array_merge( [
			'reported_at' => $reportedAt,
		], $overrides ) );
		$finding = TestDataFactory::insertAfsFileScanResultTracked( $this->scanID, $path, [
			'is_in_plugin'      => 1,
			'is_mal'            => 1,
			'malware_record_id' => $id,
			'ptg_slug'          => 'fixture/fixture.php',
		] );
		return [ 'malware_record_id' => $id, 'result_item_id' => $finding[ 'result_item_id' ] ];
	}
}
