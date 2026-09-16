<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Tables\ImportExportSites;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ImportExportSites\Ops\{
	Handler as SitesDB,
	Record
};
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\ImportExportSites\{
	BuildImportExportSitesTableData,
	SiteSyncStatusBuilder
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Sites\InvitationMetadata;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Diagnostics\{
	ObservationPresenter,
	SyncObservation
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	ServicesState,
	UnitTestRequest
};

class SiteSyncStatusBuilderTest extends BaseUnitTest {

	private const NOW = 1712620800;

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		$this->servicesSnapshot = ServicesState::snapshot();
		ServicesState::installItems( [
			'service_request' => new UnitTestRequest( [], '127.0.0.1', self::NOW ),
		] );
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( 'esc_html' )->alias( static fn( $text ) :string => \htmlspecialchars( (string)$text, \ENT_QUOTES, 'UTF-8' ) );
		Functions\when( 'esc_attr' )->alias( static fn( $text ) :string => \htmlspecialchars( (string)$text, \ENT_QUOTES, 'UTF-8' ) );
		Functions\when( 'wp_date' )->alias( static fn( string $format, int $timestamp ) :string => \gmdate( $format, $timestamp ) );
	}

	protected function tearDown() :void {
		ServicesState::restore( $this->servicesSnapshot );
		parent::tearDown();
	}

	public function test_stale_failures_are_ignored_during_unexpired_waiting_export() :void {
		$record = $this->record( [
			'queue_status'           => SitesDB::QUEUE_WAITING_EXPORT,
			'expected_export_by'     => self::NOW + 600,
			'last_ping_success_at'   => self::NOW - 30,
			'last_ping_failure_at'   => self::NOW - 600,
			'last_ping_error'        => 'old ping failure',
			'last_export_failure_at' => self::NOW - 500,
			'last_export_error'      => 'old export failure',
			'consecutive_failures'   => 7,
		] );

		$this->assertSame( SiteSyncStatusBuilder::STATE_PENDING, $this->builder()->stateForRecord( $record ) );
	}

	public function test_expired_waiting_export_is_problem() :void {
		$record = $this->record( [
			'queue_status'         => SitesDB::QUEUE_WAITING_EXPORT,
			'expected_export_by'   => self::NOW - 1,
			'last_ping_success_at' => self::NOW - 60,
		] );

		$status = $this->builder()->build( $record );

		$this->assertSame( SiteSyncStatusBuilder::STATE_PROBLEM, $status[ 'state_key' ] );
	}

	/**
	 * @dataProvider waitingExportBoundaryProvider
	 */
	public function test_waiting_export_expiry_boundary(
		int $deadline,
		int $pingSuccess,
		int $exportSuccess,
		string $expectedState
	) :void {
		$record = $this->record( [
			'queue_status'           => SitesDB::QUEUE_WAITING_EXPORT,
			'expected_export_by'     => $deadline,
			'last_ping_success_at'   => $pingSuccess,
			'last_export_success_at' => $exportSuccess,
		] );

		$this->assertSame( $expectedState, $this->builder()->stateForRecord( $record ) );
	}

	public static function waitingExportBoundaryProvider() :array {
		return [
			'absent deadline' => [ 0, self::NOW - 10, 0, SiteSyncStatusBuilder::STATE_PENDING ],
			'before deadline without success' => [ self::NOW + 1, self::NOW - 10, 0, SiteSyncStatusBuilder::STATE_PENDING ],
			'at deadline without success' => [ self::NOW, self::NOW - 10, 0, SiteSyncStatusBuilder::STATE_PROBLEM ],
			'after deadline with equal positive success' => [ self::NOW - 1, self::NOW - 10, self::NOW - 10, SiteSyncStatusBuilder::STATE_PENDING ],
			'after deadline with both timestamps zero' => [ self::NOW - 1, 0, 0, SiteSyncStatusBuilder::STATE_PROBLEM ],
			'after deadline with older success' => [ self::NOW - 1, self::NOW - 10, self::NOW - 11, SiteSyncStatusBuilder::STATE_PROBLEM ],
			'after deadline with newer success' => [ self::NOW - 1, self::NOW - 10, self::NOW - 9, SiteSyncStatusBuilder::STATE_PENDING ],
		];
	}

	public function test_summary_uses_explicit_last_export_request_label() :void {
		$status = $this->builder()->build( $this->record( [
			'queue_status'            => SitesDB::QUEUE_IDLE,
			'last_export_success_at'  => self::NOW - 20,
			'last_export_request_at'  => self::NOW - 25,
			'last_export_result_code' => SitesDB::EXPORT_RESULT_SUCCESS,
		] ) );

		$this->assertStringContainsString( 'Last export request', $status[ 'summary_html' ] );
		$this->assertStringNotContainsString( 'Last request:', $status[ 'summary_html' ] );
	}

	public function test_queued_first_sync_is_pending() :void {
		$record = $this->record( [
			'queue_status' => SitesDB::QUEUE_QUEUED,
		] );

		$this->assertSame( SiteSyncStatusBuilder::STATE_PENDING, $this->builder()->stateForRecord( $record ) );
	}

	/**
	 * @dataProvider pendingQueueStatusProvider
	 */
	public function test_pending_invite_and_connection_queue_states_are_pending( string $queueStatus ) :void {
		$record = $this->record( [
			'queue_status' => $queueStatus,
		] );

		$this->assertSame( SiteSyncStatusBuilder::STATE_PENDING, $this->builder()->stateForRecord( $record ) );
	}

	/**
	 * @dataProvider invitationPresentationStateProvider
	 */
	public function test_invitation_metadata_maps_to_stable_presentation_state(
		string $queueStatus,
		array $invitation,
		string $expectedState
	) :void {
		$record = $this->record( [
			'queue_status' => $queueStatus,
			'meta'         => empty( $invitation ) ? [] : [ InvitationMetadata::META_KEY => $invitation ],
		] );

		$this->assertSame( $expectedState, $this->builder()->invitationStateForRecord( $record ) );
	}

	public function test_invitation_details_include_bounded_attempt_and_http_values() :void {
		$status = $this->builder()->build( $this->record( [
			'queue_status' => SitesDB::QUEUE_PENDING_CONNECTION,
			'meta'         => [ InvitationMetadata::META_KEY => [
				'cycle_id'               => 'cycle-a',
				'attempts_started'        => 2,
				'last_attempt_started_at' => self::NOW - 60,
				'last_result'             => InvitationMetadata::RESULT_HTTP_RESPONSE,
				'last_http_status'        => 204,
			] ],
		] ) );

		$this->assertStringContainsString( '2 / 3', $status[ 'details_html' ] );
		$this->assertStringContainsString( '204', $status[ 'details_html' ] );
	}

	public function test_queued_after_failure_is_problem() :void {
		$record = $this->record( [
			'queue_status'          => SitesDB::QUEUE_QUEUED,
			'last_ping_failure_at'  => self::NOW - 20,
			'last_ping_error'       => 'service unavailable',
			'consecutive_failures'  => 1,
			'last_export_success_at' => 0,
		] );

		$this->assertSame( SiteSyncStatusBuilder::STATE_PROBLEM, $this->builder()->stateForRecord( $record ) );
	}

	public function test_idle_after_export_success_is_working() :void {
		$record = $this->record( [
			'queue_status'           => SitesDB::QUEUE_IDLE,
			'last_export_success_at' => self::NOW - 20,
			'last_export_result_code' => SitesDB::EXPORT_RESULT_SUCCESS,
		] );

		$this->assertSame( SiteSyncStatusBuilder::STATE_WORKING, $this->builder()->stateForRecord( $record ) );
	}

	public function test_inactive_rows_are_inactive() :void {
		$record = $this->record( [
			'status'       => SitesDB::STATUS_DELETED,
			'queue_status' => SitesDB::QUEUE_IDLE,
		] );

		$this->assertSame( SiteSyncStatusBuilder::STATE_INACTIVE, $this->builder()->stateForRecord( $record ) );
	}

	public function test_import_id_is_included_in_generated_details() :void {
		$status = $this->builder()->build( $this->record( [
			'import_id' => 'secret-import-id',
		] ) );

		$this->assertStringContainsString( 'secret-import-id', $status[ 'details_html' ] );
	}

	public function test_diagnostic_contract_exposes_three_independent_empty_slots() :void {
		$status = $this->builder()->build( $this->record() );

		$this->assertSame( [ 'notification', 'verification', 'export' ], \array_keys( $status[ 'diagnostics' ] ) );
		foreach ( $status[ 'diagnostics' ] as $diagnostic ) {
			$this->assertFalse( $diagnostic[ 'has_result' ] );
		}
	}

	public function test_diagnostic_contract_preserves_machine_results_and_qualifies_failed_claim() :void {
		$status = $this->builder()->build( $this->record( [
			'queue_status' => SitesDB::QUEUE_IDLE,
			'meta'         => [
				'sync_observations' => [
					'notification' => SyncObservation::create(
						self::NOW - 30,
						SyncObservation::PHASE_NOTIFICATION,
						SyncObservation::RESULT_HTTP_RESPONSE_RECEIVED,
						SyncObservation::VERIFICATION_NOT_APPLICABLE,
						[ 'http_status' => 202 ]
					),
					'verification' => SyncObservation::create(
						self::NOW - 20,
						SyncObservation::PHASE_VERIFICATION,
						SyncObservation::RESULT_MISMATCHED_ID,
						SyncObservation::VERIFICATION_FAILED
					),
					'export'       => SyncObservation::create(
						self::NOW - 10,
						SyncObservation::PHASE_EXPORT,
						SyncObservation::RESULT_EXPORT_SERVED,
						SyncObservation::VERIFICATION_ESTABLISHED,
						[ 'http_status' => 403 ]
					),
				],
			],
		] ) );

		$this->assertSame( SiteSyncStatusBuilder::STATE_NEVER_SYNCED, $status[ 'state_key' ] );
		$this->assertSame(
			[
				SyncObservation::RESULT_HTTP_RESPONSE_RECEIVED,
				SyncObservation::RESULT_MISMATCHED_ID,
				SyncObservation::RESULT_EXPORT_SERVED,
			],
			\array_column( $status[ 'diagnostics' ], 'result' )
		);
		$this->assertNotEmpty( $status[ 'diagnostics' ][ 'verification' ][ 'qualification' ] );
		$this->assertSame( 202, $status[ 'diagnostics' ][ 'notification' ][ 'http_status' ] );
		$this->assertSame( 403, $status[ 'diagnostics' ][ 'export' ][ 'http_status' ] );
	}

	public function test_remote_import_cooldown_does_not_claim_unknown_eligibility_time() :void {
		$presented = ( new ObservationPresenter() )->present( SyncObservation::create(
			self::NOW,
			SyncObservation::PHASE_CLIENT_IMPORT,
			SyncObservation::RESULT_PARSED_REJECTION,
			SyncObservation::VERIFICATION_FAILED,
			[ 'error_category' => SyncObservation::ERROR_REMOTE_COOLDOWN ]
		) );

		$this->assertNull( $presented[ 'eligible_at' ] );
		$this->assertNull( $presented[ 'eligible_at_display' ] );
		$this->assertNotSame( '', $presented[ 'next_check' ] );

		$knownEligibility = ( new ObservationPresenter() )->present( SyncObservation::create(
			self::NOW,
			SyncObservation::PHASE_EXPORT,
			SyncObservation::RESULT_EXPORT_COOLDOWN,
			SyncObservation::VERIFICATION_ESTABLISHED,
			[ 'eligible_at' => self::NOW + 60 ]
		) );
		$this->assertSame( self::NOW + 60, $knownEligibility[ 'eligible_at' ] );
		$this->assertNotNull( $knownEligibility[ 'eligible_at_display' ] );
		$this->assertNotSame( '', $knownEligibility[ 'next_check' ] );
		$this->assertNotSame( $presented[ 'next_check' ], $knownEligibility[ 'next_check' ] );
	}

	public function test_remote_export_exception_has_distinct_bounded_guidance_and_manual_message() :void {
		$presenter = new ObservationPresenter();
		$remoteExport = SyncObservation::create(
			self::NOW,
			SyncObservation::PHASE_CLIENT_IMPORT,
			SyncObservation::RESULT_PARSED_REJECTION,
			SyncObservation::VERIFICATION_FAILED,
			[ 'error_category' => SyncObservation::ERROR_REMOTE_EXPORT_EXCEPTION ]
		);
		$genericRejection = SyncObservation::create(
			self::NOW,
			SyncObservation::PHASE_CLIENT_IMPORT,
			SyncObservation::RESULT_PARSED_REJECTION,
			SyncObservation::VERIFICATION_FAILED
		);
		$localException = SyncObservation::create(
			self::NOW,
			SyncObservation::PHASE_CLIENT_IMPORT,
			SyncObservation::RESULT_LOCAL_IMPORT_EXCEPTION,
			SyncObservation::VERIFICATION_FAILED
		);

		$remote = $presenter->present( $remoteExport );
		$generic = $presenter->present( $genericRejection );
		$local = $presenter->present( $localException );
		$this->assertNotSame( $generic[ 'label' ], $remote[ 'label' ] );
		$this->assertNotSame( $generic[ 'explanation' ], $remote[ 'explanation' ] );
		$this->assertNotSame( '', $remote[ 'next_check' ] );
		$this->assertNotSame( $generic[ 'next_check' ], $remote[ 'next_check' ] );

		$manual = $presenter->failureMessage( $remoteExport, 'unsafe remote fallback' );
		$this->assertStringContainsString( $remote[ 'explanation' ], $manual );
		$this->assertStringContainsString( $remote[ 'next_check' ], $manual );
		$this->assertStringNotContainsString( 'unsafe remote fallback', $manual );
		$this->assertNotSame( $local[ 'explanation' ], $remote[ 'explanation' ] );
	}

	public function test_callback_guidance_and_response_failure_messages_preserve_result_semantics() :void {
		$presenter = new ObservationPresenter();
		$presented = [];
		foreach ( [
			SyncObservation::RESULT_CALLBACK_TRANSPORT_FAILURE,
			SyncObservation::RESULT_CALLBACK_INVALID_RESPONSE,
			SyncObservation::RESULT_CALLBACK_DID_NOT_CONFIRM,
			SyncObservation::RESULT_INVALID_RESPONSE,
			SyncObservation::RESULT_EMPTY_RESPONSE,
		] as $result ) {
			$isCallback = \in_array( $result, [
				SyncObservation::RESULT_CALLBACK_TRANSPORT_FAILURE,
				SyncObservation::RESULT_CALLBACK_INVALID_RESPONSE,
				SyncObservation::RESULT_CALLBACK_DID_NOT_CONFIRM,
			], true );
			$presented[ $result ] = $presenter->present( SyncObservation::create(
				self::NOW,
				$isCallback ? SyncObservation::PHASE_VERIFICATION : SyncObservation::PHASE_CLIENT_IMPORT,
				$result,
				$isCallback ? SyncObservation::VERIFICATION_FAILED : SyncObservation::VERIFICATION_NOT_APPLICABLE
			) );
		}

		$callbackNextCheck = $presented[ SyncObservation::RESULT_CALLBACK_TRANSPORT_FAILURE ][ 'next_check' ];
		$this->assertSame( $callbackNextCheck, $presented[ SyncObservation::RESULT_CALLBACK_INVALID_RESPONSE ][ 'next_check' ] );
		$this->assertSame( $callbackNextCheck, $presented[ SyncObservation::RESULT_CALLBACK_DID_NOT_CONFIRM ][ 'next_check' ] );
		$this->assertNotSame( $callbackNextCheck, $presented[ SyncObservation::RESULT_INVALID_RESPONSE ][ 'next_check' ] );

		$messages = [];
		foreach ( [ SyncObservation::RESULT_EMPTY_RESPONSE, SyncObservation::RESULT_INVALID_RESPONSE ] as $result ) {
			$observation = SyncObservation::create(
				self::NOW,
				SyncObservation::PHASE_CLIENT_IMPORT,
				$result,
				SyncObservation::VERIFICATION_NOT_APPLICABLE
			);
			$messages[ $result ] = $presenter->failureMessage( $observation, 'fallback' );
			$this->assertStringContainsString( $presented[ $result ][ 'explanation' ], $messages[ $result ] );
			$this->assertStringContainsString( $presented[ $result ][ 'next_check' ], $messages[ $result ] );
		}
		$this->assertSame(
			$presented[ SyncObservation::RESULT_EMPTY_RESPONSE ][ 'next_check' ],
			$presented[ SyncObservation::RESULT_INVALID_RESPONSE ][ 'next_check' ]
		);
		$this->assertNotSame( $messages[ SyncObservation::RESULT_EMPTY_RESPONSE ], $messages[ SyncObservation::RESULT_INVALID_RESPONSE ] );
	}

	public function test_table_row_contract_uses_summary_fields_without_raw_metadata() :void {
		$rows = ( new BuildImportExportSitesTableData() )->exportBuildTableRowsFromRawRecords( [
			$this->record( [
				'id'                     => 99,
				'url'                    => 'https://contract.example.com',
				'import_id'              => 'secret-import-id',
				'queue_status'           => SitesDB::QUEUE_IDLE,
				'last_export_success_at' => self::NOW - 60,
				'last_export_result_code' => SitesDB::EXPORT_RESULT_SUCCESS,
			] ),
		] );

		$row = $rows[ 0 ];
		$expectedKeys = [
			'actions',
			'profile',
			'queue_status',
			'queue_status_key',
			'rid',
			'status',
			'status_key',
			'sync_state',
			'sync_status',
			'updated_at',
			'url',
			'url_display',
		];
		$actualKeys = \array_keys( $row );
		\sort( $actualKeys );

		$this->assertSame( $expectedKeys, $actualKeys );
		foreach ( [
			'last_ping_attempt',
			'last_ping_success',
			'last_ping_failure',
			'last_export_request',
			'last_export_success',
			'last_export_failure',
			'last_ping_http_code',
			'last_export_result_code',
			'consecutive_failures',
			'details',
		] as $removedKey ) {
			$this->assertArrayNotHasKey( $removedKey, $row );
		}
		$this->assertSame( SiteSyncStatusBuilder::STATE_WORKING, $row[ 'sync_state' ] );
		$this->assertSame( 'https://contract.example.com', $row[ 'url' ] );
		$this->assertStringContainsString( 'https://contract.example.com', $row[ 'url_display' ] );
		$this->assertStringContainsString( 'data-import-export-site-import-id="secret-import-id"', $row[ 'url_display' ] );
		$this->assertSame( 'Default Profile', $row[ 'profile' ] );
		$this->assertStringContainsString( 'data-import-export-site-delete="1"', $row[ 'actions' ] );
		$this->assertStringNotContainsString( 'data-import-export-site-repair="1"', $row[ 'actions' ] );
		$this->assertStringContainsString( 'data-rid="99"', $row[ 'actions' ] );
		$this->assertStringNotContainsString( 'secret-import-id', $row[ 'actions' ] );
		$this->assertStringContainsString( 'data-shield-sync-details-trigger="1"', $row[ 'sync_status' ] );
		$this->assertStringContainsString( 'secret-import-id', $row[ 'sync_status' ] );
	}

	public function test_problem_table_row_includes_repair_action() :void {
		$rows = ( new BuildImportExportSitesTableData() )->exportBuildTableRowsFromRawRecords( [
			$this->record( [
				'id'                      => 123,
				'import_id'               => 'secret-import-id',
				'queue_status'            => SitesDB::QUEUE_QUEUED,
				'last_export_failure_at'  => self::NOW - 10,
				'last_export_error'       => 'verify failed',
				'consecutive_failures'    => 1,
			] ),
		] );

		$row = $rows[ 0 ];

		$this->assertSame( SiteSyncStatusBuilder::STATE_PROBLEM, $row[ 'sync_state' ] );
		$this->assertStringContainsString( 'data-import-export-site-repair="1"', $row[ 'actions' ] );
		$this->assertStringContainsString( 'data-import-export-site-delete="1"', $row[ 'actions' ] );
		$this->assertStringContainsString( 'data-rid="123"', $row[ 'actions' ] );
		$this->assertStringNotContainsString( 'secret-import-id', $row[ 'actions' ] );
	}

	/**
	 * @dataProvider repairActionVisibilityProvider
	 */
	public function test_repair_action_visibility_follows_sync_state( array $overrides, string $expectedState, bool $expectRepairAction ) :void {
		$rows = ( new BuildImportExportSitesTableData() )->exportBuildTableRowsFromRawRecords( [
			$this->record( \array_merge( [
				'id' => 77,
			], $overrides ) ),
		] );
		$row = $rows[ 0 ];

		$this->assertSame( $expectedState, $row[ 'sync_state' ] );
		$this->assertStringContainsString( 'data-import-export-site-delete="1"', $row[ 'actions' ] );
		if ( $expectRepairAction ) {
			$this->assertStringContainsString( 'data-import-export-site-repair="1"', $row[ 'actions' ] );
		}
		else {
			$this->assertStringNotContainsString( 'data-import-export-site-repair="1"', $row[ 'actions' ] );
		}
	}

	public function test_search_panes_validate_allowed_values_and_discard_invalid_values() :void {
		$builder = new BuildImportExportSitesTableData();

		$this->assertSame( [
			'sync_state'       => [ SiteSyncStatusBuilder::STATE_PROBLEM ],
			'status_key'       => [ SitesDB::STATUS_ACTIVE ],
			'queue_status_key' => [ SitesDB::QUEUE_QUEUED, SitesDB::QUEUE_PENDING_INVITE ],
		], $builder->exportValidateSearchPanes( [
			'sync_state'       => [ SiteSyncStatusBuilder::STATE_PROBLEM, 'bad-state', [ 'nested-bad-state' ] ],
			'status_key'       => [ SitesDB::STATUS_ACTIVE, 'bad-status', [ 'nested-bad-status' ] ],
			'queue_status_key' => [ SitesDB::QUEUE_QUEUED, SitesDB::QUEUE_PENDING_INVITE, 'bad-queue', [ 'nested-bad-queue' ] ],
			'unknown'          => [ 'anything' ],
		] ) );
	}

	public function test_search_panes_build_state_registration_and_queue_wheres() :void {
		$builder = new BuildImportExportSitesTableData();
		$builder->table_data = [
			'searchPanes' => [
				'sync_state'       => [ SiteSyncStatusBuilder::STATE_PROBLEM ],
				'status_key'       => [ SitesDB::STATUS_ACTIVE ],
				'queue_status_key' => [ SitesDB::QUEUE_QUEUED ],
			],
		];

		$wheres = $builder->exportBuildWheresFromSearchParams();

		$this->assertCount( 3, $wheres );
		$this->assertStringContainsString( '`status`=', $wheres[ 0 ] );
		$this->assertStringContainsString( '`status`=', $wheres[ 1 ] );
		$this->assertStringContainsString( '`queue_status`=', $wheres[ 2 ] );
	}

	private function builder() :SiteSyncStatusBuilder {
		return new SiteSyncStatusBuilder( self::NOW );
	}

	public static function pendingQueueStatusProvider() :array {
		return [
			'pending invite' => [ SitesDB::QUEUE_PENDING_INVITE ],
			'pending connection' => [ SitesDB::QUEUE_PENDING_CONNECTION ],
		];
	}

	public static function invitationPresentationStateProvider() :array {
		return [
			'queued before first attempt' => [
				SitesDB::QUEUE_PENDING_INVITE,
				[ 'cycle_id' => 'cycle-a', 'attempts_started' => 0 ],
				SiteSyncStatusBuilder::INVITATION_STATE_QUEUED,
			],
			'unknown retryable attempt' => [
				SitesDB::QUEUE_PENDING_INVITE,
				[ 'cycle_id' => 'cycle-a', 'attempts_started' => 1, 'last_result' => InvitationMetadata::RESULT_STARTED ],
				SiteSyncStatusBuilder::INVITATION_STATE_STARTED_RETRYABLE,
			],
			'failure awaiting retry' => [
				SitesDB::QUEUE_PENDING_INVITE,
				[ 'cycle_id' => 'cycle-a', 'attempts_started' => 2, 'last_result' => InvitationMetadata::RESULT_TRANSPORT_FAILURE ],
				SiteSyncStatusBuilder::INVITATION_STATE_RETRY_SCHEDULED,
			],
			'unknown final attempt awaiting settlement' => [
				SitesDB::QUEUE_PENDING_INVITE,
				[ 'cycle_id' => 'cycle-a', 'attempts_started' => 3, 'last_result' => InvitationMetadata::RESULT_STARTED ],
				SiteSyncStatusBuilder::INVITATION_STATE_FINAL_SETTLEMENT,
			],
			'response received but unconfirmed' => [
				SitesDB::QUEUE_PENDING_CONNECTION,
				[ 'cycle_id' => 'cycle-a', 'attempts_started' => 1, 'last_result' => InvitationMetadata::RESULT_HTTP_RESPONSE, 'last_http_status' => 204 ],
				SiteSyncStatusBuilder::INVITATION_STATE_RESPONSE_UNCONFIRMED,
			],
			'known failure exhausted' => [
				SitesDB::QUEUE_PENDING_CONNECTION,
				[ 'cycle_id' => 'cycle-a', 'attempts_started' => 3, 'last_result' => InvitationMetadata::RESULT_HTTP_FAILURE, 'last_http_status' => 503 ],
				SiteSyncStatusBuilder::INVITATION_STATE_EXHAUSTED_FAILURE,
			],
			'unknown final attempt settled' => [
				SitesDB::QUEUE_PENDING_CONNECTION,
				[ 'cycle_id' => 'cycle-a', 'attempts_started' => 3, 'last_result' => InvitationMetadata::RESULT_STARTED ],
				SiteSyncStatusBuilder::INVITATION_STATE_EXHAUSTED_UNKNOWN,
			],
			'legacy passive connection' => [
				SitesDB::QUEUE_PENDING_CONNECTION,
				[],
				SiteSyncStatusBuilder::INVITATION_STATE_PASSIVE,
			],
		];
	}

	public static function repairActionVisibilityProvider() :array {
		return [
			'working idle success'           => [
				[
					'queue_status'              => SitesDB::QUEUE_IDLE,
					'last_export_success_at'    => self::NOW - 60,
					'last_export_result_code'   => SitesDB::EXPORT_RESULT_SUCCESS,
				],
				SiteSyncStatusBuilder::STATE_WORKING,
				false,
			],
			'pending queued first sync'      => [
				[
					'queue_status' => SitesDB::QUEUE_QUEUED,
				],
				SiteSyncStatusBuilder::STATE_PENDING,
				false,
			],
			'pending waiting export'         => [
				[
					'queue_status'         => SitesDB::QUEUE_WAITING_EXPORT,
					'expected_export_by'   => self::NOW + 300,
					'last_ping_success_at' => self::NOW - 30,
				],
				SiteSyncStatusBuilder::STATE_PENDING,
				false,
			],
			'pending invite'                 => [
				[
					'queue_status' => SitesDB::QUEUE_PENDING_INVITE,
				],
				SiteSyncStatusBuilder::STATE_PENDING,
				false,
			],
			'pending connection'             => [
				[
					'queue_status' => SitesDB::QUEUE_PENDING_CONNECTION,
				],
				SiteSyncStatusBuilder::STATE_PENDING,
				false,
			],
			'never synced'                   => [
				[],
				SiteSyncStatusBuilder::STATE_NEVER_SYNCED,
				false,
			],
			'inactive deleted'               => [
				[
					'status' => SitesDB::STATUS_DELETED,
				],
				SiteSyncStatusBuilder::STATE_INACTIVE,
				false,
			],
			'problem queued after failure'   => [
				[
					'queue_status'          => SitesDB::QUEUE_QUEUED,
					'last_ping_failure_at'  => self::NOW - 20,
					'last_ping_error'       => 'service unavailable',
					'consecutive_failures'  => 1,
				],
				SiteSyncStatusBuilder::STATE_PROBLEM,
				true,
			],
			'problem expired waiting export' => [
				[
					'queue_status'         => SitesDB::QUEUE_WAITING_EXPORT,
					'expected_export_by'   => self::NOW - 1,
					'last_ping_success_at' => self::NOW - 60,
				],
				SiteSyncStatusBuilder::STATE_PROBLEM,
				true,
			],
		];
	}

	private function record( array $overrides = [] ) :Record {
		$data = \array_merge( [
			'id'                       => 1,
			'url'                      => 'https://sync.example.com',
			'url_hash'                 => \hash( 'md5', 'https://sync.example.com' ),
			'import_id'                => '',
			'source'                   => SitesDB::SOURCE_MANUAL,
			'status'                   => SitesDB::STATUS_ACTIVE,
			'queue_status'             => SitesDB::QUEUE_IDLE,
			'priority'                 => 0,
			'queued_at'                => 0,
			'picked_at'                => 0,
			'lock_until'               => 0,
			'next_ping_at'             => 0,
			'expected_export_by'       => 0,
			'last_ping_attempt_at'     => 0,
			'last_ping_success_at'     => 0,
			'last_ping_failure_at'     => 0,
			'last_ping_http_code'      => 0,
			'last_ping_error'          => '',
			'last_export_request_at'   => 0,
			'last_export_success_at'   => 0,
			'last_export_failure_at'   => 0,
			'last_export_result_code'  => '',
			'last_export_error'        => '',
			'ping_attempts_total'      => 0,
			'consecutive_failures'     => 0,
			'meta'                     => [],
			'created_at'               => self::NOW - 3600,
			'updated_at'               => self::NOW - 60,
			'deleted_at'               => 0,
		], $overrides );
		$meta = \is_array( $data[ 'meta' ] ?? null ) ? $data[ 'meta' ] : [];
		unset( $data[ 'meta' ] );
		$record = ( new Record() )->applyFromArray( $data );
		$record->meta = $meta;
		return $record;
	}
}
