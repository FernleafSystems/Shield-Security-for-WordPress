<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\TableActionBase;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\BaseBuildSearchPanesData;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\BaseBuildTableData;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class TableActionBaseTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) => $text );
	}

	public function testSubActionDispatchesToExpectedHandler() :void {
		$action = new TableActionBaseDispatchTestDouble( [ 'sub_action' => 'handler_one' ] );
		$payload = $action->runExecForTest();

		$this->assertSame( [ 'handler_one' ], $action->invokedHandlers );
		$this->assertTrue( $payload[ 'success' ] ?? false );
		$this->assertArrayHasKey( 'message', $payload );
		$this->assertNotSame( '', (string)( $payload[ 'message' ] ?? '' ) );
	}

	public function testUnsupportedSubActionReturnsFailureEnvelope() :void {
		$action = new TableActionBaseDispatchTestDouble( [ 'sub_action' => 'does_not_exist' ] );
		$payload = $action->runExecForTest();

		$this->assertFalse( $payload[ 'success' ] ?? true );
		$this->assertTrue( $payload[ 'page_reload' ] ?? false );
		$this->assertArrayHasKey( 'message', $payload );
		$this->assertNotSame( '', (string)( $payload[ 'message' ] ?? '' ) );
	}

	public function testNonArrayHandlerResponseReturnsFailureEnvelope() :void {
		$action = new TableActionBaseDispatchTestDouble( [ 'sub_action' => 'handler_non_array' ] );
		$payload = $action->runExecForTest();

		$this->assertFalse( $payload[ 'success' ] ?? true );
		$this->assertTrue( $payload[ 'page_reload' ] ?? false );
		$this->assertArrayHasKey( 'message', $payload );
		$this->assertNotSame( '', (string)( $payload[ 'message' ] ?? '' ) );
	}

	public function testHandlerResponseWithoutSuccessDefaultsToFailureEnvelopeSuccessFlag() :void {
		$action = new TableActionBaseDispatchTestDouble( [ 'sub_action' => 'handler_missing_success' ] );
		$payload = $action->runExecForTest();

		$this->assertFalse( $payload[ 'success' ] ?? true );
		$this->assertSame( 'handler_missing_success_ok', $payload[ 'message' ] ?? '' );
	}

	public function testThrowableFromHandlerReturnsFailureEnvelope() :void {
		$action = new TableActionBaseDispatchTestDouble( [ 'sub_action' => 'handler_type_error' ] );
		$payload = $action->runExecForTest();

		$this->assertFalse( $payload[ 'success' ] ?? true );
		$this->assertTrue( $payload[ 'page_reload' ] ?? false );
		$this->assertNotSame( '', (string)( $payload[ 'message' ] ?? '' ) );
	}

	public function testBuildDatatableDataResponseSetsTableDataAndReturnsEnvelope() :void {
		$action = new TableActionBaseDispatchTestDouble( [] );
		$builder = new TableActionBaseDatatableBuilderTestDouble();
		$payload = $action->buildDatatableDataResponseForTest( $builder, [ 'example' => 'data' ] );

		$this->assertSame( [ 'example' => 'data' ], $builder->table_data );
		$this->assertSame( [
			'success'        => true,
			'datatable_data' => [
				'builder_marker' => 'datatable_builder_test',
				'table_data'     => [ 'example' => 'data' ],
			],
		], $payload );
	}

	public function testBuildRetrieveTableDataResponseUsesDefaultKeyAndReturnsEnvelope() :void {
		$action = new TableActionBaseDispatchTestDouble( [ 'table_data' => [ 'example' => 'data' ] ] );
		$builder = new TableActionBaseDatatableBuilderTestDouble();
		$payload = $action->buildRetrieveTableDataResponseForTest( $builder );

		$this->assertSame( [ 'example' => 'data' ], $builder->table_data );
		$this->assertSame( [
			'success'        => true,
			'datatable_data' => [
				'builder_marker' => 'datatable_builder_test',
				'table_data'     => [ 'example' => 'data' ],
			],
		], $payload );
	}

	public function testBuildRetrieveTableDataResponseSupportsKeyOverride() :void {
		$action = new TableActionBaseDispatchTestDouble( [ 'custom_table_data' => [ 'override' => true ] ] );
		$builder = new TableActionBaseDatatableBuilderTestDouble();
		$payload = $action->buildRetrieveTableDataResponseForTest( $builder, 'custom_table_data' );

		$this->assertSame( [ 'override' => true ], $builder->table_data );
		$this->assertSame( [
			'success'        => true,
			'datatable_data' => [
				'builder_marker' => 'datatable_builder_test',
				'table_data'     => [ 'override' => true ],
			],
		], $payload );
	}

	public function testBuildRetrieveTableDataResponseNormalizesNonArrayCustomKeyValue() :void {
		$action = new TableActionBaseDispatchTestDouble( [ 'custom_table_data' => 'invalid' ] );
		$builder = new TableActionBaseDatatableBuilderTestDouble();
		$payload = $action->buildRetrieveTableDataResponseForTest( $builder, 'custom_table_data' );

		$this->assertSame( [], $builder->table_data );
		$this->assertSame( [
			'success'        => true,
			'datatable_data' => [
				'builder_marker' => 'datatable_builder_test',
				'table_data'     => [],
			],
		], $payload );
	}

	public function testGetTableDataFromActionDataReturnsArrayInput() :void {
		$action = new TableActionBaseDispatchTestDouble( [ 'table_data' => [ 'example' => 'data' ] ] );

		$this->assertSame( [ 'example' => 'data' ], $action->getTableDataFromActionDataForTest() );
	}

	public function testGetTableDataFromActionDataReturnsEmptyArrayWhenKeyMissing() :void {
		$action = new TableActionBaseDispatchTestDouble( [] );

		$this->assertSame( [], $action->getTableDataFromActionDataForTest() );
	}

	public function testGetTableDataFromActionDataReturnsEmptyArrayWhenValueIsNotArray() :void {
		$action = new TableActionBaseDispatchTestDouble( [ 'table_data' => 'invalid' ] );

		$this->assertSame( [], $action->getTableDataFromActionDataForTest() );
	}

	public function testGetTableDataFromActionDataSupportsKeyOverride() :void {
		$action = new TableActionBaseDispatchTestDouble( [ 'custom_table_data' => [ 'override' => true ] ] );

		$this->assertSame( [ 'override' => true ], $action->getTableDataFromActionDataForTest( 'custom_table_data' ) );
	}

	public function testRequiredKeyMapMissingKeyReturnsFailureEnvelope() :void {
		$action = new TableActionBaseDispatchTestDouble( [ 'sub_action' => 'handler_requires_key' ] );
		$payload = $action->runExecForTest();

		$this->assertFalse( $payload[ 'success' ] ?? true );
		$this->assertTrue( $payload[ 'page_reload' ] ?? false );
		$this->assertArrayHasKey( 'message', $payload );
		$this->assertNotSame( '', (string)( $payload[ 'message' ] ?? '' ) );
	}

	public function testRequiredKeyMapPresentKeyAllowsHandlerExecution() :void {
		$action = new TableActionBaseDispatchTestDouble( [
			'sub_action'    => 'handler_requires_key',
			'required_key'  => 'ok',
		] );
		$payload = $action->runExecForTest();

		$this->assertSame( [ 'handler_requires_key' ], $action->invokedHandlers );
		$this->assertTrue( $payload[ 'success' ] ?? false );
		$this->assertArrayHasKey( 'message', $payload );
		$this->assertNotSame( '', (string)( $payload[ 'message' ] ?? '' ) );
	}

	public function testBuildUnsupportedSubActionMessageReturnsNonEmptyString() :void {
		$action = new TableActionBaseDispatchTestDouble( [] );
		$message = $action->buildUnsupportedSubActionMessageForTest( 'Activity Log', 'unknown_sub_action' );

		$this->assertIsString( $message );
		$this->assertNotSame( '', $message );
	}
}

