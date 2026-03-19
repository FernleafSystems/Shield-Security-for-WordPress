<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionProcessor,
	Actions\Investigation\InvestigationTableContract,
	Actions\InvestigationTableAction
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TestDataFactory;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Services\Services;

class InvestigationTableActionIntegrationTest extends ShieldIntegrationTestCase {

	public function set_up() {
		parent::set_up();
		$this->requireDb( 'ips' );
		$this->requireDb( 'req_logs' );
		$this->requireDb( 'activity_logs' );
		$this->requireDb( 'activity_logs_meta' );
		$this->requireDb( 'scans' );
		$this->requireDb( 'scan_results' );
		$this->requireDb( 'scan_result_items' );
		$this->requireDb( 'scan_result_item_meta' );
		$this->loginAsSecurityAdmin();
		$this->requireController()->this_req->wp_is_ajax = false;
	}

	private function processor() :ActionProcessor {
		return new ActionProcessor();
	}

	private function tableDataFixture() :array {
		return [
			'search'  => [ 'value' => '' ],
			'start'   => 0,
			'length'  => 10,
			'order'   => [],
			'columns' => [],
		];
	}

	public function testValidSessionsPayloadReturnsDatatableEnvelope() {
		$userId = \get_current_user_id();
		$this->assertGreaterThan( 0, $userId );

		$payload = $this->processor()->processAction( InvestigationTableAction::SLUG, [
			InvestigationTableContract::REQ_KEY_SUB_ACTION   => InvestigationTableContract::SUB_ACTION_RETRIEVE_TABLE_DATA,
			InvestigationTableContract::REQ_KEY_TABLE_TYPE   => InvestigationTableContract::TABLE_TYPE_SESSIONS,
			InvestigationTableContract::REQ_KEY_SUBJECT_TYPE => InvestigationTableContract::SUBJECT_TYPE_USER,
			InvestigationTableContract::REQ_KEY_SUBJECT_ID   => $userId,
			InvestigationTableContract::REQ_KEY_TABLE_DATA   => $this->tableDataFixture(),
		] )->payload();

		$this->assertTrue( $payload[ 'success' ] ?? false );
		$this->assertArrayHasKey( 'datatable_data', $payload );
		$this->assertArrayHasKey( 'data', $payload[ 'datatable_data' ] );
		$this->assertArrayHasKey( 'recordsTotal', $payload[ 'datatable_data' ] );
		$this->assertArrayHasKey( 'recordsFiltered', $payload[ 'datatable_data' ] );
		$this->assertArrayHasKey( 'searchPanes', $payload[ 'datatable_data' ] );
	}

	public function testUnsupportedTableTypeReturnsFailurePayload() {
		$payload = $this->processor()->processAction( InvestigationTableAction::SLUG, [
			InvestigationTableContract::REQ_KEY_SUB_ACTION   => InvestigationTableContract::SUB_ACTION_RETRIEVE_TABLE_DATA,
			InvestigationTableContract::REQ_KEY_TABLE_TYPE   => 'unknown',
			InvestigationTableContract::REQ_KEY_SUBJECT_TYPE => InvestigationTableContract::SUBJECT_TYPE_USER,
			InvestigationTableContract::REQ_KEY_SUBJECT_ID   => 1,
			InvestigationTableContract::REQ_KEY_TABLE_DATA   => $this->tableDataFixture(),
		] )->payload();

		$this->assertFalse( $payload[ 'success' ] ?? true );
		$this->assertTrue( $payload[ 'page_reload' ] ?? false );
		$this->assertSame( 'unsupported_table_type', $payload[ 'error_code' ] ?? '' );
	}

	public function testUnsupportedSubjectTypeForTableReturnsFailurePayload() {
		$payload = $this->processor()->processAction( InvestigationTableAction::SLUG, [
			InvestigationTableContract::REQ_KEY_SUB_ACTION   => InvestigationTableContract::SUB_ACTION_RETRIEVE_TABLE_DATA,
			InvestigationTableContract::REQ_KEY_TABLE_TYPE   => InvestigationTableContract::TABLE_TYPE_SESSIONS,
			InvestigationTableContract::REQ_KEY_SUBJECT_TYPE => InvestigationTableContract::SUBJECT_TYPE_IP,
			InvestigationTableContract::REQ_KEY_SUBJECT_ID   => '1.2.3.4',
			InvestigationTableContract::REQ_KEY_TABLE_DATA   => $this->tableDataFixture(),
		] )->payload();

		$this->assertFalse( $payload[ 'success' ] ?? true );
		$this->assertTrue( $payload[ 'page_reload' ] ?? false );
		$this->assertSame( 'unsupported_subject_type', $payload[ 'error_code' ] ?? '' );
	}

