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

	private function tableDataFixture( array $overrides = [] ) :array {
		return \array_replace_recursive( [
			'search'  => [ 'value' => '' ],
			'start'   => 0,
			'length'  => 10,
			'order'   => [],
			'columns' => [],
		], $overrides );
	}

	private function pluginMainPathFragment( string $pluginSlug ) :string {
		return TestDataFactory::pathFragmentFromAbsolutePath( WP_PLUGIN_DIR.'/'.$pluginSlug );
	}

	private function themeMainPathFragment( string $themeSlug ) :string {
		return TestDataFactory::pathFragmentFromAbsolutePath( \get_theme_root().'/'.$themeSlug.'/style.css' );
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
			InvestigationTableContract::REQ_KEY_SUBJECT_TYPE => InvestigationTableContract::SUBJECT_TYPE_PLUGIN,
			InvestigationTableContract::REQ_KEY_SUBJECT_ID   => 'test-plugin/test-plugin.php',
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

	public function testValidSessionsIpPayloadReturnsOnlySessionsForInvestigatedIp() :void {
		$targetIp = '203.0.113.88';
		$otherIp = '203.0.113.89';
		$primaryUserId = $this->createAdministratorUser( [ 'user_login' => 'ip_session_admin' ] );
		$secondaryUserId = self::factory()->user->create( [
			'role'       => 'subscriber',
			'user_login' => 'ip_session_subscriber',
		] );

		$this->createActiveSessionForIp( $primaryUserId, $targetIp, Services::Request()->ts() - 600, Services::Request()->ts() - 300 );
		$this->createActiveSessionForIp( $secondaryUserId, $targetIp, Services::Request()->ts() - 500, Services::Request()->ts() - 200 );
		$this->createActiveSessionForIp( $primaryUserId, $otherIp, Services::Request()->ts() - 400, Services::Request()->ts() - 100 );

		$datatable = $this->assertSuccessfulDatatablePayload( $this->fetchInvestigationTablePayload(
			InvestigationTableContract::TABLE_TYPE_SESSIONS,
			InvestigationTableContract::SUBJECT_TYPE_IP,
			$targetIp
		) );
		$rows = $datatable[ 'data' ] ?? [];

		$this->assertSame( 2, \count( $rows ) );
		$this->assertSame( 2, (int)( $datatable[ 'recordsTotal' ] ?? 0 ) );
		$this->assertSame( 2, (int)( $datatable[ 'recordsFiltered' ] ?? 0 ) );
		$this->assertCount( 2, \array_filter(
			$rows,
			fn( array $row ) :bool => \strpos( (string)( $row[ 'details' ] ?? '' ), $targetIp ) !== false
		) );
		$this->assertCount( 0, \array_filter(
			$rows,
			fn( array $row ) :bool => \strpos( (string)( $row[ 'details' ] ?? '' ), $otherIp ) !== false
		) );
	}

	public function testValidSessionsIpPayloadHonorsUidSearchPaneFilter() :void {
		$targetIp = '203.0.113.90';
		$primaryUserId = $this->createAdministratorUser( [ 'user_login' => 'ip_session_filter_admin' ] );
		$secondaryUserId = self::factory()->user->create( [
			'role'       => 'subscriber',
			'user_login' => 'ip_session_filter_subscriber',
		] );

		$this->createActiveSessionForIp( $primaryUserId, $targetIp, Services::Request()->ts() - 600, Services::Request()->ts() - 300 );
		$this->createActiveSessionForIp( $secondaryUserId, $targetIp, Services::Request()->ts() - 500, Services::Request()->ts() - 200 );

		$datatable = $this->assertSuccessfulDatatablePayload( $this->fetchInvestigationTablePayload(
			InvestigationTableContract::TABLE_TYPE_SESSIONS,
			InvestigationTableContract::SUBJECT_TYPE_IP,
			$targetIp,
			$this->tableDataFixture( [
				'searchPanes' => [
					'uid' => [ (string)$secondaryUserId ],
				],
			] )
		) );
		$rows = $datatable[ 'data' ] ?? [];
		$userIds = \array_values( \array_unique( \array_map(
			static fn( array $row ) :int => (int)( $row[ 'uid' ] ?? 0 ),
			$rows
		) ) );

		$this->assertSame( 2, (int)( $datatable[ 'recordsTotal' ] ?? 0 ) );
		$this->assertSame( 1, (int)( $datatable[ 'recordsFiltered' ] ?? 0 ) );
		$this->assertCount( 1, $rows );
		$this->assertSame( [ $secondaryUserId ], $userIds );
		$this->assertCount( 1, \array_filter(
			$rows,
			fn( array $row ) :bool => \strpos( (string)( $row[ 'details' ] ?? '' ), $targetIp ) !== false
		) );
	}

	public function testValidActivityIpPayloadReturnsOnlyRowsForInvestigatedIp() :void {
		$targetIp = '203.0.113.101';
		$otherIp = '203.0.113.102';

		TestDataFactory::insertActivityLog( 'user_login', $targetIp );
		TestDataFactory::insertActivityLog( 'user_logout', $targetIp );
		TestDataFactory::insertActivityLog( 'user_login', $otherIp );

		$datatable = $this->assertSuccessfulDatatablePayload( $this->fetchInvestigationTablePayload(
			InvestigationTableContract::TABLE_TYPE_ACTIVITY,
			InvestigationTableContract::SUBJECT_TYPE_IP,
			$targetIp
		) );
		$ips = \array_values( \array_map(
			static fn( array $row ) :string => (string)( $row[ 'ip' ] ?? '' ),
			$datatable[ 'data' ] ?? []
		) );

		$this->assertSame( [ $targetIp, $targetIp ], $ips );
		$this->assertSame( 2, (int)( $datatable[ 'recordsTotal' ] ?? 0 ) );
		$this->assertSame( 2, (int)( $datatable[ 'recordsFiltered' ] ?? 0 ) );
		$this->assertFalse( \in_array( $otherIp, $ips, true ) );
	}

	public function testValidTrafficIpPayloadReturnsOnlyRowsForInvestigatedIp() :void {
		$targetIp = '203.0.113.111';
		$otherIp = '203.0.113.112';

		TestDataFactory::insertRequestLog( $targetIp, [
			'path' => '/target-one',
		] );
		TestDataFactory::insertRequestLog( $targetIp, [
			'path' => '/target-two',
		] );
		TestDataFactory::insertRequestLog( $otherIp, [
			'path' => '/other-ip',
		] );

		$datatable = $this->assertSuccessfulDatatablePayload( $this->fetchInvestigationTablePayload(
			InvestigationTableContract::TABLE_TYPE_TRAFFIC,
			InvestigationTableContract::SUBJECT_TYPE_IP,
			$targetIp
		) );
		$rows = $datatable[ 'data' ] ?? [];
		$ips = \array_values( \array_map(
			static fn( array $row ) :string => (string)( $row[ 'ip' ] ?? '' ),
			$rows
		) );

		$this->assertSame( [ $targetIp, $targetIp ], $ips );
		$this->assertSame( 2, (int)( $datatable[ 'recordsTotal' ] ?? 0 ) );
		$this->assertSame( 2, (int)( $datatable[ 'recordsFiltered' ] ?? 0 ) );
		$this->assertCount( 0, \array_filter(
			$rows,
			fn( array $row ) :bool => \strpos( (string)( $row[ 'page' ] ?? '' ), '/other-ip' ) !== false
		) );
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
		TestDataFactory::insertAfsFileScanResult( $afsId, $this->pluginMainPathFragment( $pluginSlug ), [
			'is_in_plugin' => 1,
			'ptg_slug'     => $pluginSlug,
		] );
		TestDataFactory::insertAfsFileScanResult( $afsId, $this->themeMainPathFragment( $themeSlug ), [
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
		TestDataFactory::insertAfsFileScanResult( $afsId, $this->pluginMainPathFragment( $pluginSlug ), [
			'is_in_plugin' => 1,
			'ptg_slug'     => $pluginSlug,
		] );
		TestDataFactory::insertAfsFileScanResult( $afsId, $this->pluginMainPathFragment( $pluginSlug ), [
			'is_in_plugin' => 1,
			'ptg_slug'     => $pluginSlug,
		] );
		TestDataFactory::insertScanResultItem( $afsId, [
			'item_id'    => 'wp-admin/admin.php',
			'is_in_core' => 1,
		] );
		TestDataFactory::insertAfsFileScanResult( $afsId, $this->themeMainPathFragment( $themeSlug ), [
			'is_in_theme' => 1,
			'ptg_slug'    => $themeSlug,
		] );
		if ( !empty( $otherPluginSlug ) ) {
			TestDataFactory::insertAfsFileScanResult( $afsId, $this->pluginMainPathFragment( $otherPluginSlug ), [
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

		$this->assertSame( 2, \count( $files ) );
		$this->assertSame(
			[ $this->pluginMainPathFragment( $pluginSlug ), $this->pluginMainPathFragment( $pluginSlug ) ],
			$files
		);
		$this->assertSame( 2, (int)( $datatable[ 'recordsTotal' ] ?? 0 ) );
		$this->assertSame( 2, (int)( $datatable[ 'recordsFiltered' ] ?? 0 ) );
		$this->assertFalse( \in_array( 'wp-admin/admin.php', $files, true ) );
		$this->assertFalse( \in_array( $this->themeMainPathFragment( $themeSlug ), $files, true ) );
		$this->assertFalse( !empty( $otherPluginSlug ) && \in_array( $this->pluginMainPathFragment( $otherPluginSlug ), $files, true ) );
	}

	public function testValidFileScanResultsPluginPayloadPreparesStaleRowsBeforeLaterPageAndCountQueries() :void {
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

		$pluginSlug = $this->firstInstalledPluginSlug();
		$afsId = TestDataFactory::insertCompletedScan( 'afs' );
		$stale = TestDataFactory::insertAfsFileScanResultTracked( $afsId, $this->pluginMainPathFragment( $pluginSlug ), [
			'is_in_plugin'   => 1,
			'ptg_slug'       => $pluginSlug,
			'is_checksumfail' => 1,
		] );

		for ( $i = 0; $i < 10; $i++ ) {
			TestDataFactory::insertAfsFileScanResult( $afsId, $this->pluginMainPathFragment( $pluginSlug ), [
				'is_in_plugin' => 1,
				'ptg_slug'     => $pluginSlug,
			] );
		}

		$datatable = $this->assertSuccessfulDatatablePayload( $this->fetchInvestigationTablePayload(
			InvestigationTableContract::TABLE_TYPE_FILE_SCAN_RESULTS,
			InvestigationTableContract::SUBJECT_TYPE_PLUGIN,
			$pluginSlug,
			$this->tableDataFixture( [
				'start'  => 10,
				'length' => 10,
			] )
		) );

		$this->assertSame( 10, (int)( $datatable[ 'recordsTotal' ] ?? -1 ) );
		$this->assertSame( 10, (int)( $datatable[ 'recordsFiltered' ] ?? -1 ) );
		$this->assertCount( 0, $datatable[ 'data' ] ?? [] );

		$item = self::con()->db_con->scan_result_items->getQuerySelector()->byId( (int)$stale[ 'result_item_id' ] );
		$this->assertNotEmpty( $item );
		$this->assertGreaterThan( 0, (int)( $item->item_repaired_at ?? 0 ) );
	}

	public function testValidFileScanResultsThemePayloadReturnsOnlyThemeRowsAndScopedCounts() :void {
		$pluginSlug = $this->firstInstalledPluginSlug();
		$themeSlug = $this->firstInstalledThemeSlug();
		$otherThemeSlug = $this->secondInstalledThemeSlug( $themeSlug );
		$afsId = TestDataFactory::insertCompletedScan( 'afs' );
		TestDataFactory::insertAfsFileScanResult( $afsId, $this->themeMainPathFragment( $themeSlug ), [
			'is_in_theme' => 1,
			'ptg_slug'    => $themeSlug,
		] );
		TestDataFactory::insertAfsFileScanResult( $afsId, $this->themeMainPathFragment( $themeSlug ), [
			'is_in_theme' => 1,
			'ptg_slug'    => $themeSlug,
		] );
		TestDataFactory::insertAfsFileScanResult( $afsId, $this->pluginMainPathFragment( $pluginSlug ), [
			'is_in_plugin' => 1,
			'ptg_slug'     => $pluginSlug,
		] );
		TestDataFactory::insertScanResultItem( $afsId, [
			'item_id'    => 'wp-admin/admin.php',
			'is_in_core' => 1,
		] );
		if ( !empty( $otherThemeSlug ) ) {
			TestDataFactory::insertAfsFileScanResult( $afsId, $this->themeMainPathFragment( $otherThemeSlug ), [
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

		$this->assertSame(
			[ $this->themeMainPathFragment( $themeSlug ), $this->themeMainPathFragment( $themeSlug ) ],
			$files
		);
		$this->assertSame( 2, (int)( $datatable[ 'recordsTotal' ] ?? 0 ) );
		$this->assertSame( 2, (int)( $datatable[ 'recordsFiltered' ] ?? 0 ) );
		$this->assertFalse( \in_array( $this->pluginMainPathFragment( $pluginSlug ), $files, true ) );
		$this->assertFalse( \in_array( 'wp-admin/admin.php', $files, true ) );
		$this->assertFalse( !empty( $otherThemeSlug ) && \in_array( $this->themeMainPathFragment( $otherThemeSlug ), $files, true ) );
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

	private function fetchInvestigationTablePayload(
		string $tableType,
		string $subjectType,
		string $subjectId,
		?array $tableData = null
	) :array {
		return $this->processor()->processAction( InvestigationTableAction::SLUG, [
			InvestigationTableContract::REQ_KEY_SUB_ACTION   => InvestigationTableContract::SUB_ACTION_RETRIEVE_TABLE_DATA,
			InvestigationTableContract::REQ_KEY_TABLE_TYPE   => $tableType,
			InvestigationTableContract::REQ_KEY_SUBJECT_TYPE => $subjectType,
			InvestigationTableContract::REQ_KEY_SUBJECT_ID   => $subjectId,
			InvestigationTableContract::REQ_KEY_TABLE_DATA   => $tableData ?? $this->tableDataFixture(),
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

	private function createActiveSessionForIp( int $userId, string $ip, int $loginAt, int $lastActivityAt ) :void {
		$manager = \WP_Session_Tokens::get_instance( $userId );
		$token = $manager->create( Services::Request()->ts() + HOUR_IN_SECONDS );
		$session = $manager->get( $token );

		$this->assertIsArray( $session );

		$session[ 'login' ] = $loginAt;
		$session[ 'ip' ] = $ip;
		$session[ 'shield' ] = [
			'user_id'          => $userId,
			'unique'           => \wp_generate_password( 12, false, false ),
			'ip'               => $ip,
			'useragent'        => 'Integration Test Agent',
			'last_activity_at' => $lastActivityAt,
			'secadmin_at'      => 0,
		];

		$manager->update( $token, $session );
	}
}
