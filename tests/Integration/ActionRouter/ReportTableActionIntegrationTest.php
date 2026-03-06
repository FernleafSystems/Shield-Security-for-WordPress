<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionProcessor,
	Actions\ReportTableAction
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\Constants;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build\ForReports;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class ReportTableActionIntegrationTest extends ShieldIntegrationTestCase {

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

		$payload = $this->processor()->processAction( ReportTableAction::SLUG, [
			'sub_action' => 'retrieve_table_data',
			'table_data' => $this->buildTableDataRequest(),
		] )->payload();

		$this->assertTrue( $payload[ 'success' ] ?? false );
		$this->assertSame( 2, $payload[ 'datatable_data' ][ 'recordsTotal' ] ?? null );
		$this->assertSame( 2, $payload[ 'datatable_data' ][ 'recordsFiltered' ] ?? null );
		$this->assertCount( 2, $payload[ 'datatable_data' ][ 'data' ] ?? [] );
		$this->assertSame(
			[ $betaId, $alphaId ],
			\array_column( $payload[ 'datatable_data' ][ 'data' ] ?? [], 'rid' )
		);

		$searchPayload = $this->processor()->processAction( ReportTableAction::SLUG, [
			'sub_action' => 'retrieve_table_data',
			'table_data' => $this->buildTableDataRequest( 'Alpha' ),
		] )->payload();

		$this->assertTrue( $searchPayload[ 'success' ] ?? false );
		$this->assertSame( 2, $searchPayload[ 'datatable_data' ][ 'recordsTotal' ] ?? null );
		$this->assertSame( 1, $searchPayload[ 'datatable_data' ][ 'recordsFiltered' ] ?? null );
		$this->assertCount( 1, $searchPayload[ 'datatable_data' ][ 'data' ] ?? [] );
		$this->assertSame( $alphaId, $searchPayload[ 'datatable_data' ][ 'data' ][ 0 ][ 'rid' ] ?? null );
	}

	public function test_delete_sub_action_removes_report_without_page_reload() :void {
		$reportId = $this->insertReport( 'Delete Me' );
		$this->assertNotEmpty( self::con()->db_con->reports->getQuerySelector()->byId( $reportId ) );

		$payload = $this->processor()->processAction( ReportTableAction::SLUG, [
			'sub_action' => 'delete',
			'rids'       => [ $reportId ],
		] )->payload();

		$this->assertTrue( $payload[ 'success' ] ?? false );
		$this->assertFalse( $payload[ 'page_reload' ] ?? true );
		$this->assertIsString( $payload[ 'message' ] ?? null );
		$this->assertNotSame( '', (string)( $payload[ 'message' ] ?? '' ) );
		$this->assertEmpty( self::con()->db_con->reports->getQuerySelector()->byId( $reportId ) );
	}

	private function processor() :ActionProcessor {
		return new ActionProcessor();
	}

	private function buildTableDataRequest( string $search = '' ) :array {
		return \array_merge( ( new ForReports() )->buildRaw(), [
			'draw'   => 1,
			'start'  => 0,
			'length' => 25,
			'search' => [
				'value' => $search,
				'regex' => false,
			],
		] );
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