	public function testMissingRequiredKeysReturnsFailurePayloadWithErrorCode() {
		$payload = $this->processor()->processAction( InvestigationTableAction::SLUG, [
			InvestigationTableContract::REQ_KEY_SUB_ACTION => InvestigationTableContract::SUB_ACTION_RETRIEVE_TABLE_DATA,
		] )->payload();

		$this->assertFalse( $payload[ 'success' ] ?? true );
		$this->assertTrue( $payload[ 'page_reload' ] ?? false );
		$this->assertSame( 'missing_required_action_data', $payload[ 'error_code' ] ?? '' );
	}

	public function testValidActivityPluginPayloadReturnsDatatableEnvelope() :void {
		$payload = $this->processor()->processAction( InvestigationTableAction::SLUG, [
			InvestigationTableContract::REQ_KEY_SUB_ACTION   => InvestigationTableContract::SUB_ACTION_RETRIEVE_TABLE_DATA,
			InvestigationTableContract::REQ_KEY_TABLE_TYPE   => InvestigationTableContract::TABLE_TYPE_ACTIVITY,
			InvestigationTableContract::REQ_KEY_SUBJECT_TYPE => InvestigationTableContract::SUBJECT_TYPE_PLUGIN,
			InvestigationTableContract::REQ_KEY_SUBJECT_ID   => $this->firstInstalledPluginSlug(),
			InvestigationTableContract::REQ_KEY_TABLE_DATA   => $this->tableDataFixture(),
		] )->payload();

		$this->assertTrue( $payload[ 'success' ] ?? false );
		$this->assertArrayHasKey( 'datatable_data', $payload );
		$this->assertArrayHasKey( 'data', $payload[ 'datatable_data' ] );
	}

	public function testValidActivityThemePayloadReturnsDatatableEnvelope() :void {
		$payload = $this->processor()->processAction( InvestigationTableAction::SLUG, [
			InvestigationTableContract::REQ_KEY_SUB_ACTION   => InvestigationTableContract::SUB_ACTION_RETRIEVE_TABLE_DATA,
			InvestigationTableContract::REQ_KEY_TABLE_TYPE   => InvestigationTableContract::TABLE_TYPE_ACTIVITY,
			InvestigationTableContract::REQ_KEY_SUBJECT_TYPE => InvestigationTableContract::SUBJECT_TYPE_THEME,
			InvestigationTableContract::REQ_KEY_SUBJECT_ID   => $this->firstInstalledThemeSlug(),
			InvestigationTableContract::REQ_KEY_TABLE_DATA   => $this->tableDataFixture(),
		] )->payload();

		$this->assertTrue( $payload[ 'success' ] ?? false );
		$this->assertArrayHasKey( 'datatable_data', $payload );
		$this->assertArrayHasKey( 'data', $payload[ 'datatable_data' ] );
	}

	public function testValidActivityCorePayloadReturnsDatatableEnvelope() :void {
		$payload = $this->processor()->processAction( InvestigationTableAction::SLUG, [
			InvestigationTableContract::REQ_KEY_SUB_ACTION   => InvestigationTableContract::SUB_ACTION_RETRIEVE_TABLE_DATA,
			InvestigationTableContract::REQ_KEY_TABLE_TYPE   => InvestigationTableContract::TABLE_TYPE_ACTIVITY,
			InvestigationTableContract::REQ_KEY_SUBJECT_TYPE => InvestigationTableContract::SUBJECT_TYPE_CORE,
			InvestigationTableContract::REQ_KEY_SUBJECT_ID   => InvestigationTableContract::SUBJECT_TYPE_CORE,
			InvestigationTableContract::REQ_KEY_TABLE_DATA   => $this->tableDataFixture(),
		] )->payload();

		$this->assertTrue( $payload[ 'success' ] ?? false );
		$this->assertArrayHasKey( 'datatable_data', $payload );
		$this->assertArrayHasKey( 'data', $payload[ 'datatable_data' ] );
	}

