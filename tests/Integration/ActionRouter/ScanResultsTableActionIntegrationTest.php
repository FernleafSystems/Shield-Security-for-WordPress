<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	ActionProcessor,
	Actions\ScanResultsTableAction,
	Exceptions\InvalidActionNonceException
};
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\ScanResultsDisplayOptions;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\ActionRouter\PluginAdminRouteRuntime;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TestDataFactory;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter\Support\ActionRequestNonceFixture;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class ScanResultsTableActionIntegrationTest extends ShieldIntegrationTestCase {

	use ActionRequestNonceFixture;

	public function set_up() {
		parent::set_up();
		$this->truncateShieldTables();
		$this->requireDb( 'scans' );
		$this->requireDb( 'scan_results' );
		$this->requireDb( 'scan_result_items' );
		$this->requireDb( 'scan_result_item_meta' );
		$this->loginAsSecurityAdmin();
		$this->requireController()->this_req->wp_is_ajax = false;
		$this->requireController()->opts
			 ->optSet( 'enable_core_file_integrity_scan', 'Y' )
			 ->optSet( 'file_scan_areas', [ 'wp' ] )
			 ->store();
		$this->resetScanResultCountMemoization();
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

	public function test_mutating_sub_action_requires_valid_nonce_before_state_change() :void {
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
				'sub_action' => 'ignore',
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
		$this->ensureFixtureFileExists( ABSPATH.$this->pluginPathFragment( $pluginFile ) );
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
		return TestDataFactory::pathFragmentFromAbsolutePath( ABSPATH.\ltrim( $relativePath, '/\\' ) );
	}

	private function pluginPathFragment( string $pluginFile ) :string {
		return 'wp-content/plugins/'.\ltrim( \wp_normalize_path( $pluginFile ), '/\\' );
	}

	private function themePathFragment( string $stylesheet ) :string {
		return TestDataFactory::pathFragmentFromAbsolutePath( \get_theme_root().'/'.$stylesheet.'/style.css' );
	}

	private function ensureFixtureFileExists( string $path ) :void {
		$path = \wp_normalize_path( $path );
		if ( \is_file( $path ) ) {
			return;
		}

		$dir = \dirname( $path );
		if ( !\is_dir( $dir ) ) {
			\wp_mkdir_p( $dir );
		}
		$this->assertNotFalse( \file_put_contents( $path, "fixture\n" ) );
		$this->assertFileExists( $path );
	}

	/**
	 * @return array{result_item_id:int,path_full:string}
	 */
	private function seedDeletableMalwareResult( string $fileName ) :array {
		$this->deleteScanActionFixtureRoot();

		$path = $this->scanActionFixturePath( $fileName );
		$this->ensureFixtureFileExists( $path );
		$resolvedPath = \realpath( $path );
		$this->assertNotFalse( $resolvedPath );
		$path = \wp_normalize_path( $resolvedPath );
		$this->assertFixturePathIsOwned( $path );

		$scanId = TestDataFactory::insertCompletedScan( 'afs' );
		$tracked = TestDataFactory::insertAfsFileScanResultTracked(
			$scanId,
			TestDataFactory::pathFragmentFromAbsolutePath( $path ),
			[
				'is_mal'          => 1,
				'is_unrecognised' => 1,
			]
		);

		return [
			'result_item_id' => (int)$tracked[ 'result_item_id' ],
			'path_full'      => $path,
		];
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
		return \wp_normalize_path( \trailingslashit( WP_CONTENT_DIR ).'shield-scan-action-fixture' );
	}

	private function deleteScanActionFixtureRoot() :void {
		$root = $this->scanActionFixtureRoot();
		if ( !\file_exists( $root ) ) {
			return;
		}

		$resolvedRoot = \realpath( $root );
		if ( $resolvedRoot === false ) {
			return;
		}
		$resolvedRoot = \wp_normalize_path( $resolvedRoot );
		$this->assertSame( $root, $resolvedRoot );
		$this->assertDirectoryExists( $resolvedRoot );

		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $resolvedRoot, \FilesystemIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ( $iterator as $item ) {
			$item->isDir() ? @\rmdir( $item->getPathname() ) : @\unlink( $item->getPathname() );
		}
		@\rmdir( $resolvedRoot );
	}

	private function assertFixturePathIsOwned( string $path ) :void {
		$root = \trailingslashit( $this->scanActionFixtureRoot() );
		$normalized = \wp_normalize_path( $path );
		$this->assertSame( $root, \substr( $normalized, 0, \strlen( $root ) ) );
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
