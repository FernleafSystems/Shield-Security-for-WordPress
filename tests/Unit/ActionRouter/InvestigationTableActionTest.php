<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Investigation\InvestigationTableContract;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\InvestigationTableAction;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\BaseBuildSearchPanesData;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\Investigation\BaseInvestigationData;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class InvestigationTableActionTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) => $text );
	}

	private function runAction( array $actionData ) :array {
		return ( new InvestigationTableActionUnitTestDouble( $actionData ) )->runExecForTest();
	}

	public function testUnsupportedSubActionReturnsFailurePayload() :void {
		$payload = $this->runAction( [ InvestigationTableContract::REQ_KEY_SUB_ACTION => 'unknown' ] );

		$this->assertFalse( $payload[ 'success' ] ?? true );
		$this->assertTrue( $payload[ 'page_reload' ] ?? false );
		$this->assertArrayHasKey( 'message', $payload );
		$this->assertSame( 'unsupported_sub_action', $payload[ 'error_code' ] ?? '' );
	}

	public function testRetrieveTableDataWithoutRequiredContextKeysReturnsFailurePayload() :void {
		$payload = $this->runAction( [
			InvestigationTableContract::REQ_KEY_SUB_ACTION   => InvestigationTableContract::SUB_ACTION_RETRIEVE_TABLE_DATA,
			InvestigationTableContract::REQ_KEY_TABLE_TYPE   => InvestigationTableContract::TABLE_TYPE_SESSIONS,
			InvestigationTableContract::REQ_KEY_SUBJECT_TYPE => InvestigationTableContract::SUBJECT_TYPE_USER,
		] );

		$this->assertFalse( $payload[ 'success' ] ?? true );
		$this->assertTrue( $payload[ 'page_reload' ] ?? false );
		$this->assertArrayHasKey( 'message', $payload );
		$this->assertSame( 'missing_required_action_data', $payload[ 'error_code' ] ?? '' );
	}

	/**
	 * @dataProvider provideRetrieveTableDataNormalizationCases
	 */
	public function testRetrieveTableDataNormalizationCases( array $actionData ) :void {
		$payload = ( new InvestigationTableActionRetrieveSuccessUnitTestDouble( [
			InvestigationTableContract::REQ_KEY_SUB_ACTION   => InvestigationTableContract::SUB_ACTION_RETRIEVE_TABLE_DATA,
			InvestigationTableContract::REQ_KEY_TABLE_TYPE   => InvestigationTableContract::TABLE_TYPE_SESSIONS,
			InvestigationTableContract::REQ_KEY_SUBJECT_TYPE => InvestigationTableContract::SUBJECT_TYPE_USER,
			InvestigationTableContract::REQ_KEY_SUBJECT_ID   => 42,
		] + $actionData ) )->runExecForTest();

		$this->assertTrue( $payload[ 'success' ] ?? false );
		$this->assertSame( [], $payload[ 'datatable_data' ][ 'table_data' ] ?? null );
	}

	public function provideRetrieveTableDataNormalizationCases() :array {
		return [
			'missing_table_data_key' => [
				'action_data' => [],
			],
			'non_array_table_data' => [
				'action_data' => [
					InvestigationTableContract::REQ_KEY_TABLE_DATA => 'invalid',
				],
			],
		];
	}

	public function testRetrieveTableDataRejectsUnknownTableType() :void {
		$payload = $this->runAction( [
			InvestigationTableContract::REQ_KEY_SUB_ACTION   => InvestigationTableContract::SUB_ACTION_RETRIEVE_TABLE_DATA,
			InvestigationTableContract::REQ_KEY_TABLE_TYPE   => 'not_real',
			InvestigationTableContract::REQ_KEY_SUBJECT_TYPE => InvestigationTableContract::SUBJECT_TYPE_USER,
			InvestigationTableContract::REQ_KEY_SUBJECT_ID   => 1,
			InvestigationTableContract::REQ_KEY_TABLE_DATA   => [],
		] );

		$this->assertFalse( $payload[ 'success' ] ?? true );
		$this->assertTrue( $payload[ 'page_reload' ] ?? false );
		$this->assertArrayHasKey( 'message', $payload );
		$this->assertSame( 'unsupported_table_type', $payload[ 'error_code' ] ?? '' );
	}

	public function testRetrieveTableDataRejectsInvalidSubjectTypeForTable() :void {
		$payload = $this->runAction( [
			InvestigationTableContract::REQ_KEY_SUB_ACTION   => InvestigationTableContract::SUB_ACTION_RETRIEVE_TABLE_DATA,
			InvestigationTableContract::REQ_KEY_TABLE_TYPE   => InvestigationTableContract::TABLE_TYPE_SESSIONS,
			InvestigationTableContract::REQ_KEY_SUBJECT_TYPE => InvestigationTableContract::SUBJECT_TYPE_IP,
			InvestigationTableContract::REQ_KEY_SUBJECT_ID   => '1.2.3.4',
			InvestigationTableContract::REQ_KEY_TABLE_DATA   => [],
		] );

		$this->assertFalse( $payload[ 'success' ] ?? true );
		$this->assertTrue( $payload[ 'page_reload' ] ?? false );
		$this->assertArrayHasKey( 'message', $payload );
		$this->assertSame( 'unsupported_subject_type', $payload[ 'error_code' ] ?? '' );
	}

	public function testRetrieveTableDataRejectsInvalidSubjectIdentifier() :void {
		$payload = $this->runAction( [
			InvestigationTableContract::REQ_KEY_SUB_ACTION   => InvestigationTableContract::SUB_ACTION_RETRIEVE_TABLE_DATA,
			InvestigationTableContract::REQ_KEY_TABLE_TYPE   => InvestigationTableContract::TABLE_TYPE_SESSIONS,
			InvestigationTableContract::REQ_KEY_SUBJECT_TYPE => InvestigationTableContract::SUBJECT_TYPE_USER,
			InvestigationTableContract::REQ_KEY_SUBJECT_ID   => 0,
			InvestigationTableContract::REQ_KEY_TABLE_DATA   => [],
		] );

		$this->assertFalse( $payload[ 'success' ] ?? true );
		$this->assertTrue( $payload[ 'page_reload' ] ?? false );
		$this->assertArrayHasKey( 'message', $payload );
		$this->assertSame( 'invalid_subject_identifier', $payload[ 'error_code' ] ?? '' );
	}

	public function testRetrieveTableDataWithUnavailableBuilderReturnsFailurePayload() :void {
		$payload = ( new InvestigationTableActionUnavailableBuilderUnitTestDouble( [
			InvestigationTableContract::REQ_KEY_SUB_ACTION   => InvestigationTableContract::SUB_ACTION_RETRIEVE_TABLE_DATA,
			InvestigationTableContract::REQ_KEY_TABLE_TYPE   => InvestigationTableContract::TABLE_TYPE_SESSIONS,
			InvestigationTableContract::REQ_KEY_SUBJECT_TYPE => InvestigationTableContract::SUBJECT_TYPE_USER,
			InvestigationTableContract::REQ_KEY_SUBJECT_ID   => 42,
		] ) )->runExecForTest();

		$this->assertFalse( $payload[ 'success' ] ?? true );
		$this->assertTrue( $payload[ 'page_reload' ] ?? false );
		$this->assertArrayHasKey( 'message', $payload );
		$this->assertSame( 'unavailable_builder', $payload[ 'error_code' ] ?? '' );
	}

	/**
	 * @dataProvider provideActivitySubjectSuccessCases
	 */
	public function testRetrieveTableDataAcceptsExpandedActivitySubjects( string $subjectType, $subjectId ) :void {
		$payload = ( new InvestigationTableActionActivityRetrieveSuccessUnitTestDouble( [
			InvestigationTableContract::REQ_KEY_SUB_ACTION   => InvestigationTableContract::SUB_ACTION_RETRIEVE_TABLE_DATA,
			InvestigationTableContract::REQ_KEY_TABLE_TYPE   => InvestigationTableContract::TABLE_TYPE_ACTIVITY,
			InvestigationTableContract::REQ_KEY_SUBJECT_TYPE => $subjectType,
			InvestigationTableContract::REQ_KEY_SUBJECT_ID   => $subjectId,
		], $subjectType, $subjectId ) )->runExecForTest();

		$this->assertTrue( $payload[ 'success' ] ?? false );
		$this->assertSame( [], $payload[ 'datatable_data' ][ 'table_data' ] ?? null );
	}

	public function provideActivitySubjectSuccessCases() :array {
		return [
			'plugin_subject' => [ 'plugin', 'akismet/akismet.php' ],
			'theme_subject'  => [ 'theme', 'twentytwentyfive' ],
			'core_subject'   => [ 'core', 'core' ],
		];
	}

	public function testRetrieveTableDataRejectsUnsupportedPluginSubjectForTrafficTable() :void {
		$payload = $this->runAction( [
			InvestigationTableContract::REQ_KEY_SUB_ACTION   => InvestigationTableContract::SUB_ACTION_RETRIEVE_TABLE_DATA,
			InvestigationTableContract::REQ_KEY_TABLE_TYPE   => InvestigationTableContract::TABLE_TYPE_TRAFFIC,
			InvestigationTableContract::REQ_KEY_SUBJECT_TYPE => InvestigationTableContract::SUBJECT_TYPE_PLUGIN,
			InvestigationTableContract::REQ_KEY_SUBJECT_ID   => 'akismet/akismet.php',
			InvestigationTableContract::REQ_KEY_TABLE_DATA   => [],
		] );

		$this->assertFalse( $payload[ 'success' ] ?? true );
		$this->assertTrue( $payload[ 'page_reload' ] ?? false );
		$this->assertSame( 'unsupported_subject_type', $payload[ 'error_code' ] ?? '' );
	}

	public function testMiswiredHandlerMapReturnsUnsupportedSubActionErrorCode() :void {
		$payload = ( new InvestigationTableActionMiswiredHandlerMapTestDouble( [
			InvestigationTableContract::REQ_KEY_SUB_ACTION => InvestigationTableContract::SUB_ACTION_RETRIEVE_TABLE_DATA,
		] ) )->runExecForTest();

		$this->assertFalse( $payload[ 'success' ] ?? true );
		$this->assertTrue( $payload[ 'page_reload' ] ?? false );
		$this->assertArrayHasKey( 'message', $payload );
		$this->assertSame( 'unsupported_sub_action', $payload[ 'error_code' ] ?? '' );
	}
}

