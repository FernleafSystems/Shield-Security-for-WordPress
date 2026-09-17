<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	ActionProcessor,
	Actions\ImportExportSitesTableAction,
	Exceptions\InvalidActionNonceException,
	Exceptions\SecurityAdminRequiredException
};
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ImportExportSites\Ops\Handler as SitesDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Sites\{
	InvitationMetadata,
	QueueScheduler,
	SiteRepository
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class ImportExportSitesTableActionIntegrationTest extends ShieldIntegrationTestCase {

	private $enabledOptionSnapshot;

	public function set_up() {
		parent::set_up();
		$this->requireDb( SitesDB::DB_KEY );
		$this->loginAsSecurityAdmin();
		$this->enablePremiumCapabilities( [ 'import_export_level_2' ] );
		$this->requireController()->this_req->wp_is_ajax = false;
		$this->enabledOptionSnapshot = $this->requireController()->opts->optGet( 'importexport_enable' );
		$this->requireController()->opts->optSet( 'importexport_enable', 'Y' )->store();
		\wp_clear_scheduled_hook( ( new QueueScheduler() )->hook() );
	}

	public function tear_down() {
		\wp_clear_scheduled_hook( ( new QueueScheduler() )->hook() );
		$this->requireController()->opts->optSet( 'importexport_enable', $this->enabledOptionSnapshot )->store();
		parent::tear_down();
	}

	public function test_retry_invitation_dispatch_reports_counts_and_queues_only_eligible_rows() :void {
		$repo = new SiteRepository();
		$eligible = $repo->upsertPendingClientSite( 'https://retry-eligible.example.com', SitesDB::SOURCE_MANUAL, false );
		$alreadyPending = $repo->upsertPendingClientSite( 'https://retry-pending.example.com', SitesDB::SOURCE_MANUAL, true );
		$nonPending = $repo->upsertActive( 'https://retry-nonpending.example.com', SitesDB::SOURCE_MANUAL );
		$deleted = $repo->upsertPendingClientSite( 'https://retry-deleted.example.com', SitesDB::SOURCE_MANUAL, false );
		$repo->softDeleteUrl( $deleted->url );

		$payload = $this->processTableAction( [
			'sub_action' => ImportExportSitesTableAction::SUB_ACTION_RETRY_INVITATION,
			'rids'       => [ $eligible->id, $alreadyPending->id, $nonPending->id, $deleted->id, 9999999, $eligible->id, 0, -1 ],
		] );

		$this->assertTrue( $payload[ 'success' ] ?? false );
		$this->assertTrue( $payload[ 'table_reload' ] ?? false );
		$this->assertSame( 1, $payload[ 'queued_count' ] ?? null );
		$this->assertSame( 4, $payload[ 'skipped_count' ] ?? null );
		$this->assertSame( 0, $payload[ 'failed_count' ] ?? null );
		$this->assertSame( SitesDB::QUEUE_PENDING_INVITE, $repo->findById( $eligible->id, true )->queue_status );
		$this->assertSame( SitesDB::QUEUE_PENDING_INVITE, $repo->findById( $alreadyPending->id, true )->queue_status );
		$this->assertSame( SitesDB::QUEUE_QUEUED, $repo->findById( $nonPending->id, true )->queue_status );
		$this->assertSame( SitesDB::STATUS_DELETED, $repo->findById( $deleted->id, true )->status );
		$this->assertNotFalse( \wp_next_scheduled( ( new QueueScheduler() )->hook() ) );
	}

	public function test_retry_replaces_only_invitation_cycle_and_queue_scheduling_state() :void {
		$repo = new SiteRepository();
		$eligible = $repo->upsertPendingClientSite( 'https://retry-preserve.example.com', SitesDB::SOURCE_MANUAL, false );
		$dbh = $this->requireController()->db_con->import_export_sites;
		$oldMeta = [
			InvitationMetadata::META_KEY => [
				'cycle_id'               => 'old-cycle',
				'attempts_started'        => 3,
				'last_attempt_started_at' => 1712620000,
				'last_result'             => InvitationMetadata::RESULT_TRANSPORT_FAILURE,
				'last_http_status'        => null,
			],
			'export_served_at'       => 1712619000,
			'handshake_attempt_at'   => 1712618000,
			'unrelated_nested_state' => [ 'keep' => 'exactly' ],
		];
		$this->assertTrue( $dbh->getQueryUpdater()->updateById( $eligible->id, [
			'import_id'               => 'preserved-import-id',
			'last_ping_attempt_at'    => 1712617001,
			'last_ping_success_at'    => 1712617002,
			'last_ping_failure_at'    => 1712617003,
			'last_ping_http_code'     => 429,
			'last_ping_error'         => 'preserved ping category',
			'last_export_request_at'  => 1712616001,
			'last_export_success_at'  => 1712616002,
			'last_export_failure_at'  => 1712616003,
			'last_export_result_code' => SitesDB::EXPORT_RESULT_VERIFY_FAILED,
			'last_export_error'       => 'preserved export category',
			'ping_attempts_total'      => 7,
			'consecutive_failures'     => 4,
			'meta'                     => $dbh->getRecord()->arrayDataWrap( $oldMeta ) ?? '',
		] ) );
		$before = $repo->findById( $eligible->id, true );

		$payload = $this->processTableAction( [
			'sub_action' => ImportExportSitesTableAction::SUB_ACTION_RETRY_INVITATION,
			'rids'       => [ $eligible->id ],
		] );
		$after = $repo->findById( $eligible->id, true );

		$this->assertSame( 1, $payload[ 'queued_count' ] ?? null );
		$this->assertSame( $before->id, $after->id );
		$this->assertSame( $before->profile_ref, $after->profile_ref );
		foreach ( [
			'url',
			'url_hash',
			'import_id',
			'source',
			'status',
			'last_ping_attempt_at',
			'last_ping_success_at',
			'last_ping_failure_at',
			'last_ping_http_code',
			'last_ping_error',
			'last_export_request_at',
			'last_export_success_at',
			'last_export_failure_at',
			'last_export_result_code',
			'last_export_error',
			'ping_attempts_total',
			'consecutive_failures',
		] as $field ) {
			$this->assertSame( $before->{$field}, $after->{$field}, $field );
		}
		$withoutInvitation = static fn( array $meta ) :array => \array_diff_key( $meta, [ InvitationMetadata::META_KEY => true ] );
		$this->assertSame( $withoutInvitation( $before->meta ), $withoutInvitation( $after->meta ) );
		$newInvitation = ( new InvitationMetadata() )->normalize( $after->meta );
		$this->assertNotSame( 'old-cycle', $newInvitation[ 'cycle_id' ] );
		$this->assertNotSame( '', $newInvitation[ 'cycle_id' ] );
		$this->assertSame( 0, $newInvitation[ 'attempts_started' ] );
		$this->assertNull( $newInvitation[ 'last_attempt_started_at' ] );
		$this->assertNull( $newInvitation[ 'last_result' ] );
		$this->assertNull( $newInvitation[ 'last_http_status' ] );
		$this->assertSame( SitesDB::QUEUE_PENDING_INVITE, $after->queue_status );
		$this->assertGreaterThan( 0, $after->next_ping_at );
	}

	public function test_repeated_retry_skips_without_resetting_the_new_cycle() :void {
		$repo = new SiteRepository();
		$eligible = $repo->upsertPendingClientSite( 'https://retry-once.example.com', SitesDB::SOURCE_MANUAL, false );

		$first = $this->processTableAction( [
			'sub_action' => ImportExportSitesTableAction::SUB_ACTION_RETRY_INVITATION,
			'rids'       => [ $eligible->id ],
		] );
		$afterFirst = $repo->findById( $eligible->id, true );
		$firstMeta = $afterFirst->meta;

		$second = $this->processTableAction( [
			'sub_action' => ImportExportSitesTableAction::SUB_ACTION_RETRY_INVITATION,
			'rids'       => [ $eligible->id ],
		] );
		$afterSecond = $repo->findById( $eligible->id, true );

		$this->assertSame( 1, $first[ 'queued_count' ] ?? null );
		$this->assertSame( 0, $second[ 'queued_count' ] ?? null );
		$this->assertSame( 1, $second[ 'skipped_count' ] ?? null );
		$this->assertSame( 0, $second[ 'failed_count' ] ?? null );
		$this->assertTrue( $second[ 'success' ] ?? false );
		$this->assertSame( SitesDB::QUEUE_PENDING_INVITE, $afterSecond->queue_status );
		$this->assertSame( $firstMeta, $afterSecond->meta );
	}

	public function test_same_snapshot_retry_interleaving_queues_one_cycle_and_skips_the_stale_request() :void {
		$repo = new SiteRepository();
		$eligible = $repo->upsertPendingClientSite( 'https://retry-interleaved.example.com', SitesDB::SOURCE_MANUAL, false );
		$table = $this->requireController()->db_con->import_export_sites->getTable();
		$targetRow = \sprintf( '`id`=%d AND', $eligible->id );
		$interleaved = false;
		$winnerPayload = null;
		$winnerState = null;
		$interleave = function ( string $query ) use (
			$table,
			$targetRow,
			$eligible,
			&$interleaved,
			&$winnerPayload,
			&$winnerState
		) :string {
			$isTargetCas = !$interleaved
						   && \strpos( $query, "UPDATE `{$table}`" ) !== false
						   && \strpos( $query, $targetRow ) !== false
						   && \strpos( $query, 'BINARY `meta`=BINARY' ) !== false;
			if ( $isTargetCas ) {
				$interleaved = true;
				$winnerPayload = $this->processTableAction( [
					'sub_action' => ImportExportSitesTableAction::SUB_ACTION_RETRY_INVITATION,
					'rids'       => [ $eligible->id ],
				] );
				$winnerState = ( new SiteRepository() )->findById( $eligible->id, true )->getRawData();
			}
			return $query;
		};
		$this->assertFalse( \wp_next_scheduled( ( new QueueScheduler() )->hook() ) );
		\add_filter( 'query', $interleave, 1000 );

		try {
			$loserPayload = $this->processTableAction( [
				'sub_action' => ImportExportSitesTableAction::SUB_ACTION_RETRY_INVITATION,
				'rids'       => [ $eligible->id ],
			] );
		}
		finally {
			\remove_filter( 'query', $interleave, 1000 );
		}

		$this->assertTrue( $interleaved );
		$this->assertIsArray( $winnerPayload );
		$this->assertTrue( $winnerPayload[ 'success' ] ?? false );
		$this->assertTrue( $winnerPayload[ 'table_reload' ] ?? false );
		$this->assertSame( 1, $winnerPayload[ 'queued_count' ] ?? null );
		$this->assertSame( 0, $winnerPayload[ 'skipped_count' ] ?? null );
		$this->assertSame( 0, $winnerPayload[ 'failed_count' ] ?? null );
		$this->assertTrue( $loserPayload[ 'success' ] ?? false );
		$this->assertTrue( $loserPayload[ 'table_reload' ] ?? false );
		$this->assertSame( 0, $loserPayload[ 'queued_count' ] ?? null );
		$this->assertSame( 1, $loserPayload[ 'skipped_count' ] ?? null );
		$this->assertSame( 0, $loserPayload[ 'failed_count' ] ?? null );

		$after = $repo->findById( $eligible->id, true );
		$this->assertSame( $winnerState, $after->getRawData() );
		$this->assertSame( SitesDB::QUEUE_PENDING_INVITE, $after->queue_status );
		$invitation = ( new InvitationMetadata() )->normalize( $after->meta );
		$this->assertNotSame( '', $invitation[ 'cycle_id' ] );
		$this->assertSame( 0, $invitation[ 'attempts_started' ] );
		$this->assertNotFalse( \wp_next_scheduled( ( new QueueScheduler() )->hook() ) );
	}

	public function test_disabled_sync_rejects_retry_without_mutation_or_scheduling() :void {
		$repo = new SiteRepository();
		$eligible = $repo->upsertPendingClientSite( 'https://retry-disabled.example.com', SitesDB::SOURCE_MANUAL, false );
		$before = $repo->findById( $eligible->id, true )->getRawData();
		$this->requireController()->opts->optSet( 'importexport_enable', 'N' )->store();

		$payload = $this->processTableAction( [
			'sub_action' => ImportExportSitesTableAction::SUB_ACTION_RETRY_INVITATION,
			'rids'       => [ $eligible->id ],
		] );

		$this->assertFalse( $payload[ 'success' ] ?? true );
		$this->assertSame( $before, $repo->findById( $eligible->id, true )->getRawData() );
		$this->assertFalse( \wp_next_scheduled( ( new QueueScheduler() )->hook() ) );
	}

	public function test_unavailable_sync_rejects_retry_without_mutation_or_scheduling() :void {
		$repo = new SiteRepository();
		$eligible = $repo->upsertPendingClientSite( 'https://retry-unavailable.example.com', SitesDB::SOURCE_MANUAL, false );
		$before = $repo->findById( $eligible->id, true )->getRawData();
		$this->disablePremiumCapabilities();

		$payload = $this->processTableAction( [
			'sub_action' => ImportExportSitesTableAction::SUB_ACTION_RETRY_INVITATION,
			'rids'       => [ $eligible->id ],
		] );

		$this->assertFalse( $payload[ 'success' ] ?? true );
		$this->assertSame( $before, $repo->findById( $eligible->id, true )->getRawData() );
		$this->assertFalse( \wp_next_scheduled( ( new QueueScheduler() )->hook() ) );
	}

	public function test_retry_invitation_does_not_send_http_inline() :void {
		$repo = new SiteRepository();
		$eligible = $repo->upsertPendingClientSite( 'https://retry-no-inline-http.example.com', SitesDB::SOURCE_MANUAL, false );
		$httpCalls = 0;
		$httpProbe = static function ( $preempt ) use ( &$httpCalls ) {
			$httpCalls++;
			return $preempt;
		};
		\add_filter( 'pre_http_request', $httpProbe, 1, 3 );

		try {
			$payload = $this->processTableAction( [
				'sub_action' => ImportExportSitesTableAction::SUB_ACTION_RETRY_INVITATION,
				'rids'       => [ $eligible->id ],
			] );
		}
		finally {
			\remove_filter( 'pre_http_request', $httpProbe, 1 );
		}

		$this->assertTrue( $payload[ 'success' ] ?? false );
		$this->assertSame( 1, $payload[ 'queued_count' ] ?? null );
		$this->assertSame( 0, $httpCalls );
	}

	public function test_database_write_failure_is_reported_without_failing_the_handled_action() :void {
		global $wpdb;
		$repo = new SiteRepository();
		$eligible = $repo->upsertPendingClientSite( 'https://retry-db-failure.example.com', SitesDB::SOURCE_MANUAL, false );
		$table = $this->requireController()->db_con->import_export_sites->getTable();
		$queryFailure = static function ( string $query ) use ( $table ) :string {
			return \strpos( $query, "UPDATE `{$table}`" ) !== false && \strpos( $query, 'BINARY `meta`' ) !== false
				? 'UPDATE intentionally_invalid_sql'
				: $query;
		};
		\add_filter( 'query', $queryFailure, 1000 );
		$previousShowErrors = $wpdb->hide_errors();
		$previousSuppressErrors = $wpdb->suppress_errors( true );

		try {
			$payload = $this->processTableAction( [
				'sub_action' => ImportExportSitesTableAction::SUB_ACTION_RETRY_INVITATION,
				'rids'       => [ $eligible->id ],
			] );
		}
		finally {
			\remove_filter( 'query', $queryFailure, 1000 );
			$wpdb->show_errors( $previousShowErrors );
			$wpdb->suppress_errors( $previousSuppressErrors );
		}

		$this->assertTrue( $payload[ 'success' ] ?? false );
		$this->assertTrue( $payload[ 'table_reload' ] ?? false );
		$this->assertSame( 0, $payload[ 'queued_count' ] ?? null );
		$this->assertSame( 0, $payload[ 'skipped_count' ] ?? null );
		$this->assertSame( 1, $payload[ 'failed_count' ] ?? null );
		$this->assertSame( SitesDB::QUEUE_PENDING_CONNECTION, $repo->findById( $eligible->id, true )->queue_status );
		$this->assertFalse( \wp_next_scheduled( ( new QueueScheduler() )->hook() ) );
	}

	public function test_mixed_retry_results_report_all_counts_and_schedule_the_queued_row() :void {
		global $wpdb;
		$repo = new SiteRepository();
		$queued = $repo->upsertPendingClientSite( 'https://retry-mixed-queued.example.com', SitesDB::SOURCE_MANUAL, false );
		$skipped = $repo->upsertPendingClientSite( 'https://retry-mixed-skipped.example.com', SitesDB::SOURCE_MANUAL, true );
		$failed = $repo->upsertPendingClientSite( 'https://retry-mixed-failed.example.com', SitesDB::SOURCE_MANUAL, false );
		$skippedBefore = $repo->findById( $skipped->id, true )->getRawData();
		$failedBefore = $repo->findById( $failed->id, true )->getRawData();
		$table = $this->requireController()->db_con->import_export_sites->getTable();
		$targetRow = \sprintf( '`id`=%d AND', $failed->id );
		$failureInjected = false;
		$queryFailure = static function ( string $query ) use ( $table, $targetRow, &$failureInjected ) :string {
			$isTargetCas = \strpos( $query, "UPDATE `{$table}`" ) !== false
						   && \strpos( $query, $targetRow ) !== false
						   && \strpos( $query, 'BINARY `meta`=BINARY' ) !== false;
			if ( $isTargetCas ) {
				$failureInjected = true;
				return 'UPDATE intentionally_invalid_mixed_retry_sql';
			}
			return $query;
		};
		$this->assertFalse( \wp_next_scheduled( ( new QueueScheduler() )->hook() ) );
		\add_filter( 'query', $queryFailure, 1000 );
		$previousShowErrors = $wpdb->hide_errors();
		$previousSuppressErrors = $wpdb->suppress_errors( true );

		try {
			$payload = $this->processTableAction( [
				'sub_action' => ImportExportSitesTableAction::SUB_ACTION_RETRY_INVITATION,
				'rids'       => [ $queued->id, $skipped->id, $failed->id ],
			] );
		}
		finally {
			\remove_filter( 'query', $queryFailure, 1000 );
			$wpdb->show_errors( $previousShowErrors );
			$wpdb->suppress_errors( $previousSuppressErrors );
		}

		$this->assertTrue( $failureInjected );
		$this->assertTrue( $payload[ 'success' ] ?? false );
		$this->assertTrue( $payload[ 'table_reload' ] ?? false );
		$this->assertSame( 1, $payload[ 'queued_count' ] ?? null );
		$this->assertSame( 1, $payload[ 'skipped_count' ] ?? null );
		$this->assertSame( 1, $payload[ 'failed_count' ] ?? null );
		$this->assertSame( SitesDB::QUEUE_PENDING_INVITE, $repo->findById( $queued->id, true )->queue_status );
		$this->assertSame( $skippedBefore, $repo->findById( $skipped->id, true )->getRawData() );
		$this->assertSame( $failedBefore, $repo->findById( $failed->id, true )->getRawData() );
		$this->assertNotFalse( \wp_next_scheduled( ( new QueueScheduler() )->hook() ) );
	}

	public function test_retry_invitation_requires_valid_nonce_before_mutation() :void {
		$repo = new SiteRepository();
		$eligible = $repo->upsertPendingClientSite( 'https://retry-nonce.example.com', SitesDB::SOURCE_MANUAL, false );
		$this->requireController()->this_req->wp_is_ajax = true;

		try {
			$this->expectException( InvalidActionNonceException::class );
			( new ActionProcessor() )->processAction( ImportExportSitesTableAction::SLUG, [
				'sub_action' => ImportExportSitesTableAction::SUB_ACTION_RETRY_INVITATION,
				'rids'       => [ $eligible->id ],
				ActionData::FIELD_NONCE => '',
			] );
		}
		finally {
			$this->assertSame( SitesDB::QUEUE_PENDING_CONNECTION, $repo->findById( $eligible->id, true )->queue_status );
			$this->assertFalse( \wp_next_scheduled( ( new QueueScheduler() )->hook() ) );
		}
	}

	public function test_retry_invitation_requires_security_admin_before_mutation() :void {
		$repo = new SiteRepository();
		$eligible = $repo->upsertPendingClientSite( 'https://retry-security-admin.example.com', SitesDB::SOURCE_MANUAL, false );
		$this->requireController()->this_req->is_security_admin = false;

		try {
			$this->expectException( SecurityAdminRequiredException::class );
			$this->processTableAction( [
				'sub_action' => ImportExportSitesTableAction::SUB_ACTION_RETRY_INVITATION,
				'rids'       => [ $eligible->id ],
			] );
		}
		finally {
			$this->assertSame( SitesDB::QUEUE_PENDING_CONNECTION, $repo->findById( $eligible->id, true )->queue_status );
			$this->assertFalse( \wp_next_scheduled( ( new QueueScheduler() )->hook() ) );
			$this->requireController()->this_req->is_security_admin = true;
		}
	}

	private function processTableAction( array $data ) :array {
		$this->requireController()->this_req->wp_is_ajax = true;
		return ( new ActionProcessor() )->processAction(
			ImportExportSitesTableAction::SLUG,
			ActionData::Build( ImportExportSitesTableAction::class, true, $data )
		)->payload();
	}
}
