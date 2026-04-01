<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionProcessor,
	Actions\ScanResultsTableAction
};
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\ActionsQueueScanResultsOptions;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TestDataFactory;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class ScanResultsTableActionIntegrationTest extends ShieldIntegrationTestCase {

	public function set_up() {
		parent::set_up();
		$this->requireDb( 'scans' );
		$this->requireDb( 'scan_results' );
		$this->requireDb( 'scan_result_items' );
		$this->requireDb( 'scan_result_item_meta' );
		$this->loginAsSecurityAdmin();
		$this->requireController()->this_req->wp_is_ajax = false;
		$this->enablePremiumCapabilities( [
			'scan_file_areas',
			'scan_pluginsthemes_local',
		] );
		$this->requireController()->opts
			 ->optSet( 'enable_core_file_integrity_scan', 'Y' )
			 ->optSet( 'file_scan_areas', [ 'plugins' ] )
			 ->store();
		self::con()->cache_dir_handler->buildSubDir( 'integration-fixture' );
		$this->resetScanResultCountMemoization();
	}

	public function test_ignore_sub_action_removes_plugin_row_from_active_results_without_page_reload() :void {
		$pluginSlug = self::con()->base_file;
		$tracked = $this->seedPluginScanResult( $pluginSlug );
		$scanResultId = (int)( $tracked[ 'scan_result_id' ] ?? 0 );
		$resultItemId = (int)( $tracked[ 'result_item_id' ] ?? 0 );
		$this->assertGreaterThan( 0, $scanResultId );
		$this->assertGreaterThan( 0, $resultItemId );

		$beforeActive = $this->retrievePluginRows( $pluginSlug, ( new ActionsQueueScanResultsOptions() )->activeOnly() );
		$this->assertTrue( $beforeActive[ 'success' ] ?? false );
		$this->assertSame( 1, (int)( $beforeActive[ 'datatable_data' ][ 'recordsTotal' ] ?? 0 ) );
		$this->assertSame( 1, (int)( $beforeActive[ 'datatable_data' ][ 'recordsFiltered' ] ?? 0 ) );
		$this->assertSame(
			[ $scanResultId ],
			\array_column( $beforeActive[ 'datatable_data' ][ 'data' ] ?? [], 'rid' )
		);

		$payload = $this->processor()->processAction( ScanResultsTableAction::SLUG, [
			'sub_action' => 'ignore',
			'rids'       => [ $scanResultId ],
		] )->payload();

		$this->assertTrue( $payload[ 'success' ] ?? false );
		$this->assertFalse( $payload[ 'page_reload' ] ?? true );
		$this->assertTrue( $payload[ 'table_reload' ] ?? false );
		$this->assertIsString( $payload[ 'message' ] ?? null );
		$this->assertNotSame( '', (string)( $payload[ 'message' ] ?? '' ) );

		$item = self::con()->db_con->scan_result_items->getQuerySelector()->byId( $resultItemId );
		$this->assertNotEmpty( $item );
		$this->assertGreaterThan( 0, (int)( $item->ignored_at ?? 0 ) );

		$afterActive = $this->retrievePluginRows( $pluginSlug, ( new ActionsQueueScanResultsOptions() )->activeOnly() );
		$this->assertTrue( $afterActive[ 'success' ] ?? false );
		$this->assertSame( 0, (int)( $afterActive[ 'datatable_data' ][ 'recordsTotal' ] ?? -1 ) );
		$this->assertSame( 0, (int)( $afterActive[ 'datatable_data' ][ 'recordsFiltered' ] ?? -1 ) );
		$this->assertCount( 0, $afterActive[ 'datatable_data' ][ 'data' ] ?? [] );

		$afterIgnored = $this->retrievePluginRows( $pluginSlug, ( new ActionsQueueScanResultsOptions() )->ignoredOnly() );
		$this->assertTrue( $afterIgnored[ 'success' ] ?? false );
		$this->assertSame( 1, (int)( $afterIgnored[ 'datatable_data' ][ 'recordsTotal' ] ?? 0 ) );
		$this->assertSame( 1, (int)( $afterIgnored[ 'datatable_data' ][ 'recordsFiltered' ] ?? 0 ) );
		$this->assertSame(
			[ $scanResultId ],
			\array_column( $afterIgnored[ 'datatable_data' ][ 'data' ] ?? [], 'rid' )
		);
	}

	public function test_active_plugin_results_prepare_stale_rows_before_later_page_and_count_queries() :void {
		$pluginSlug = self::con()->base_file;
		$scanId = TestDataFactory::insertCompletedScan( 'afs' );
		$stale = TestDataFactory::insertAfsFileScanResultTracked( $scanId, $this->pluginMainPathFragment( $pluginSlug ), [
			'is_in_plugin'   => 1,
			'ptg_slug'       => $pluginSlug,
			'is_checksumfail' => 1,
		] );

		for ( $i = 0; $i < 10; $i++ ) {
			TestDataFactory::insertAfsFileScanResult( $scanId, $this->pluginMainPathFragment( $pluginSlug ), [
				'is_in_plugin' => 1,
				'ptg_slug'     => $pluginSlug,
			] );
		}

		$payload = $this->retrievePluginRows(
			$pluginSlug,
			( new ActionsQueueScanResultsOptions() )->activeOnly(),
			$this->tableDataFixture( 10, 10 )
		);

		$this->assertTrue( $payload[ 'success' ] ?? false );
		$this->assertSame( 10, (int)( $payload[ 'datatable_data' ][ 'recordsTotal' ] ?? -1 ) );
		$this->assertSame( 10, (int)( $payload[ 'datatable_data' ][ 'recordsFiltered' ] ?? -1 ) );
		$this->assertCount( 0, $payload[ 'datatable_data' ][ 'data' ] ?? [] );

		$item = self::con()->db_con->scan_result_items->getQuerySelector()->byId( (int)$stale[ 'result_item_id' ] );
		$this->assertNotEmpty( $item );
		$this->assertGreaterThan( 0, (int)( $item->item_repaired_at ?? 0 ) );
	}

	private function processor() :ActionProcessor {
		return new ActionProcessor();
	}

	/**
	 * @return array<string,mixed>
	 */
	private function retrievePluginRows( string $pluginSlug, array $resultsDisplayOptions, ?array $tableData = null ) :array {
		return $this->processor()->processAction( ScanResultsTableAction::SLUG, [
			'sub_action' => 'retrieve_table_data',
			'table_data' => $tableData ?? $this->tableDataFixture(),
			'type'       => 'plugin',
			'file'       => $pluginSlug,
			...(
				new ActionsQueueScanResultsOptions()
			)->buildActionData( $resultsDisplayOptions ),
		] )->payload();
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

	private function pluginMainPathFragment( string $pluginSlug ) :string {
		return TestDataFactory::pathFragmentFromAbsolutePath( WP_PLUGIN_DIR.'/'.$pluginSlug );
	}

	/**
	 * @return array<string,mixed>
	 */
	private function seedPluginScanResult( string $pluginSlug ) :array {
		$scanId = TestDataFactory::insertCompletedScan( 'afs' );
		return TestDataFactory::insertAfsFileScanResultTracked( $scanId, $this->pluginMainPathFragment( $pluginSlug ), [
			'is_in_plugin' => 1,
			'ptg_slug'     => $pluginSlug,
		] );
	}
}