class InvestigationTableActionUnitTestDouble extends InvestigationTableAction {

	public function runExecForTest() :array {
		$this->exec();
		return $this->response()->payload();
	}
}

class InvestigationTableActionMiswiredHandlerMapTestDouble extends InvestigationTableActionUnitTestDouble {

	protected function getSubActionHandlers() :array {
		return [
			InvestigationTableContract::SUB_ACTION_RETRIEVE_TABLE_DATA => '__invalid_investigation_handler_callable__',
		];
	}
}

class InvestigationTableActionUnavailableBuilderUnitTestDouble extends InvestigationTableActionUnitTestDouble {

	protected function normalizeSubjectContext( string $tableType, string $subjectType, $subjectId ) :array {
		return [
			InvestigationTableContract::REQ_KEY_TABLE_TYPE   => 'not_registered_builder_table_type',
			InvestigationTableContract::REQ_KEY_SUBJECT_TYPE => InvestigationTableContract::SUBJECT_TYPE_USER,
			InvestigationTableContract::REQ_KEY_SUBJECT_ID   => 7,
		];
	}
}

class InvestigationTableActionRetrieveSuccessUnitTestDouble extends InvestigationTableActionUnitTestDouble {

	protected function normalizeSubjectContext( string $tableType, string $subjectType, $subjectId ) :array {
		return [
			InvestigationTableContract::REQ_KEY_TABLE_TYPE   => InvestigationTableContract::TABLE_TYPE_SESSIONS,
			InvestigationTableContract::REQ_KEY_SUBJECT_TYPE => InvestigationTableContract::SUBJECT_TYPE_USER,
			InvestigationTableContract::REQ_KEY_SUBJECT_ID   => 7,
		];
	}