	public function testValidFileScanResultsCorePayloadReturnsOnlyCoreRowsAndScopedCounts() :void {
		$pluginSlug = $this->firstInstalledPluginSlug();
		$themeSlug = $this->firstInstalledThemeSlug();
		$afsId = TestDataFactory::insertCompletedScan( 'afs' );
		TestDataFactory::insertScanResultItem( $afsId, [
			'item_id'    => 'wp-admin/admin.php',
			'is_in_core' => 1,
		] );
		TestDataFactory::insertScanResultItem( $afsId, [
			'item_id'      => 'plugin-file.php',
			'is_in_plugin' => 1,
			'ptg_slug'     => $pluginSlug,
		] );
		TestDataFactory::insertScanResultItem( $afsId, [
			'item_id'     => 'theme-file.php',
			'is_in_theme' => 1,
			'ptg_slug'    => $themeSlug,
		] );

		$datatable = $this->assertSuccessfulDatatablePayload( $this->fetchInvestigationTablePayload(
			InvestigationTableContract::TABLE_TYPE_FILE_SCAN_RESULTS,
			InvestigationTableContract::SUBJECT_TYPE_CORE,
			InvestigationTableContract::SUBJECT_TYPE_CORE
		) );

		$this->assertSame( [ 'wp-admin/admin.php' ], $this->extractFilesFromRows( $datatable[ 'data' ] ?? [] ) );
		$this->assertSame( 1, (int)( $datatable[ 'recordsTotal' ] ?? 0 ) );
		$this->assertSame( 1, (int)( $datatable[ 'recordsFiltered' ] ?? 0 ) );
	}

	public function testValidFileScanResultsPluginPayloadReturnsOnlyPluginRowsAndScopedCounts() :void {
		$pluginSlug = $this->firstInstalledPluginSlug();
		$otherPluginSlug = $this->secondInstalledPluginSlug( $pluginSlug );
		$themeSlug = $this->firstInstalledThemeSlug();
		$afsId = TestDataFactory::insertCompletedScan( 'afs' );
		TestDataFactory::insertScanResultItem( $afsId, [
			'item_id'      => 'plugin-file.php',
			'is_in_plugin' => 1,
			'ptg_slug'     => $pluginSlug,
		] );
		TestDataFactory::insertScanResultItem( $afsId, [
			'item_id'      => 'plugin-file-2.php',
			'is_in_plugin' => 1,
			'ptg_slug'     => $pluginSlug,
		] );
		TestDataFactory::insertScanResultItem( $afsId, [
			'item_id'    => 'wp-admin/admin.php',
			'is_in_core' => 1,
		] );
		TestDataFactory::insertScanResultItem( $afsId, [
			'item_id'     => 'theme-file.php',
			'is_in_theme' => 1,
			'ptg_slug'    => $themeSlug,
		] );
		if ( !empty( $otherPluginSlug ) ) {
			TestDataFactory::insertScanResultItem( $afsId, [
				'item_id'      => 'other-plugin-file.php',
				'is_in_plugin' => 1,
				'ptg_slug'     => $otherPluginSlug,
			] );
		}

		$datatable = $this->assertSuccessfulDatatablePayload( $this->fetchInvestigationTablePayload(
			InvestigationTableContract::TABLE_TYPE_FILE_SCAN_RESULTS,
			InvestigationTableContract::SUBJECT_TYPE_PLUGIN,
			$pluginSlug
		) );
		$files = $this->extractFilesFromRows( $datatable[ 'data' ] ?? [] );

		$this->assertEqualsCanonicalizing( [ 'plugin-file.php', 'plugin-file-2.php' ], $files );
		$this->assertSame( 2, (int)( $datatable[ 'recordsTotal' ] ?? 0 ) );
		$this->assertSame( 2, (int)( $datatable[ 'recordsFiltered' ] ?? 0 ) );
		$this->assertFalse( \in_array( 'wp-admin/admin.php', $files, true ) );
		$this->assertFalse( \in_array( 'theme-file.php', $files, true ) );
		$this->assertFalse( \in_array( 'other-plugin-file.php', $files, true ) );
	}