class TableActionBaseDispatchTestDouble extends TableActionBase {

	public const SLUG = 'test_table_action_base_dispatch';

	public array $invokedHandlers = [];

	public function runExecForTest() :array {
		$this->exec();
		return $this->response()->payload();
	}

	protected function getSubActionHandlers() :array {
		return [
			'handler_one'          => fn() => $this->handlerOne(),
			'handler_non_array'    => fn() => $this->handlerNonArray(),
			'handler_missing_success' => fn() => $this->handlerMissingSuccess(),
			'handler_type_error'   => fn() => $this->handlerTypeError(),
			'handler_requires_key' => fn() => $this->handlerRequiresKey(),
		];
	}

	protected function getSubActionRequiredDataKeysMap() :array {
		return [
			'handler_requires_key' => [ 'required_key' ],
		];
	}

	protected function getUnsupportedSubActionMessage( string $subAction ) :string {
		return 'Unsupported sub_action for test table: '.$subAction;
	}

	protected function handlerOne() :array {
		$this->invokedHandlers[] = 'handler_one';
		return [
			'success' => true,
			'message' => 'handler_one_ok',
		];
	}

	protected function handlerNonArray() {
		return 'not-an-array';
	}

	protected function handlerMissingSuccess() :array {
		return [
			'message' => 'handler_missing_success_ok',
		];
	}

	protected function handlerTypeError() :array {
		return 'not-an-array';
	}

	protected function handlerRequiresKey() :array {
		$this->invokedHandlers[] = 'handler_requires_key';
		return [
			'success' => true,
			'message' => 'handler_requires_key_ok',
		];
	}

	public function buildDatatableDataResponseForTest( BaseBuildTableData $builder, array $tableData ) :array {
		return $this->buildDatatableDataResponse( $builder, $tableData );
	}

	public function buildRetrieveTableDataResponseForTest( BaseBuildTableData $builder, string $tableDataKey = 'table_data' ) :array {
		return $this->buildRetrieveTableDataResponse( $builder, $tableDataKey );
	}

	public function getTableDataFromActionDataForTest( string $key = 'table_data' ) :array {
		return $this->getTableDataFromActionData( $key );
	}

	public function buildUnsupportedSubActionMessageForTest( string $tableLabel, string $subAction ) :string {
		return $this->buildUnsupportedSubActionMessage( $tableLabel, $subAction );
	}
}

class TableActionBaseDatatableBuilderTestDouble extends BaseBuildTableData {

	public array $table_data = [];

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

	public function build() :array {
		return [
			'builder_marker' => 'datatable_builder_test',
			'table_data'     => $this->table_data,
		];
	}
}
