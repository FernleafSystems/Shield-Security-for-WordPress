<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	ActionProcessor,
	Actions\ReportTableAction,
	Exceptions\InvalidActionNonceException
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\Constants;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build\ForReports;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter\Support\ActionRequestNonceFixture;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class ReportTableActionIntegrationTest extends ShieldIntegrationTestCase {

	use ActionRequestNonceFixture;

	public function set_up() {
		parent::set_up();
		$this->requireDb( 'reports' );
		$this->loginAsSecurityAdmin();
		$this->requireController()->this_req->wp_is_ajax = false;
	}

	public function test_retrieve_table_data_uses_report_constraints_and_search() :void {
		$alphaId = $this->insertReport( 'Alpha Report', [
			'created_at' => 100,
		] );
		$betaId = $this->insertReport( 'Beta Report', [
			'created_at' => 200,
		] );
		$this->insertReport( 'Hidden Empty Content', [
			'content' => '',
		] );
		$this->insertReport( 'Hidden Empty UniqueId', [
			'unique_id' => '',
		] );

		$payload = $this->processReportTableAction( [
			'sub_action' => 'retrieve_table_data',
			'table_data' => $this->buildTableDataRequest(),
		] );

		$this->assertTrue( $payload[ 'success' ] ?? false );
		$this->assertSame( 2, $payload[ 'datatable_data' ][ 'recordsTotal' ] ?? null );
		$this->assertSame( 2, $payload[ 'datatable_data' ][ 'recordsFiltered' ] ?? null );
		$this->assertCount( 2, $payload[ 'datatable_data' ][ 'data' ] ?? [] );
		$this->assertSame(
			[ $betaId, $alphaId ],
			\array_column( $payload[ 'datatable_data' ][ 'data' ] ?? [], 'rid' )
		);

		$searchPayload = $this->processReportTableAction( [
			'sub_action' => 'retrieve_table_data',
			'table_data' => $this->buildTableDataRequest( 'Alpha' ),
		] );

		$this->assertTrue( $searchPayload[ 'success' ] ?? false );
		$this->assertSame( 2, $searchPayload[ 'datatable_data' ][ 'recordsTotal' ] ?? null );
		$this->assertSame( 1, $searchPayload[ 'datatable_data' ][ 'recordsFiltered' ] ?? null );
		$this->assertCount( 1, $searchPayload[ 'datatable_data' ][ 'data' ] ?? [] );
		$this->assertSame( $alphaId, $searchPayload[ 'datatable_data' ][ 'data' ][ 0 ][ 'rid' ] ?? null );
	}

	public function test_delete_sub_action_removes_report_without_page_reload() :void {
		$reportId = $this->insertReport( 'Delete Me' );
		$this->assertNotEmpty( self::con()->db_con->reports->getQuerySelector()->byId( $reportId ) );

		$payload = $this->processReportTableAction( [
			'sub_action' => 'delete',
			'rids'       => [ $reportId ],
		] );

		$this->assertTrue( $payload[ 'success' ] ?? false );
		$this->assertFalse( $payload[ 'page_reload' ] ?? true );
		$this->assertEmpty( self::con()->db_con->reports->getQuerySelector()->byId( $reportId ) );
	}

	public function test_delete_sub_action_removes_multiple_reports_without_page_reload() :void {
		$reportIdA = $this->insertReport( 'Delete Me A' );
		$reportIdB = $this->insertReport( 'Delete Me B' );
		$this->assertNotEmpty( self::con()->db_con->reports->getQuerySelector()->byId( $reportIdA ) );
		$this->assertNotEmpty( self::con()->db_con->reports->getQuerySelector()->byId( $reportIdB ) );

		$payload = $this->processReportTableAction( [
			'sub_action' => 'delete',
			'rids'       => [ $reportIdA, $reportIdB ],
		] );

		$this->assertTrue( $payload[ 'success' ] ?? false );
		$this->assertFalse( $payload[ 'page_reload' ] ?? true );
		$this->assertEmpty( self::con()->db_con->reports->getQuerySelector()->byId( $reportIdA ) );
		$this->assertEmpty( self::con()->db_con->reports->getQuerySelector()->byId( $reportIdB ) );
	}

	public function test_delete_sub_action_requires_valid_nonce_before_deleting_report() :void {
		$reportId = $this->insertReport( 'Nonce Protected Delete' );
		$snapshot = $this->seedActionNonceContext( ReportTableAction::class );
		$this->mergeCurrentRequestTransport( [
			ActionData::FIELD_NONCE => '',
		] );

		try {
			$this->expectException( InvalidActionNonceException::class );
			$this->processor()->processAction( ReportTableAction::SLUG, [
				'sub_action' => 'delete',
				'rids'       => [ $reportId ],
			] );
		}
		finally {
			$this->assertNotEmpty( self::con()->db_con->reports->getQuerySelector()->byId( $reportId ) );
			$this->restoreActionNonceContext( $snapshot );
		}
	}

	public function test_retrieve_table_data_clamps_excessive_length() :void {
		$this->seedReports( 101 );

		$payload = $this->processReportTableAction( [
			'sub_action' => 'retrieve_table_data',
			'table_data' => $this->buildTableDataRequest( '', [
				'length' => 100000,
			] ),
		] );

		$this->assertTrue( $payload[ 'success' ] ?? false );
		$this->assertLessThanOrEqual( 100, \count( $payload[ 'datatable_data' ][ 'data' ] ?? [] ) );
	}

	public function test_retrieve_table_data_clamps_zero_length_to_minimum() :void {
		$this->seedReports( 3 );

		$payload = $this->processReportTableAction( [
			'sub_action' => 'retrieve_table_data',
			'table_data' => $this->buildTableDataRequest( '', [
				'length' => 0,
			] ),
		] );

		$this->assertTrue( $payload[ 'success' ] ?? false );
		$this->assertCount( 1, $payload[ 'datatable_data' ][ 'data' ] ?? [] );
	}

	public function test_retrieve_table_data_rebuilds_canonical_order_contract() :void {
		$newerId = $this->insertReport( 'Alpha Newer', [
			'created_at' => 200,
		] );
		$olderId = $this->insertReport( 'Zulu Older', [
			'created_at' => 100,
		] );

		$tableData = $this->buildTableDataRequest( '', [
			'columns' => [
				[
					'data' => 'title',
				],
			],
			'order'   => [
				[
					'column' => 0,
					'dir'    => 'sideways',
				],
			],
		] );

		$payload = $this->processReportTableAction( [
			'sub_action' => 'retrieve_table_data',
			'table_data' => $tableData,
		] );

		$this->assertTrue( $payload[ 'success' ] ?? false );
		$this->assertSame(
			[ $newerId, $olderId ],
			\array_column( $payload[ 'datatable_data' ][ 'data' ] ?? [], 'rid' )
		);
	}

	private function processor() :ActionProcessor {
		return new ActionProcessor();
	}

	private function processReportTableAction( array $actionData ) :array {
		$this->requireController()->this_req->wp_is_ajax = true;
		return $this->processor()->processAction(
			ReportTableAction::SLUG,
			ActionData::Build( ReportTableAction::class, true, $actionData )
		)->payload();
	}

	private function buildTableDataRequest( string $search = '', array $overrides = [] ) :array {
		$tableData = ( new ForReports() )->buildRaw();
		$tableData[ 'order' ] = \array_values( \array_map(
			static fn( array $order ) :array => [
				'column' => (int)( $order[ 0 ] ?? 0 ),
				'dir'    => (string)( $order[ 1 ] ?? 'desc' ),
			],
			\is_array( $tableData[ 'order' ] ?? null ) ? $tableData[ 'order' ] : []
		) );

		return \array_merge( $tableData, [
			'draw'   => 1,
			'start'  => 0,
			'length' => 25,
			'search' => [
				'value' => $search,
				'regex' => false,
			],
		], $overrides );
	}

	private function seedReports( int $count ) :void {
		for ( $i = 1; $i <= $count; $i++ ) {
			$this->insertReport( 'Report '.$i, [
				'created_at' => $i,
			] );
		}
	}

	private function insertReport( string $title, array $overrides = [] ) :int {
		$dbh = self::con()->db_con->reports;
		$record = $dbh->getRecord();
		$record->type = $overrides[ 'type' ] ?? Constants::REPORT_TYPE_INFO;
		$record->interval_length = $overrides[ 'interval_length' ] ?? 'daily';
		$record->unique_id = $overrides[ 'unique_id' ] ?? \wp_generate_uuid4();
		$record->title = $title;
		$record->content = \array_key_exists( 'content', $overrides )
			? $overrides[ 'content' ]
			: \gzdeflate( '<html><body>'.$title.'</body></html>' );
		$record->protected = $overrides[ 'protected' ] ?? false;
		$record->interval_start_at = $overrides[ 'interval_start_at' ] ?? 100;
		$record->interval_end_at = $overrides[ 'interval_end_at' ] ?? 200;
		$record->created_at = $overrides[ 'created_at' ] ?? ( \time() + \random_int( 1, 100 ) );

		$dbh->getQueryInserter()->insert( $record );

		return (int)$dbh->getQuerySelector()->filterByReportID( $record->unique_id )->first()->id;
	}
}