	public function testValidFileScanResultsThemePayloadReturnsOnlyThemeRowsAndScopedCounts() :void {
		$pluginSlug = $this->firstInstalledPluginSlug();
		$themeSlug = $this->firstInstalledThemeSlug();
		$otherThemeSlug = $this->secondInstalledThemeSlug( $themeSlug );
		$afsId = TestDataFactory::insertCompletedScan( 'afs' );
		TestDataFactory::insertScanResultItem( $afsId, [
			'item_id'     => 'theme-file.php',
			'is_in_theme' => 1,
			'ptg_slug'    => $themeSlug,
		] );
		TestDataFactory::insertScanResultItem( $afsId, [
			'item_id'     => 'theme-file-2.php',
			'is_in_theme' => 1,
			'ptg_slug'    => $themeSlug,
		] );
		TestDataFactory::insertScanResultItem( $afsId, [
			'item_id'      => 'plugin-file.php',
			'is_in_plugin' => 1,
			'ptg_slug'     => $pluginSlug,
		] );
		TestDataFactory::insertScanResultItem( $afsId, [
			'item_id'    => 'wp-admin/admin.php',
			'is_in_core' => 1,
		] );
		if ( !empty( $otherThemeSlug ) ) {
			TestDataFactory::insertScanResultItem( $afsId, [
				'item_id'     => 'other-theme-file.php',
				'is_in_theme' => 1,
				'ptg_slug'    => $otherThemeSlug,
			] );
		}

		$datatable = $this->assertSuccessfulDatatablePayload( $this->fetchInvestigationTablePayload(
			InvestigationTableContract::TABLE_TYPE_FILE_SCAN_RESULTS,
			InvestigationTableContract::SUBJECT_TYPE_THEME,
			$themeSlug
		) );
		$files = $this->extractFilesFromRows( $datatable[ 'data' ] ?? [] );

		$this->assertEqualsCanonicalizing( [ 'theme-file.php', 'theme-file-2.php' ], $files );
		$this->assertSame( 2, (int)( $datatable[ 'recordsTotal' ] ?? 0 ) );
		$this->assertSame( 2, (int)( $datatable[ 'recordsFiltered' ] ?? 0 ) );
		$this->assertFalse( \in_array( 'plugin-file.php', $files, true ) );
		$this->assertFalse( \in_array( 'wp-admin/admin.php', $files, true ) );
		$this->assertFalse( \in_array( 'other-theme-file.php', $files, true ) );
	}

	public function testValidMalwareScanResultsPayloadReturnsDatatableEnvelope() :void {
		$afsId = TestDataFactory::insertCompletedScan( 'afs' );
		TestDataFactory::insertScanResultItem( $afsId, [
			'item_id' => 'infected.php',
			'is_mal'  => 1,
		] );

		$payload = $this->processor()->processAction( InvestigationTableAction::SLUG, [
			InvestigationTableContract::REQ_KEY_SUB_ACTION   => InvestigationTableContract::SUB_ACTION_RETRIEVE_TABLE_DATA,
			InvestigationTableContract::REQ_KEY_TABLE_TYPE   => InvestigationTableContract::TABLE_TYPE_MALWARE_SCAN_RESULTS,
			InvestigationTableContract::REQ_KEY_SUBJECT_TYPE => InvestigationTableContract::SUBJECT_TYPE_MALWARE,
			InvestigationTableContract::REQ_KEY_SUBJECT_ID   => InvestigationTableContract::SUBJECT_TYPE_MALWARE,
			InvestigationTableContract::REQ_KEY_TABLE_DATA   => $this->tableDataFixture(),
		] )->payload();

		$this->assertTrue( $payload[ 'success' ] ?? false );
		$this->assertArrayHasKey( 'datatable_data', $payload );
		$this->assertArrayHasKey( 'data', $payload[ 'datatable_data' ] );
	}

	public function testPluginActivityRowsIncludeInvestigatePluginLinkWhenPluginMetaPresent() :void {
		$pluginSlug = $this->firstInstalledPluginSlug();
		$logId = TestDataFactory::insertActivityLog( 'plugin_file_edited', '203.0.113.201' );
		TestDataFactory::insertActivityLogMeta( $logId, 'plugin', $pluginSlug );
		TestDataFactory::insertActivityLogMeta( $logId, 'file', $pluginSlug );

		$payload = $this->processor()->processAction( InvestigationTableAction::SLUG, [
			InvestigationTableContract::REQ_KEY_SUB_ACTION   => InvestigationTableContract::SUB_ACTION_RETRIEVE_TABLE_DATA,
			InvestigationTableContract::REQ_KEY_TABLE_TYPE   => InvestigationTableContract::TABLE_TYPE_ACTIVITY,
			InvestigationTableContract::REQ_KEY_SUBJECT_TYPE => InvestigationTableContract::SUBJECT_TYPE_PLUGIN,
			InvestigationTableContract::REQ_KEY_SUBJECT_ID   => $pluginSlug,
			InvestigationTableContract::REQ_KEY_TABLE_DATA   => $this->tableDataFixture(),
		] )->payload();

		$this->assertTrue( $payload[ 'success' ] ?? false );
		$rows = $payload[ 'datatable_data' ][ 'data' ] ?? [];
		$this->assertTrue(
			$this->rowsContain( $rows, 'Investigate Plugin' ),
			'Expected an activity row message with "Investigate Plugin".'
		);
	}