	protected function createBuilderForTableType( string $tableType ) :BaseInvestigationData {
		return new InvestigationTableActionEchoTableDataBuilderTestDouble();
	}
}

class InvestigationTableActionActivityRetrieveSuccessUnitTestDouble extends InvestigationTableActionUnitTestDouble {

	private string $subjectType;

	private $subjectId;

	public function __construct( array $actionData, string $subjectType, $subjectId ) {
		parent::__construct( $actionData );
		$this->subjectType = $subjectType;
		$this->subjectId = $subjectId;
	}

	protected function normalizeSubjectContext( string $tableType, string $subjectType, $subjectId ) :array {
		return [
			InvestigationTableContract::REQ_KEY_TABLE_TYPE   => InvestigationTableContract::TABLE_TYPE_ACTIVITY,
			InvestigationTableContract::REQ_KEY_SUBJECT_TYPE => $this->subjectType,
			InvestigationTableContract::REQ_KEY_SUBJECT_ID   => $this->subjectId,
		];
	}

	protected function createBuilderForTableType( string $tableType ) :BaseInvestigationData {
		return new InvestigationTableActionEchoTableDataBuilderTestDouble();
	}
}

class InvestigationTableActionEchoTableDataBuilderTestDouble extends BaseInvestigationData {

	protected function countTotalRecords() :int {
		return 0;
	}

	protected function countTotalRecordsFiltered() :int {
		return 0;
	}

	protected function buildTableRowsFromRawRecords( array $records ) :array {
		return [];
	}

	protected function getSearchPanesDataBuilder() :BaseBuildSearchPanesData {
		return new BaseBuildSearchPanesData();
	}

	protected function getSubjectWheres() :array {
		return [];
	}

	public function build() :array {
		return [
			'table_data' => $this->table_data,
		];
	}
}
