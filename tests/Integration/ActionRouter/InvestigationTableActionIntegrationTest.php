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
		$userIds = \array_values( \array_map(
			static fn( array $row ) :int => (int)( $row[ 'uid' ] ?? 0 ),
			$rows
		) );

		$this->assertSame( 2, \count( $rows ) );
		$this->assertSame( 2, (int)( $datatable[ 'recordsTotal' ] ?? 0 ) );
		$this->assertSame( 2, (int)( $datatable[ 'recordsFiltered' ] ?? 0 ) );
		$this->assertEqualsCanonicalizing( [ $primaryUserId, $secondaryUserId ], $userIds );
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

		$this->assertNotEmpty( $ips );
		$this->assertSame( [ $targetIp ], \array_values( \array_unique( $ips ) ) );
		$this->assertGreaterThanOrEqual( 1, (int)( $datatable[ 'recordsTotal' ] ?? 0 ) );
		$this->assertGreaterThanOrEqual( 1, (int)( $datatable[ 'recordsFiltered' ] ?? 0 ) );
		$this->assertFalse( \in_array( $otherIp, $ips, true ) );
	}

	public function testGetRequestMetaReturnsStructuredMetaForInvestigatedActivityRequest() :void {
		$rid = 'rqmeta1234';
		$requestId = TestDataFactory::insertRequestLog( '203.0.113.103', [
			'rid'  => $rid,
			'verb' => 'POST',
			'path' => '/fixture/request-meta',
			'code' => 418,
			'meta' => [
				'ua' => 'Investigation Integration Fixture/1.0',
				'ts' => '1710000000',
			],
		] );
		TestDataFactory::insertActivityLogForRequest( $requestId, 'user_login' );

		$payload = $this->processor()->processAction( InvestigationTableAction::SLUG, [
			InvestigationTableContract::REQ_KEY_SUB_ACTION => InvestigationTableContract::SUB_ACTION_GET_REQUEST_META,
			InvestigationTableContract::REQ_KEY_RID        => $rid,
		] )->payload();

		$this->assertTrue( $payload[ 'success' ] ?? false );
		$meta = \is_array( $payload[ 'request_meta' ] ?? null ) ? $payload[ 'request_meta' ] : [];
		$this->assertTrue( (bool)( $meta[ 'is_valid' ] ?? false ) );
		$this->assertSame( $rid, (string)( $meta[ 'rid' ] ?? '' ) );
		$this->assertSame( '/fixture/request-meta', (string)( $meta[ 'values' ][ 'path' ] ?? '' ) );
		$this->assertSame( '0', (string)( $meta[ 'values' ][ 'uid' ] ?? '' ) );
		$this->assertArrayHasKey( 'ua', $meta[ 'values' ] ?? [] );
		$this->assertSame( '418', (string)( $meta[ 'values' ][ 'code' ] ?? '' ) );
		$this->assertSame( 'POST', (string)( $meta[ 'values' ][ 'verb' ] ?? '' ) );
		$this->assertSame( [ 'rid', 'type', 'uid', 'ts', 'verb', 'path', 'code', 'ua' ], \array_column( $meta[ 'fields' ] ?? [], 'key' ) );
		$this->assertArrayHasKey( 'html', $payload );
	}

	public function testGetRequestMetaReturnsInvalidStructuredContractForUnknownRid() :void {
		$payload = $this->processor()->processAction( InvestigationTableAction::SLUG, [
			InvestigationTableContract::REQ_KEY_SUB_ACTION => InvestigationTableContract::SUB_ACTION_GET_REQUEST_META,
			InvestigationTableContract::REQ_KEY_RID        => 'missingrid01',
		] )->payload();

		$meta = \is_array( $payload[ 'request_meta' ] ?? null ) ? $payload[ 'request_meta' ] : [];
		$this->assertTrue( $payload[ 'success' ] ?? false );
		$this->assertSame( 'missingrid01', (string)( $meta[ 'rid' ] ?? '' ) );
		$this->assertFalse( (bool)( $meta[ 'is_valid' ] ?? true ) );
		$this->assertSame( [], $meta[ 'values' ] ?? [ 'unexpected' ] );
		$this->assertSame( [], $meta[ 'fields' ] ?? [ 'unexpected' ] );
		$this->assertArrayHasKey( 'html', $payload );
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
	}

	public function testTrafficIpPayloadRejectsTamperedOrderColumnAtSqlSink() :void {
		$targetIp = '203.0.113.113';
		$otherIp = '203.0.113.114';

		TestDataFactory::insertRequestLog( $targetIp, [
			'path' => '/target-order-one',
		] );
		TestDataFactory::insertRequestLog( $targetIp, [
			'path' => '/target-order-two',
		] );
		TestDataFactory::insertRequestLog( $otherIp, [
			'path' => '/other-order',
		] );

		$datatable = $this->assertSuccessfulDatatablePayload( $this->fetchInvestigationTablePayload(
			InvestigationTableContract::TABLE_TYPE_TRAFFIC,
			InvestigationTableContract::SUBJECT_TYPE_IP,
			$targetIp,
			$this->tableDataFixture( [
				'order'   => [
					[
						'column' => 0,
						'dir'    => 'sideways',
					],
				],
				'columns' => [
					[
						'data' => "path` DESC, (SELECT SLEEP(1)) -- ",
					],
				],
			] )
		) );
		$rows = $datatable[ 'data' ] ?? [];
		$ips = \array_values( \array_map(
			static fn( array $row ) :string => (string)( $row[ 'ip' ] ?? '' ),
			$rows
		) );

		$this->assertSame( [ $targetIp, $targetIp ], $ips );
		$this->assertSame( 2, (int)( $datatable[ 'recordsTotal' ] ?? 0 ) );
		$this->assertSame( 2, (int)( $datatable[ 'recordsFiltered' ] ?? 0 ) );
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

	public function testPluginActivityRowsIncludeSubjectRowsWhenPluginMetaPresent() :void {
		$pluginSlug = $this->firstInstalledPluginSlug();
		$rid = 'plugmeta01';
		$requestId = TestDataFactory::insertRequestLog( '203.0.113.201', [
			'rid' => $rid,
		] );
		$logId = TestDataFactory::insertActivityLogForRequest( $requestId, 'plugin_file_edited' );
		TestDataFactory::insertActivityLogMeta( $logId, 'plugin', $pluginSlug );
		TestDataFactory::insertActivityLogMeta( $logId, 'file', $pluginSlug );

		$datatable = $this->assertSuccessfulDatatablePayload( $this->fetchInvestigationTablePayload(
			InvestigationTableContract::TABLE_TYPE_ACTIVITY,
			InvestigationTableContract::SUBJECT_TYPE_PLUGIN,
			$pluginSlug,
			$this->tableDataFixture( [ 'length' => 100 ] )
		) );

		$this->assertContains( $rid, $this->rowRids( $datatable[ 'data' ] ?? [] ) );
	}

	public function testPluginActivityFileOnlyFallbackUsesExactAndPrefixScope() :void {
		$pluginSlug = $this->firstInstalledPluginSlug();
		$pluginDir = \trim( \dirname( $pluginSlug ), './\\' );
		$targetExactRid = 'plugfile01';
		$targetPrefixRid = 'plugfile02';
		$unrelatedRid = 'plugfile03';

		$exactRequestId = TestDataFactory::insertRequestLog( '203.0.113.203', [
			'rid' => $targetExactRid,
		] );
		$exactLogId = TestDataFactory::insertActivityLogForRequest( $exactRequestId, 'plugin_file_edited' );
		TestDataFactory::insertActivityLogMeta( $exactLogId, 'file', $pluginSlug );

		if ( !empty( $pluginDir ) && $pluginDir !== '.' ) {
			$prefixRequestId = TestDataFactory::insertRequestLog( '203.0.113.204', [
				'rid' => $targetPrefixRid,
			] );
			$prefixLogId = TestDataFactory::insertActivityLogForRequest( $prefixRequestId, 'plugin_file_edited' );
			TestDataFactory::insertActivityLogMeta( $prefixLogId, 'file', $pluginDir.'/unit8-target.php' );
			$unrelatedFile = 'other/'.$pluginDir.'/unit8-unrelated.php';
		}
		else {
			$targetPrefixRid = '';
			$unrelatedFile = 'other/'.$pluginSlug;
		}

		$unrelatedRequestId = TestDataFactory::insertRequestLog( '203.0.113.205', [
			'rid' => $unrelatedRid,
		] );
		$unrelatedLogId = TestDataFactory::insertActivityLogForRequest( $unrelatedRequestId, 'plugin_file_edited' );
		TestDataFactory::insertActivityLogMeta( $unrelatedLogId, 'file', $unrelatedFile );

		$datatable = $this->assertSuccessfulDatatablePayload( $this->fetchInvestigationTablePayload(
			InvestigationTableContract::TABLE_TYPE_ACTIVITY,
			InvestigationTableContract::SUBJECT_TYPE_PLUGIN,
			$pluginSlug,
			$this->tableDataFixture( [ 'length' => 100 ] )
		) );
		$rids = $this->rowRids( $datatable[ 'data' ] ?? [] );

		$this->assertContains( $targetExactRid, $rids );
		if ( !empty( $targetPrefixRid ) ) {
			$this->assertContains( $targetPrefixRid, $rids );
		}
		$this->assertNotContains( $unrelatedRid, $rids );
	}

	public function testThemeActivityRowsIncludeSubjectRowsWhenThemeMetaPresent() :void {
		$themeSlug = $this->firstInstalledThemeSlug();
		$rid = 'thememeta1';
		$requestId = TestDataFactory::insertRequestLog( '203.0.113.202', [
			'rid' => $rid,
		] );
		$logId = TestDataFactory::insertActivityLogForRequest( $requestId, 'theme_file_edited' );
		TestDataFactory::insertActivityLogMeta( $logId, 'theme', $themeSlug );
		TestDataFactory::insertActivityLogMeta( $logId, 'file', $themeSlug.'/functions.php' );

		$datatable = $this->assertSuccessfulDatatablePayload( $this->fetchInvestigationTablePayload(
			InvestigationTableContract::TABLE_TYPE_ACTIVITY,
			InvestigationTableContract::SUBJECT_TYPE_THEME,
			$themeSlug,
			$this->tableDataFixture( [ 'length' => 100 ] )
		) );

		$this->assertContains( $rid, $this->rowRids( $datatable[ 'data' ] ?? [] ) );
	}

	private function rowRids( array $rows ) :array {
		return \array_values( \array_map(
			static fn( array $row ) :string => (string)( $row[ 'rid' ] ?? '' ),
			$rows
		) );
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

	private function firstInstalledPluginSlug() :string {
		$plugins = Services::WpPlugins()->getInstalledPluginFiles();
		if ( empty( $plugins ) ) {
			$this->markTestSkipped( 'No installed plugins were available for activity table integration test.' );
		}
		return (string)\array_values( $plugins )[ 0 ];
	}

	private function firstInstalledThemeSlug() :string {
		$themes = Services::WpThemes()->getInstalledStylesheets();
		if ( empty( $themes ) ) {
			$this->markTestSkipped( 'No installed themes were available for activity table integration test.' );
		}
		return (string)\array_values( $themes )[ 0 ];
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
