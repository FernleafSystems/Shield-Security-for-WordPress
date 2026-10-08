<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	ActionProcessor,
	Actions\ScanResultsTableAction,
	Exceptions\InvalidActionNonceException
};
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ResultItems\Ops\Handler as ResultItemsHandler;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\{
	ActionsQueueScanResultScopeStateBuilder,
	ScanResultsDisplayOptions
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\ActionRouter\PluginAdminRouteRuntime;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TestDataFactory;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\ServicesState;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter\Support\ActionRequestNonceFixture;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use PHPUnit\Framework\ExpectationFailedException;
use Symfony\Component\Filesystem\Path;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Integrations\WpHashes\ApiBase;
use FernleafSystems\Wordpress\Services\Utilities\Options\Transient;

class ScanResultsTableActionIntegrationTest extends ShieldIntegrationTestCase {

	use ActionRequestNonceFixture;

	public function set_up() {
		parent::set_up();
		$this->requireDb( 'scans' );
		$this->requireDb( 'scan_results' );
		$this->requireDb( 'scan_result_items' );
		$this->requireDb( 'scan_result_item_meta' );
		$this->loginAsSecurityAdmin();
		$this->requireController()->this_req->wp_is_ajax = false;
		Transient::Delete( self::con()->prefix( 'malai_manual_refresh', '_' ) );
		$this->requireController()->opts
			 ->optSet( 'enable_core_file_integrity_scan', 'Y' )
			 ->optSet( 'file_scan_areas', [ 'wp' ] )
			 ->store();
		$this->resetScanResultCountMemoization();
	}

	#[\PHPUnit\Framework\Attributes\DataProvider( 'malwareRefreshResponseProvider' )]
	public function test_manual_malware_refresh_uses_real_lookup_and_reloads_partial_results( bool $partialFailure ) :void {
		$this->requireDb( 'malware' );
		$this->enablePremiumCapabilities( [ 'scan_malware_local', 'scan_malware_malai' ] );
		$now = Services::Request()->ts();
		$scan = TestDataFactory::insertCompletedScan( 'afs' );
		$findings = [];
		$hashes = [];
		foreach ( [ 'clean', 'pending', 'ignored' ] as $key ) {
			$path = 'fixture/refresh-'.$key.'.php';
			$recordID = TestDataFactory::insertMalwareRecord( $path, $key, [
				'malai_status' => 'malware',
				'last_malai_status_at' => $now,
			] );
			$findings[ $key ] = TestDataFactory::insertAfsFileScanResultTracked( $scan, $path, [
				'is_mal' => 1, 'malware_record_id' => $recordID,
			] );
			$findings[ $key ][ 'malware_record_id' ] = $recordID;
			$hashes[ $key ] = self::con()->db_con->malware->getQuerySelector()->byId( $recordID )->hash_sha256;
		}
		TestDataFactory::markScanResultItemIgnored( $findings[ 'ignored' ][ 'result_item_id' ] );
		$optionsSnapshot = $this->snapshotSelectedOptions( [ 'wphashes_api_token' ] );
		$tokenProperty = new \ReflectionProperty( ApiBase::class, 'API_TOKEN' );
		$tokenProperty->setAccessible( true );
		$previousToken = $tokenProperty->getValue();
		self::con()->opts->optSet( 'wphashes_api_token', [
			'token' => \str_repeat( 'a', 40 ), 'expires_at' => $now + \DAY_IN_SECONDS,
			'next_attempt_from' => $now + \DAY_IN_SECONDS, 'valid_license' => true,
		] );
		Transient::Set( 'apto-wphashes-api-available-routes', '#.*#', 300 );
		$requests = [];
		$filter = function ( $pre, array $args, string $url ) use ( &$requests, $hashes, $partialFailure ) {
			$requests[] = [ 'url' => $url, 'method' => $args[ 'method' ], 'body' => $args[ 'body' ] ];
			$this->assertCount( 1, $requests );
			// A second request is rejected even while the first API lookup is still running.
			$blocked = $this->processScanResultsAction( [
				'sub_action' => 'refresh_malware_assessments', 'type' => 'malware', 'file' => 'malware',
			] );
			$this->assertFalse( $blocked[ 'success' ] );
			$this->assertFalse( $blocked[ 'table_reload' ] );
			$statuses = [ $hashes[ 'clean' ] => 'clean' ];
			if ( !$partialFailure ) {
				$statuses[ $hashes[ 'pending' ] ] = 'predicted_clean';
			}
			return [ 'headers' => [], 'body' => \wp_json_encode( [ 'error_code' => 0, 'statuses' => $statuses ] ),
				'response' => [ 'code' => 200, 'message' => '' ], 'cookies' => [] ];
		};
		\add_filter( 'pre_http_request', $filter, 10, 3 );
		try {
			$payload = $this->processScanResultsAction( [
				'sub_action' => 'refresh_malware_assessments', 'type' => 'malware', 'file' => 'malware',
				// Display state and selections must not change the server-owned refresh scope.
				'results_display_options' => ( new ScanResultsDisplayOptions() )->ignoredOnly(),
				'rids' => [ $findings[ 'ignored' ][ 'result_item_id' ] ],
			] );
			$blocked = $this->processScanResultsAction( [
				'sub_action' => 'refresh_malware_assessments', 'type' => 'malware', 'file' => 'malware',
			] );
			$this->assertFalse( $blocked[ 'success' ] );
			$this->assertFalse( $blocked[ 'table_reload' ] );
		}
		finally {
			\remove_filter( 'pre_http_request', $filter, 10 );
			$tokenProperty->setValue( null, $previousToken );
			$this->restoreSelectedOptions( $optionsSnapshot );
		}
		$this->assertSame( !$partialFailure, $payload[ 'success' ] );
		$this->assertFalse( $payload[ 'page_reload' ] );
		$this->assertTrue( $payload[ 'table_reload' ] );
		$this->assertCount( 1, $requests );
		$this->assertStringContainsString( '/v2/malai/malware/statuses', $requests[ 0 ][ 'url' ] );
		$this->assertSame( 'POST', $requests[ 0 ][ 'method' ] );
		$this->assertSame( [ $hashes[ 'clean' ], $hashes[ 'pending' ] ], $requests[ 0 ][ 'body' ][ 'hashes_sha256' ] );
		$this->assertGreaterThan( 0, (int)self::con()->db_con->scan_result_items->getQuerySelector()->byId( $findings[ 'clean' ][ 'result_item_id' ] )->auto_filtered_at );
		$pending = self::con()->db_con->malware->getQuerySelector()->byId( $findings[ 'pending' ][ 'malware_record_id' ] );
		$this->assertSame( $partialFailure ? 'unknown' : 'predicted_clean', $pending->malai_status );
		$this->assertSame( $partialFailure ? 'lookup_failed' : '', $pending->malai_status_context );
		$this->assertSame( 'malware', self::con()->db_con->malware->getQuerySelector()->byId( $findings[ 'ignored' ][ 'malware_record_id' ] )->malai_status );
	}

	public static function malwareRefreshResponseProvider() :array {
		return [ 'success' => [ false ], 'partial failure' => [ true ] ];
	}

	#[\PHPUnit\Framework\Attributes\DataProvider( 'malwareRefreshWaitProvider' )]
	public function test_manual_malware_refresh_enforces_transient_wait( int $seconds, bool $allowed ) :void {
		$this->enablePremiumCapabilities( [ 'scan_malware_malai' ] );
		$key = self::con()->prefix( 'malai_manual_refresh', '_' );
		$now = Services::Request()->ts();
		$nextAllowed = $now + $seconds;
		// WordPress can return scalar option values as strings after a database read.
		Transient::Set( $key, (string)$nextAllowed, 300 );
		$snapshot = ServicesState::snapshot();
		$request = $this->createPartialMock( \FernleafSystems\Wordpress\Services\Core\Request::class, [ 'ts' ] );
		$request->applyFromArray( Services::Request()->getRawData() );
		$request->method( 'ts' )->willReturn( $now );
		ServicesState::mergeItems( [ 'service_request' => $request ] );
		try {
			$payload = $this->processScanResultsAction( [
				'sub_action' => 'refresh_malware_assessments', 'type' => 'malware', 'file' => 'malware',
			] );
		}
		finally {
			ServicesState::restore( $snapshot );
		}
		$this->assertSame( $allowed, $payload[ 'success' ] );
		$this->assertSame( $allowed, $payload[ 'table_reload' ] );
		$this->assertFalse( $payload[ 'page_reload' ] );
		$this->assertSame( $allowed ? $now + 300 : $nextAllowed, (int)Transient::Get( $key ) );
		if ( !$allowed ) {
			$this->assertStringContainsString( sprintf( '%d min %d sec', \intdiv( $seconds, 60 ), $seconds % 60 ), $payload[ 'message' ] );
		}
	}

	public static function malwareRefreshWaitProvider() :array {
		return [ 'full wait' => [ 300, false ], 'minutes and seconds' => [ 61, false ],
			'last second' => [ 1, false ], 'exact expiry' => [ 0, true ], 'expired' => [ -1, true ] ];
	}

	#[\PHPUnit\Framework\Attributes\DataProvider( 'malwareRefreshRejectionProvider' )]
	public function test_manual_malware_refresh_rejects_unavailable_capability_or_wrong_scope( bool $capable, string $scope ) :void {
		$this->enablePremiumCapabilities( $capable ? [ 'scan_malware_malai' ] : [ 'scan_malware_local' ] );
		$requests = 0;
		$filter = static function () use ( &$requests ) {
			$requests++;
			return new \WP_Error( 'unexpected_refresh_request' );
		};
		\add_filter( 'pre_http_request', $filter );
		try {
			$payload = $this->processScanResultsAction( [
				'sub_action' => 'refresh_malware_assessments', 'type' => $scope, 'file' => $scope,
			] );
		}
		finally {
			\remove_filter( 'pre_http_request', $filter );
		}
		$this->assertFalse( $payload[ 'success' ] );
		$this->assertFalse( $payload[ 'page_reload' ] );
		$this->assertFalse( $payload[ 'table_reload' ] );
		$this->assertSame( 0, $requests );
	}

	public static function malwareRefreshRejectionProvider() :array {
		return [ 'no capability' => [ false, 'malware' ], 'wrong scope' => [ true, 'wordpress' ] ];
	}

	public function test_manual_malware_refresh_with_no_active_findings_is_successful() :void {
		$this->enablePremiumCapabilities( [ 'scan_malware_malai' ] );
		$payload = $this->processScanResultsAction( [
			'sub_action' => 'refresh_malware_assessments', 'type' => 'malware', 'file' => 'malware',
		] );
		$this->assertTrue( $payload[ 'success' ] );
		$this->assertFalse( $payload[ 'page_reload' ] );
		$this->assertTrue( $payload[ 'table_reload' ] );
	}

	public function test_ignore_sub_action_removes_wordpress_row_from_active_results_without_page_reload() :void {
		$tracked = $this->seedWordpressScanResult();
		$scanResultId = (int)( $tracked[ 'result_item_id' ] ?? 0 );
		$resultItemId = (int)( $tracked[ 'result_item_id' ] ?? 0 );
		$this->assertGreaterThan( 0, $scanResultId );
		$this->assertGreaterThan( 0, $resultItemId );

		$beforeActive = $this->retrieveWordpressRows( ( new ScanResultsDisplayOptions() )->activeOnly() );
		$this->assertTrue( $beforeActive[ 'success' ] ?? false );
		$this->assertSame( 1, (int)( $beforeActive[ 'datatable_data' ][ 'recordsTotal' ] ?? 0 ) );
		$this->assertSame( 1, (int)( $beforeActive[ 'datatable_data' ][ 'recordsFiltered' ] ?? 0 ) );
		$this->assertSame(
			[ $scanResultId ],
			\array_column( $beforeActive[ 'datatable_data' ][ 'data' ] ?? [], 'rid' )
		);

		$payload = $this->processScanResultsAction( [
			'sub_action' => 'ignore',
			'rids'       => [ $scanResultId ],
		] );

		$this->assertTrue( $payload[ 'success' ] ?? false );
		$this->assertFalse( $payload[ 'page_reload' ] ?? true );
		$this->assertTrue( $payload[ 'table_reload' ] ?? false );

		$item = self::con()->db_con->scan_result_items->getQuerySelector()->byId( $resultItemId );
		$this->assertNotEmpty( $item );
		$this->assertGreaterThan( 0, (int)( $item->ignored_at ?? 0 ) );

		$afterActive = $this->retrieveWordpressRows( ( new ScanResultsDisplayOptions() )->activeOnly() );
		$this->assertTrue( $afterActive[ 'success' ] ?? false );
		$this->assertSame( 0, (int)( $afterActive[ 'datatable_data' ][ 'recordsTotal' ] ?? -1 ) );
		$this->assertSame( 0, (int)( $afterActive[ 'datatable_data' ][ 'recordsFiltered' ] ?? -1 ) );
		$this->assertCount( 0, $afterActive[ 'datatable_data' ][ 'data' ] ?? [] );

		$afterIgnored = $this->retrieveWordpressRows( ( new ScanResultsDisplayOptions() )->ignoredOnly() );
		$this->assertTrue( $afterIgnored[ 'success' ] ?? false );
		$this->assertSame( 1, (int)( $afterIgnored[ 'datatable_data' ][ 'recordsTotal' ] ?? 0 ) );
		$this->assertSame( 1, (int)( $afterIgnored[ 'datatable_data' ][ 'recordsFiltered' ] ?? 0 ) );
		$this->assertSame(
			[ $scanResultId ],
			\array_column( $afterIgnored[ 'datatable_data' ][ 'data' ] ?? [], 'rid' )
		);
	}

	#[\PHPUnit\Framework\Attributes\DataProvider( 'nonceProtectedSubActionProvider' )]
	public function test_mutating_sub_action_requires_valid_nonce_before_state_change( string $subAction ) :void {
		$tracked = $this->seedWordpressScanResult();
		$resultItemId = (int)( $tracked[ 'result_item_id' ] ?? 0 );
		$this->assertGreaterThan( 0, $resultItemId );
		$snapshot = $this->seedActionNonceContext( ScanResultsTableAction::class );
		$this->mergeCurrentRequestTransport( [
			ActionData::FIELD_NONCE => '',
		] );

		try {
			$this->expectException( InvalidActionNonceException::class );
			( new ActionProcessor() )->processAction( ScanResultsTableAction::SLUG, [
				'sub_action' => $subAction,
				'type' => 'malware',
				'file' => 'malware',
				'rids'       => [ $resultItemId ],
			] );
		}
		finally {
			$item = self::con()->db_con->scan_result_items->getQuerySelector()->byId( $resultItemId );
			$this->restoreActionNonceContext( $snapshot );
			$this->assertNotEmpty( $item );
			$this->assertSame( 0, (int)( $item->ignored_at ?? -1 ) );
		}
	}

	public static function nonceProtectedSubActionProvider() :array {
		return [ 'ignore' => [ 'ignore' ], 'refresh' => [ 'refresh_malware_assessments' ] ];
	}

	public function test_unignore_sub_action_restores_wordpress_row_to_active_results_without_page_reload() :void {
		$ignored = $this->seedWordpressScanResult();
		$resultItemId = (int)( $ignored[ 'result_item_id' ] ?? 0 );
		TestDataFactory::markScanResultItemIgnored( $resultItemId );

		$beforeIgnored = $this->retrieveWordpressRows( ( new ScanResultsDisplayOptions() )->ignoredOnly() );
		$this->assertSame( [ $resultItemId ], \array_column( $beforeIgnored[ 'datatable_data' ][ 'data' ] ?? [], 'rid' ) );

		$payload = $this->processScanResultsAction( [
			'sub_action' => 'unignore',
			'rids'       => [ $resultItemId ],
		] );

		$this->assertTrue( $payload[ 'success' ] ?? false );
		$this->assertFalse( $payload[ 'page_reload' ] ?? true );
		$this->assertTrue( $payload[ 'table_reload' ] ?? false );

		$item = self::con()->db_con->scan_result_items->getQuerySelector()->byId( $resultItemId );
		$this->assertNotEmpty( $item );
		$this->assertSame( 0, (int)( $item->ignored_at ?? -1 ) );

		$afterActive = $this->retrieveWordpressRows( ( new ScanResultsDisplayOptions() )->activeOnly() );
		$this->assertSame( [ $resultItemId ], \array_column( $afterActive[ 'datatable_data' ][ 'data' ] ?? [], 'rid' ) );

		$afterIgnored = $this->retrieveWordpressRows( ( new ScanResultsDisplayOptions() )->ignoredOnly() );
		$this->assertSame( 0, (int)( $afterIgnored[ 'datatable_data' ][ 'recordsTotal' ] ?? -1 ) );

		$secondPayload = $this->processScanResultsAction( [
			'sub_action' => 'unignore',
			'rids'       => [ $resultItemId ],
		] );

		$this->assertTrue( $secondPayload[ 'success' ] ?? false );
		$item = self::con()->db_con->scan_result_items->getQuerySelector()->byId( $resultItemId );
		$this->assertSame( 0, (int)( $item->ignored_at ?? -1 ) );
	}

	public function test_ignore_sub_action_does_not_clean_unrelated_stale_rows_in_same_scan() :void {
		$scanId = TestDataFactory::insertCompletedScan( 'afs' );
		$active = $this->seedWordpressScanResultForScan( $scanId );
		$stale = TestDataFactory::insertAfsFileScanResultTracked( $scanId, $this->corePathFragment( 'wp-admin/update.php' ), [
			'is_in_core'      => 1,
			'is_checksumfail' => 1,
		] );

		$payload = $this->processScanResultsAction( [
			'sub_action' => 'ignore',
			'rids'       => [ (int)$active[ 'result_item_id' ] ],
		] );

		$this->assertTrue( $payload[ 'success' ] ?? false );
		$this->assertFalse( $payload[ 'page_reload' ] ?? true );
		$this->assertTrue( $payload[ 'table_reload' ] ?? false );

		$activeItem = self::con()->db_con->scan_result_items->getQuerySelector()->byId( (int)$active[ 'result_item_id' ] );
		$this->assertNotEmpty( $activeItem );
		$this->assertGreaterThan( 0, (int)( $activeItem->ignored_at ?? 0 ) );

		$staleItem = self::con()->db_con->scan_result_items->getQuerySelector()->byId( (int)$stale[ 'result_item_id' ] );
		$this->assertNotEmpty( $staleItem );
		$this->assertSame( 0, (int)( $staleItem->resolved_at ?? 0 ) );
	}

	public function test_ignore_all_sub_action_ignores_full_active_wordpress_scope_without_page_reload() :void {
		$scanId = TestDataFactory::insertCompletedScan( 'afs' );
		$activeOne = $this->seedWordpressScanResultForScan( $scanId );
		$activeTwo = $this->seedWordpressScanResultForScan( $scanId, 'wp-admin/update.php' );
		$alreadyIgnored = $this->seedWordpressScanResultForScan( $scanId, 'wp-includes/version.php' );
		TestDataFactory::markScanResultItemIgnored( (int)$alreadyIgnored[ 'result_item_id' ] );

		$beforeActive = $this->retrieveWordpressRows( ( new ScanResultsDisplayOptions() )->activeOnly() );
		$this->assertSame( 2, (int)( $beforeActive[ 'datatable_data' ][ 'recordsTotal' ] ?? -1 ) );

		$payload = $this->processScanResultsAction( [
			'sub_action' => 'ignore_all',
			'type'       => 'wordpress',
			'file'       => 'wordpress',
		] );

		$this->assertTrue( $payload[ 'success' ] ?? false );
		$this->assertFalse( $payload[ 'page_reload' ] ?? true );
		$this->assertTrue( $payload[ 'table_reload' ] ?? false );

		$afterActive = $this->retrieveWordpressRows( ( new ScanResultsDisplayOptions() )->activeOnly() );
		$this->assertSame( 0, (int)( $afterActive[ 'datatable_data' ][ 'recordsTotal' ] ?? -1 ) );

		$afterIgnored = $this->retrieveWordpressRows( ( new ScanResultsDisplayOptions() )->ignoredOnly() );
		$this->assertSame( 3, (int)( $afterIgnored[ 'datatable_data' ][ 'recordsTotal' ] ?? -1 ) );
		$this->assertEqualsCanonicalizing(
			[
				(int)$activeOne[ 'result_item_id' ],
				(int)$activeTwo[ 'result_item_id' ],
				(int)$alreadyIgnored[ 'result_item_id' ],
			],
			\array_column( $afterIgnored[ 'datatable_data' ][ 'data' ] ?? [], 'rid' )
		);
	}

	public function test_ignore_all_sub_action_ignores_full_active_malware_scope_without_page_reload() :void {
		try {
			$this->enablePremiumCapabilities( [
				'scan_malware_local',
			] );
			$this->requireController()->opts
				 ->optSet( 'file_scan_areas', [ 'wp', 'malware_php' ] )
				 ->store();
			$this->resetScanResultCountMemoization();

			$scanId = TestDataFactory::insertCompletedScan( 'afs' );
			$activeOne = $this->seedExistingMalwareResultForScan( $scanId, 'active-malware-a.php' );
			$activeTwo = $this->seedExistingMalwareResultForScan( $scanId, 'active-malware-b.php' );
			$alreadyIgnored = $this->seedExistingMalwareResultForScan( $scanId, 'ignored-malware.php' );
			TestDataFactory::markScanResultItemIgnored( (int)$alreadyIgnored[ 'result_item_id' ] );
			$this->resetScanResultCountMemoization();

			$beforeActive = $this->retrieveMalwareRows( ( new ScanResultsDisplayOptions() )->activeOnly() );
			$this->assertSame( 2, (int)( $beforeActive[ 'datatable_data' ][ 'recordsTotal' ] ?? -1 ) );

			$payload = $this->processScanResultsAction( [
				'sub_action' => 'ignore_all',
				'type'       => 'malware',
				'file'       => 'malware',
			] );

			$this->assertTrue( $payload[ 'success' ] ?? false );
			$this->assertFalse( $payload[ 'page_reload' ] ?? true );
			$this->assertTrue( $payload[ 'table_reload' ] ?? false );

			$afterActive = $this->retrieveMalwareRows( ( new ScanResultsDisplayOptions() )->activeOnly() );
			$this->assertSame( 0, (int)( $afterActive[ 'datatable_data' ][ 'recordsTotal' ] ?? -1 ) );

			$afterIgnored = $this->retrieveMalwareRows( ( new ScanResultsDisplayOptions() )->ignoredOnly() );
			$this->assertSame( 3, (int)( $afterIgnored[ 'datatable_data' ][ 'recordsTotal' ] ?? -1 ) );
			$this->assertEqualsCanonicalizing(
				[
					(int)$activeOne[ 'result_item_id' ],
					(int)$activeTwo[ 'result_item_id' ],
					(int)$alreadyIgnored[ 'result_item_id' ],
				],
				\array_column( $afterIgnored[ 'datatable_data' ][ 'data' ] ?? [], 'rid' )
			);
		}
		finally {
			$this->deleteScanActionFixtureRoot();
		}
	}

	public function test_ignore_all_sub_action_rejects_invalid_scope_without_mutating_wordpress_results() :void {
		$scanId = TestDataFactory::insertCompletedScan( 'afs' );
		$activeOne = $this->seedWordpressScanResultForScan( $scanId );
		$activeTwo = $this->seedWordpressScanResultForScan( $scanId, 'wp-admin/update.php' );

		$payload = $this->processScanResultsAction( [
			'sub_action' => 'ignore_all',
			'type'       => 'unknown',
			'file'       => 'wordpress',
		] );

		$this->assertFalse( $payload[ 'success' ] ?? true );
		$this->assertTrue( $payload[ 'page_reload' ] ?? false );

		foreach ( [ $activeOne, $activeTwo ] as $tracked ) {
			$item = self::con()->db_con->scan_result_items->getQuerySelector()->byId( (int)$tracked[ 'result_item_id' ] );
			$this->assertNotEmpty( $item );
			$this->assertSame( 0, (int)( $item->ignored_at ?? -1 ) );
		}

		$afterActive = $this->retrieveWordpressRows( ( new ScanResultsDisplayOptions() )->activeOnly() );
		$this->assertSame( 2, (int)( $afterActive[ 'datatable_data' ][ 'recordsTotal' ] ?? -1 ) );
	}

	public function test_ignore_all_sub_action_returns_in_place_noop_when_scope_is_already_empty() :void {
		$ignored = $this->seedWordpressScanResult();
		TestDataFactory::markScanResultItemIgnored( (int)$ignored[ 'result_item_id' ] );

		$beforeActive = $this->retrieveWordpressRows( ( new ScanResultsDisplayOptions() )->activeOnly() );
		$this->assertSame( 0, (int)( $beforeActive[ 'datatable_data' ][ 'recordsTotal' ] ?? -1 ) );

		$payload = $this->processScanResultsAction( [
			'sub_action' => 'ignore_all',
			'type'       => 'wordpress',
			'file'       => 'wordpress',
		] );

		$this->assertTrue( $payload[ 'success' ] ?? false );
		$this->assertFalse( $payload[ 'page_reload' ] ?? true );
		$this->assertTrue( $payload[ 'table_reload' ] ?? false );
		$this->assertIsString( $payload[ 'message' ] ?? null );
		$this->assertNotSame( '', \trim( (string)( $payload[ 'message' ] ?? '' ) ) );

		$afterActive = $this->retrieveWordpressRows( ( new ScanResultsDisplayOptions() )->activeOnly() );
		$this->assertSame( 0, (int)( $afterActive[ 'datatable_data' ][ 'recordsTotal' ] ?? -1 ) );

		$afterIgnored = $this->retrieveWordpressRows( ( new ScanResultsDisplayOptions() )->ignoredOnly() );
		$this->assertSame( 1, (int)( $afterIgnored[ 'datatable_data' ][ 'recordsTotal' ] ?? -1 ) );
		$this->assertSame(
			[ (int)$ignored[ 'result_item_id' ] ],
			\array_column( $afterIgnored[ 'datatable_data' ][ 'data' ] ?? [], 'rid' )
		);
	}

	public function test_active_wordpress_results_do_not_prepare_stale_rows_outside_the_loaded_page() :void {
		$scanId = TestDataFactory::insertCompletedScan( 'afs' );
		$stale = TestDataFactory::insertAfsFileScanResultTracked( $scanId, $this->corePathFragment( 'wp-admin/update.php' ), [
			'is_in_core'      => 1,
			'is_checksumfail' => 1,
		] );

		for ( $i = 0; $i < 10; $i++ ) {
			TestDataFactory::insertAfsFileScanResult( $scanId, $this->corePathFragment( 'wp-admin/admin.php' ), [
				'is_in_core' => 1,
			] );
		}

		$payload = $this->retrieveWordpressRows(
			( new ScanResultsDisplayOptions() )->activeOnly(),
			$this->tableDataFixture( 10, 10 )
		);

		$this->assertTrue( $payload[ 'success' ] ?? false );
		$this->assertSame( 11, (int)( $payload[ 'datatable_data' ][ 'recordsTotal' ] ?? -1 ) );
		$this->assertSame( 11, (int)( $payload[ 'datatable_data' ][ 'recordsFiltered' ] ?? -1 ) );
		$this->assertCount( 1, $payload[ 'datatable_data' ][ 'data' ] ?? [] );

		$item = self::con()->db_con->scan_result_items->getQuerySelector()->byId( (int)$stale[ 'result_item_id' ] );
		$this->assertNotEmpty( $item );
		$this->assertSame( 0, (int)( $item->resolved_at ?? 0 ) );
	}

	public function test_retrieve_table_data_normalizes_explicit_results_display_options() :void {
		$active = $this->seedWordpressScanResult();
		$ignored = $this->seedWordpressScanResult( 'wp-admin/update.php' );
		TestDataFactory::markScanResultItemIgnored( (int)$ignored[ 'result_item_id' ] );

		$payload = $this->processScanResultsAction( [
			'sub_action'              => 'retrieve_table_data',
			'table_data'              => $this->tableDataFixture(),
			'type'                    => 'core',
			'file'                    => 'core',
			'display_context'         => ScanResultsDisplayOptions::DISPLAY_CONTEXT,
			'scan_results_notice_context' => ActionsQueueScanResultScopeStateBuilder::NOTICE_CONTEXT_ACTIONS_QUEUE,
			'results_display_options' => [
				'include_ignored'  => '1',
				'include_repaired' => 'false',
				'include_deleted'  => '0',
				'ignored_only'     => 1,
				'unexpected'       => 'discard-me',
			],
		] );

		$this->assertTrue( $payload[ 'success' ] ?? false );
		$this->assertSame( 1, (int)( $payload[ 'datatable_data' ][ 'recordsTotal' ] ?? -1 ) );
		$this->assertSame( 1, (int)( $payload[ 'datatable_data' ][ 'recordsFiltered' ] ?? -1 ) );
		$this->assertSame(
			[ (int)$ignored[ 'result_item_id' ] ],
			\array_column( $payload[ 'datatable_data' ][ 'data' ] ?? [], 'rid' )
		);
		$this->assertArrayHasKey( 'display_notice', $payload[ 'datatable_data' ] );
		$this->assertSame(
			ActionsQueueScanResultScopeStateBuilder::MODE_SHOWING_IGNORED,
			(string)$payload[ 'datatable_data' ][ 'display_notice' ][ 'mode' ]
		);
		$this->assertSame(
			1,
			(int)$payload[ 'datatable_data' ][ 'display_notice' ][ 'ignored_count' ]
		);
		$this->assertNotContains(
			(int)$active[ 'result_item_id' ],
			\array_column( $payload[ 'datatable_data' ][ 'data' ] ?? [], 'rid' )
		);
	}

	public function test_retrieve_table_data_exposes_ignored_row_state() :void {
		$ignored = $this->seedWordpressScanResult();
		TestDataFactory::markScanResultItemIgnored( (int)$ignored[ 'result_item_id' ] );

		$payload = $this->retrieveWordpressRows( ( new ScanResultsDisplayOptions() )->ignoredOnly() );
		$this->assertCount( 1, $payload[ 'datatable_data' ][ 'data' ] );
		$row = $payload[ 'datatable_data' ][ 'data' ][ 0 ];

		$this->assertIsArray( $row );
		$this->assertTrue( (bool)( $row[ 'is_ignored' ] ?? false ) );
		$this->assertSame(
			[ 'data-scan-result-ignored' => '1' ],
			(array)( $row[ 'DT_RowAttr' ] ?? [] )
		);
	}

	public function test_retrieve_table_data_uses_single_relative_file_identity_for_wordpress_rows() :void {
		$scanId = TestDataFactory::insertCompletedScan( 'afs' );
		$pathFragment = $this->corePathFragment( 'wp-admin/admin.php' );
		$pathFull = \wp_normalize_path( ABSPATH.$pathFragment );
		$tracked = TestDataFactory::insertAfsFileScanResultTracked( $scanId, $pathFull, [
			'is_in_core'    => 1,
			'path_full'     => $pathFull,
			'path_fragment' => $pathFull,
			'file_path'     => $pathFull,
		] );

		$payload = $this->retrieveWordpressRows( ( new ScanResultsDisplayOptions() )->activeOnly() );
		$this->assertCount( 1, $payload[ 'datatable_data' ][ 'data' ] );
		$row = $payload[ 'datatable_data' ][ 'data' ][ 0 ];

		$this->assertSame( (int)$tracked[ 'result_item_id' ], (int)$row[ 'rid' ] );
		$this->assertScanResultRowUsesSingleRelativePathContract( $row, $pathFragment );
	}

	public function test_retrieve_table_data_uses_single_relative_file_identity_for_malware_rows() :void {
		$pathFull = '';
		try {
			$this->enablePremiumCapabilities( [
				'scan_malware_local',
			] );
			$this->requireController()->opts
				 ->optSet( 'file_scan_areas', [ 'wp', 'malware_php' ] )
				 ->store();
			$this->resetScanResultCountMemoization();

			$pathFull = \wp_normalize_path( ABSPATH.'wp-content/uploads/path-contract-malware.php' );
			$this->ensureFixtureFileExists( $pathFull );
			$pathFragment = TestDataFactory::afsFileItemIdFromPath( $pathFull );
			$scanId = TestDataFactory::insertCompletedScan( 'afs' );
			$tracked = TestDataFactory::insertAfsFileScanResultTracked( $scanId, $pathFull, [
				'is_mal'        => 1,
				'path_full'     => $pathFull,
				'path_fragment' => $pathFull,
				'file_path'     => $pathFull,
			] );

			$payload = $this->retrieveMalwareRows( ( new ScanResultsDisplayOptions() )->activeOnly() );
			$this->assertCount( 1, $payload[ 'datatable_data' ][ 'data' ] );
			$row = $payload[ 'datatable_data' ][ 'data' ][ 0 ];

			$this->assertSame( (int)$tracked[ 'result_item_id' ], (int)$row[ 'rid' ] );
			$this->assertScanResultRowUsesSingleRelativePathContract( $row, $pathFragment );
		}
		finally {
			if ( $pathFull !== '' && \is_file( $pathFull ) ) {
				@\unlink( $pathFull );
			}
		}
	}

	public function test_core_row_actions_keep_independent_delete_and_repair_action_ids() :void {
		$scanId = TestDataFactory::insertCompletedScan( 'afs' );
		$tracked = TestDataFactory::insertAfsFileScanResultTracked( $scanId, $this->corePathFragment( 'wp-admin/admin.php' ), [
			'is_in_core' => 1,
			'is_missing' => 1,
			'is_mal'     => 1,
		] );

		$payload = $this->retrieveWordpressRows( ( new ScanResultsDisplayOptions() )->activeOnly() );
		$this->assertCount( 1, $payload[ 'datatable_data' ][ 'data' ] );
		$row = $payload[ 'datatable_data' ][ 'data' ][ 0 ];

		$this->assertSame( (int)$tracked[ 'result_item_id' ], (int)$row[ 'rid' ] );
		$this->assertSame( [ 'view', 'delete', 'repair', 'ignore' ], $row[ 'action_ids' ] );
	}

	public function test_plugin_row_actions_use_plugin_route_scope_and_delete_id_without_repair() :void {
		$pluginFile = self::con()->base_file;
		$this->assertFileExists( WP_PLUGIN_DIR.'/'.$pluginFile );
		$scanId = TestDataFactory::insertCompletedScan( 'afs' );
		$tracked = TestDataFactory::insertAfsFileScanResultTracked( $scanId, $this->pluginPathFragment( $pluginFile ), [
			'is_unrecognised' => 1,
			'ptg_slug'        => $pluginFile,
		] );

		$payload = $this->retrieveRows( 'plugin', $pluginFile, ( new ScanResultsDisplayOptions() )->activeOnly() );
		$this->assertCount( 1, $payload[ 'datatable_data' ][ 'data' ] );
		$row = $payload[ 'datatable_data' ][ 'data' ][ 0 ];

		$this->assertSame( (int)$tracked[ 'result_item_id' ], (int)$row[ 'rid' ] );
		$this->assertSame( [ 'view', 'delete', 'ignore' ], $row[ 'action_ids' ] );
	}

	public function test_plugin_scope_table_ignores_asset_scoped_non_file_rows() :void {
		$pluginFile = self::con()->base_file;
		$scanId = TestDataFactory::insertCompletedScan( 'afs' );
		$tracked = TestDataFactory::insertAfsFileScanResultTracked( $scanId, $this->pluginPathFragment( $pluginFile ), [
			'is_checksumfail' => 1,
			'is_in_plugin'    => 1,
			'ptg_slug'        => $pluginFile,
		] );
		self::con()->db_con->scan_result_items->getQueryUpdater()->updateById( $tracked[ 'result_item_id' ], [
			'item_type' => ResultItemsHandler::ITEM_TYPE_PLUGIN,
		] );

		$payload = $this->retrieveRows( 'plugin', $pluginFile, ( new ScanResultsDisplayOptions() )->activeOnly() );

		$this->assertTrue( $payload[ 'success' ] );
		$this->assertSame( 0, $payload[ 'datatable_data' ][ 'recordsTotal' ] );
		$this->assertSame( 0, $payload[ 'datatable_data' ][ 'recordsFiltered' ] );
		$this->assertSame( [], $payload[ 'datatable_data' ][ 'data' ] );
	}

	public function test_plugin_scope_table_does_not_fatal_when_blank_path_rows_are_cleaned() :void {
		$pluginFile = self::con()->base_file;
		$scanId = TestDataFactory::insertCompletedScan( 'afs' );
		$tracked = TestDataFactory::insertScanResultItemTracked( $scanId, [
			'item_id'         => '',
			'is_checksumfail' => 1,
			'is_in_plugin'    => 1,
			'ptg_slug'        => $pluginFile,
		] );

		$payload = $this->retrieveRows( 'plugin', $pluginFile, ( new ScanResultsDisplayOptions() )->activeOnly() );

		$this->assertTrue( $payload[ 'success' ] );
		$this->assertTrue( $payload[ 'datatable_data' ][ 'scan_results_changed' ] );
		$this->assertSame( 0, $payload[ 'datatable_data' ][ 'recordsTotal' ] );
		$this->assertSame( 0, $payload[ 'datatable_data' ][ 'recordsFiltered' ] );
		$this->assertSame( [], $payload[ 'datatable_data' ][ 'data' ] );

		$item = self::con()->db_con->scan_result_items->getQuerySelector()->byId( $tracked[ 'result_item_id' ] );
		$this->assertGreaterThan( 0, $item->resolved_at );
		$this->assertSame( 'asset_replaced', $item->resolution_reason );
	}

	public function test_theme_row_actions_use_theme_route_scope_and_delete_id_without_repair() :void {
		$stylesheet = \wp_get_theme()->get_stylesheet();
		$this->ensureFixtureFileExists( \get_theme_root().'/'.$stylesheet.'/style.css' );
		$scanId = TestDataFactory::insertCompletedScan( 'afs' );
		$tracked = TestDataFactory::insertAfsFileScanResultTracked( $scanId, $this->themePathFragment( $stylesheet ), [
			'is_unrecognised' => 1,
			'ptg_slug'        => $stylesheet,
		] );

		$payload = $this->retrieveRows( 'theme', $stylesheet, ( new ScanResultsDisplayOptions() )->activeOnly() );
		$this->assertCount( 1, $payload[ 'datatable_data' ][ 'data' ] );
		$row = $payload[ 'datatable_data' ][ 'data' ][ 0 ];

		$this->assertSame( (int)$tracked[ 'result_item_id' ], (int)$row[ 'rid' ] );
		$this->assertSame( [ 'view', 'delete', 'ignore' ], $row[ 'action_ids' ] );
	}

	public function test_delete_sub_action_deletes_fixture_owned_malware_file_and_marks_row_deleted() :void {
		try {
			$tracked = $this->seedDeletableMalwareResult( 'delete-action.php' );
			$resultItemId = (int)$tracked[ 'result_item_id' ];
			$path = (string)$tracked[ 'path_full' ];

			$beforeActive = $this->retrieveMalwareRows( ( new ScanResultsDisplayOptions() )->activeOnly() );
			$this->assertSame( [ $resultItemId ], \array_column( $beforeActive[ 'datatable_data' ][ 'data' ] ?? [], 'rid' ) );

			$payload = $this->processScanResultsAction( [
				'sub_action' => 'delete',
				'rids'       => [ $resultItemId ],
			] );

			$this->assertTrue( $payload[ 'success' ] ?? false );
			$this->assertFalse( $payload[ 'page_reload' ] ?? true );
			$this->assertTrue( $payload[ 'table_reload' ] ?? false );
			$this->assertFalse( \is_file( $path ) );
			$this->assertResultItemDeleted( $resultItemId );

			$afterActive = $this->retrieveMalwareRows( ( new ScanResultsDisplayOptions() )->activeOnly() );
			$this->assertSame( 0, (int)( $afterActive[ 'datatable_data' ][ 'recordsTotal' ] ?? -1 ) );

			$afterDeleted = $this->retrieveMalwareRows( [
				'include_ignored'  => false,
				'include_repaired' => false,
				'include_deleted'  => true,
				'ignored_only'     => false,
			] );
			$this->assertSame( [ $resultItemId ], \array_column( $afterDeleted[ 'datatable_data' ][ 'data' ] ?? [], 'rid' ) );
		}
		finally {
			$this->deleteScanActionFixtureRoot();
		}
	}

	public function test_repair_delete_sub_action_deletes_non_repairable_fixture_file_and_marks_row_deleted() :void {
		try {
			$tracked = $this->seedDeletableMalwareResult( 'repair-delete-action.php' );
			$resultItemId = (int)$tracked[ 'result_item_id' ];
			$path = (string)$tracked[ 'path_full' ];

			$payload = $this->processScanResultsAction( [
				'sub_action' => 'repair-delete',
				'rids'       => [ $resultItemId ],
			] );

			$this->assertTrue( $payload[ 'success' ] ?? false );
			$this->assertFalse( $payload[ 'page_reload' ] ?? true );
			$this->assertTrue( $payload[ 'table_reload' ] ?? false );
			$this->assertFalse( \is_file( $path ) );
			$this->assertResultItemDeleted( $resultItemId );

			$afterActive = $this->retrieveMalwareRows( ( new ScanResultsDisplayOptions() )->activeOnly() );
			$this->assertSame( 0, (int)( $afterActive[ 'datatable_data' ][ 'recordsTotal' ] ?? -1 ) );

			$afterDeleted = $this->retrieveMalwareRows( [
				'include_ignored'  => false,
				'include_repaired' => false,
				'include_deleted'  => true,
				'ignored_only'     => false,
			] );
			$this->assertSame( [ $resultItemId ], \array_column( $afterDeleted[ 'datatable_data' ][ 'data' ] ?? [], 'rid' ) );
		}
		finally {
			$this->deleteScanActionFixtureRoot();
		}
	}

	public function test_fixture_cleanup_removes_owned_file_and_root() :void {
		$path = $this->createOwnedScanActionFixtureFile( 'cleanup-contract.php' );
		$root = $this->scanActionFixtureRoot();
		$this->assertFileExists( $path );
		$this->assertDirectoryExists( $root );

		$this->deleteScanActionFixtureRoot();

		$this->assertFileDoesNotExist( $path );
		$this->assertDirectoryDoesNotExist( $root );
	}

	public function test_fixture_cleanup_rejects_linked_root_without_touching_target() :void {
		if ( !\function_exists( 'symlink' ) ) {
			$this->markTestSkipped( 'symlink() is unavailable.' );
		}

		$this->deleteScanActionFixtureRoot();
		$root = $this->scanActionFixtureRoot();
		$resolvedAbsRoot = \realpath( ABSPATH );
		$this->assertNotFalse( $resolvedAbsRoot );
		$resolvedAbsRoot = \wp_normalize_path( (string)$resolvedAbsRoot );
		$target = \wp_normalize_path( \trailingslashit( $resolvedAbsRoot ).'shield-scan-action-fixture-target-'.\uniqid() );
		$sentinel = $target.'/sentinel.txt';

		try {
			$this->assertTrue( \wp_mkdir_p( $target ) );
			$this->assertNotFalse( \file_put_contents( $sentinel, "preserve me\n" ) );

			$warning = '';
			\set_error_handler( static function ( int $level, string $message ) use ( &$warning ) :bool {
				unset( $level );
				$warning = $message;
				return true;
			} );
			try {
				$linkCreated = \symlink( $target, $root );
			}
			finally {
				\restore_error_handler();
			}

			if ( !$linkCreated ) {
				$this->markTestSkipped( $warning === ''
					? 'symlink() returned false without a warning.'
					: 'symlink() failed: '.$warning );
			}

			\clearstatcache( true, $root );
			$this->assertTrue( \is_link( $root ) );
			$this->assertFixturePathIsOwned( $root );
			$resolvedTarget = \realpath( $target );
			$resolvedLinkedRoot = \realpath( $root );
			$this->assertNotFalse( $resolvedTarget );
			$this->assertNotFalse( $resolvedLinkedRoot );
			$resolvedTarget = \wp_normalize_path( (string)$resolvedTarget );
			$resolvedLinkedRoot = \wp_normalize_path( (string)$resolvedLinkedRoot );
			$expectedRoot = \wp_normalize_path( \trailingslashit( $resolvedAbsRoot ).'shield-scan-action-fixture' );
			$this->assertSame( $resolvedTarget, $resolvedLinkedRoot );
			$this->assertNotSame( $expectedRoot, $resolvedLinkedRoot );

			$caught = null;
			try {
				$this->deleteScanActionFixtureRoot();
			}
			catch ( ExpectationFailedException $e ) {
				$caught = $e;
			}

			$this->assertInstanceOf( ExpectationFailedException::class, $caught );
			\clearstatcache( true, $root );
			$this->assertTrue( \is_link( $root ) );
			$this->assertSame( "preserve me\n", \file_get_contents( $sentinel ) );
		}
		finally {
			\clearstatcache( true, $root );
			if ( \is_link( $root ) ) {
				\unlink( $root );
			}
			if ( \is_file( $sentinel ) ) {
				\unlink( $sentinel );
			}
			if ( \is_dir( $target ) ) {
				\rmdir( $target );
			}
		}
	}

	/**
	 * @param array<string,mixed> $params
	 * @return array<string,mixed>
	 */
	private function processScanResultsAction( array $params ) :array {
		$snapshot = $this->seedActionNonceContext( ScanResultsTableAction::class );

		try {
			return ( new PluginAdminRouteRuntime() )
				->processActionPayloadWithAdminBypass( ScanResultsTableAction::SLUG, $params );
		}
		finally {
			$this->restoreActionNonceContext( $snapshot );
		}
	}

	/**
	 * @return array<string,mixed>
	 */
	private function retrieveWordpressRows( array $resultsDisplayOptions, ?array $tableData = null ) :array {
		return $this->retrieveRows( 'core', 'core', $resultsDisplayOptions, $tableData );
	}

	/**
	 * @return array<string,mixed>
	 */
	private function retrieveMalwareRows( array $resultsDisplayOptions, ?array $tableData = null ) :array {
		return $this->retrieveRows( 'malware', 'malware', $resultsDisplayOptions, $tableData );
	}

	/**
	 * @return array<string,mixed>
	 */
	private function retrieveRows( string $type, string $file, array $resultsDisplayOptions, ?array $tableData = null ) :array {
		return $this->processScanResultsAction( [
			'sub_action' => 'retrieve_table_data',
			'table_data' => $tableData ?? $this->tableDataFixture(),
			'type'       => $type,
			'file'       => $file,
			...(
				new ScanResultsDisplayOptions()
			)->buildExplicitActionData( $resultsDisplayOptions ),
		] );
	}

	private function tableDataFixture( int $start = 0, int $length = 10 ) :array {
		return [
			'search'  => [ 'value' => '' ],
			'start'   => $start,
			'length'  => $length,
			'order'   => [],
			'columns' => [],
		];
	}

	private function corePathFragment( string $relativePath ) :string {
		return TestDataFactory::afsFileItemIdFromPath( ABSPATH.\ltrim( $relativePath, '/\\' ) );
	}

	private function pluginPathFragment( string $pluginFile ) :string {
		return TestDataFactory::afsFileItemIdFromPath( WP_PLUGIN_DIR.'/'.$pluginFile );
	}

	private function themePathFragment( string $stylesheet ) :string {
		return TestDataFactory::afsFileItemIdFromPath( \get_theme_root().'/'.$stylesheet.'/style.css' );
	}

	private function ensureFixtureFileExists( string $path ) :void {
		$path = \wp_normalize_path( $path );
		if ( \is_file( $path ) ) {
			return;
		}

		$dir = \dirname( $path );
		if ( !\is_dir( $dir ) ) {
			$this->assertTrue( \wp_mkdir_p( Path::canonicalize( $dir ) ), 'Could not create scan-action fixture directory.' );
		}
		$this->assertNotFalse( \file_put_contents( $path, "fixture\n" ) );
		$this->assertFileExists( $path );
	}

	/**
	 * @return array{result_item_id:int,path_full:string}
	 */
	private function seedDeletableMalwareResult( string $fileName ) :array {
		$this->deleteScanActionFixtureRoot();

		$path = $this->createOwnedScanActionFixtureFile( $fileName );
		$scanId = TestDataFactory::insertCompletedScan( 'afs' );
		$tracked = $this->insertTrackedMalwareResultForPath( $scanId, $path );

		return [
			'result_item_id' => (int)$tracked[ 'result_item_id' ],
			'path_full'      => $path,
		];
	}

	private function seedExistingMalwareResultForScan( int $scanId, string $fileName ) :array {
		return $this->insertTrackedMalwareResultForPath(
			$scanId,
			$this->createOwnedScanActionFixtureFile( $fileName )
		);
	}

	private function createOwnedScanActionFixtureFile( string $fileName ) :string {
		$path = $this->scanActionFixturePath( $fileName );
		$this->ensureFixtureFileExists( $path );
		$this->assertFixturePathIsOwned( $path );
		return $path;
	}

	private function insertTrackedMalwareResultForPath( int $scanId, string $path ) :array {
		return TestDataFactory::insertAfsFileScanResultTracked(
			$scanId,
			$path,
			[
				'is_mal'          => 1,
				'is_unrecognised' => 1,
			]
		);
	}

	private function assertResultItemDeleted( int $resultItemId ) :void {
		$item = self::con()->db_con->scan_result_items->getQuerySelector()->byId( $resultItemId );
		$this->assertNotEmpty( $item );
		$this->assertGreaterThan( 0, (int)( $item->resolved_at ?? 0 ) );
		$this->assertSame( 'deleted', (string)( $item->resolution_reason ?? '' ) );
	}

	private function scanActionFixturePath( string $fileName ) :string {
		$fileName = \ltrim( $fileName, '/\\' );
		$this->assertNotSame( '', $fileName );
		$this->assertDoesNotMatchRegularExpression( '#[/\\\\]#', $fileName );

		$path = \wp_normalize_path( $this->scanActionFixtureRoot().'/'.$fileName );
		$this->assertFixturePathIsOwned( $path );
		return $path;
	}

	private function scanActionFixtureRoot() :string {
		return \wp_normalize_path( \trailingslashit( ABSPATH ).'shield-scan-action-fixture' );
	}

	private function deleteScanActionFixtureRoot() :void {
		$root = $this->scanActionFixtureRoot();
		\clearstatcache( true, $root );
		if ( !\file_exists( $root ) && !\is_link( $root ) ) {
			return;
		}

		$this->assertFixturePathIsOwned( $root );
		$resolvedAbsRoot = \realpath( ABSPATH );
		$resolvedFixtureRoot = \realpath( $root );
		$this->assertNotFalse( $resolvedAbsRoot );
		$this->assertNotFalse( $resolvedFixtureRoot );
		$resolvedAbsRoot = \wp_normalize_path( (string)$resolvedAbsRoot );
		$resolvedFixtureRoot = \wp_normalize_path( (string)$resolvedFixtureRoot );
		$expectedFixtureRoot = \wp_normalize_path(
			\trailingslashit( $resolvedAbsRoot ).'shield-scan-action-fixture'
		);
		$this->assertSame( $expectedFixtureRoot, $resolvedFixtureRoot );
		$this->assertDirectoryExists( $resolvedFixtureRoot );

		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $resolvedFixtureRoot, \FilesystemIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ( $iterator as $item ) {
			$item->isDir() ? @\rmdir( $item->getPathname() ) : @\unlink( $item->getPathname() );
		}
		@\rmdir( $resolvedFixtureRoot );
	}

	private function assertFixturePathIsOwned( string $path ) :void {
		$root = \trailingslashit( $this->scanActionFixtureRoot() );
		$normalized = \trailingslashit( \wp_normalize_path( $path ) );
		$this->assertSame( $root, \substr( $normalized, 0, \strlen( $root ) ) );
	}

	private function assertScanResultRowUsesSingleRelativePathContract( array $row, string $expectedFragment ) :void {
		$this->assertArrayHasKey( 'file', $row );
		$this->assertSame( $expectedFragment, (string)$row[ 'file' ] );
		$this->assertArrayNotHasKey( 'path_full', $row );
		$this->assertArrayNotHasKey( 'path_fragment', $row );
		$this->assertArrayNotHasKey( 'file_path', $row );

		$encodedRow = \wp_json_encode( $row );
		$this->assertIsString( $encodedRow );
		$this->assertStringNotContainsString(
			\wp_normalize_path( ABSPATH ),
			\wp_normalize_path( $encodedRow )
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	private function seedWordpressScanResult( string $relativePath = 'wp-admin/admin.php' ) :array {
		$scanId = TestDataFactory::insertCompletedScan( 'afs' );
		return $this->seedWordpressScanResultForScan( $scanId, $relativePath );
	}

	/**
	 * @return array<string,mixed>
	 */
	private function seedWordpressScanResultForScan( int $scanId, string $relativePath = 'wp-admin/admin.php' ) :array {
		return TestDataFactory::insertAfsFileScanResultTracked( $scanId, $this->corePathFragment( $relativePath ), [
			'is_in_core' => 1,
		] );
	}
}