	public function testThemeActivityRowsIncludeInvestigateThemeLinkWhenThemeMetaPresent() :void {
		$themeSlug = $this->firstInstalledThemeSlug();
		$logId = TestDataFactory::insertActivityLog( 'theme_file_edited', '203.0.113.202' );
		TestDataFactory::insertActivityLogMeta( $logId, 'theme', $themeSlug );
		TestDataFactory::insertActivityLogMeta( $logId, 'file', $themeSlug.'/functions.php' );

		$payload = $this->processor()->processAction( InvestigationTableAction::SLUG, [
			InvestigationTableContract::REQ_KEY_SUB_ACTION   => InvestigationTableContract::SUB_ACTION_RETRIEVE_TABLE_DATA,
			InvestigationTableContract::REQ_KEY_TABLE_TYPE   => InvestigationTableContract::TABLE_TYPE_ACTIVITY,
			InvestigationTableContract::REQ_KEY_SUBJECT_TYPE => InvestigationTableContract::SUBJECT_TYPE_THEME,
			InvestigationTableContract::REQ_KEY_SUBJECT_ID   => $themeSlug,
			InvestigationTableContract::REQ_KEY_TABLE_DATA   => $this->tableDataFixture(),
		] )->payload();

		$this->assertTrue( $payload[ 'success' ] ?? false );
		$rows = $payload[ 'datatable_data' ][ 'data' ] ?? [];
		$this->assertTrue(
			$this->rowsContain( $rows, 'Investigate Theme' ),
			'Expected an activity row message with "Investigate Theme".'
		);
	}

	private function rowsContain( array $rows, string $needle ) :bool {
		foreach ( $rows as $row ) {
			$message = (string)( $row[ 'message' ] ?? '' );
			if ( \strpos( $message, $needle ) !== false ) {
				return true;
			}
		}
		return false;
	}

	private function fetchInvestigationTablePayload( string $tableType, string $subjectType, string $subjectId ) :array {
		return $this->processor()->processAction( InvestigationTableAction::SLUG, [
			InvestigationTableContract::REQ_KEY_SUB_ACTION   => InvestigationTableContract::SUB_ACTION_RETRIEVE_TABLE_DATA,
			InvestigationTableContract::REQ_KEY_TABLE_TYPE   => $tableType,
			InvestigationTableContract::REQ_KEY_SUBJECT_TYPE => $subjectType,
			InvestigationTableContract::REQ_KEY_SUBJECT_ID   => $subjectId,
			InvestigationTableContract::REQ_KEY_TABLE_DATA   => $this->tableDataFixture(),
		] )->payload();
	}

	private function assertSuccessfulDatatablePayload( array $payload ) :array {
		$this->assertTrue( $payload[ 'success' ] ?? false );
		$this->assertArrayHasKey( 'datatable_data', $payload );
		$this->assertArrayHasKey( 'data', $payload[ 'datatable_data' ] );
		$this->assertArrayHasKey( 'recordsTotal', $payload[ 'datatable_data' ] );
		$this->assertArrayHasKey( 'recordsFiltered', $payload[ 'datatable_data' ] );

		return $payload[ 'datatable_data' ];
	}

	private function extractFilesFromRows( array $rows ) :array {
		return \array_values( \array_map(
			static fn( array $row ) :string => (string)( $row[ 'file' ] ?? '' ),
			$rows
		) );
	}

	private function firstInstalledPluginSlug() :string {
		$plugins = Services::WpPlugins()->getInstalledPluginFiles();
		if ( empty( $plugins ) ) {
			$this->markTestSkipped( 'No installed plugins were available for activity table integration test.' );
		}
		return (string)\array_values( $plugins )[ 0 ];
	}

	private function secondInstalledPluginSlug( string $exclude ) :?string {
		$plugins = \array_values( \array_filter(
			Services::WpPlugins()->getInstalledPluginFiles(),
			static fn( string $plugin ) :bool => $plugin !== $exclude
		) );
		return empty( $plugins ) ? null : (string)$plugins[ 0 ];
	}

	private function firstInstalledThemeSlug() :string {
		$themes = Services::WpThemes()->getInstalledStylesheets();
		if ( empty( $themes ) ) {
			$this->markTestSkipped( 'No installed themes were available for activity table integration test.' );
		}
		return (string)\array_values( $themes )[ 0 ];
	}

	private function secondInstalledThemeSlug( string $exclude ) :?string {
		$themes = \array_values( \array_filter(
			Services::WpThemes()->getInstalledStylesheets(),
			static fn( string $theme ) :bool => $theme !== $exclude
		) );
		return empty( $themes ) ? null : (string)$themes[ 0 ];
	}
}
