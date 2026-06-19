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
		return ( new Record() )->applyFromArray( \array_merge( [
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
		], $overrides ) );
	}
}
