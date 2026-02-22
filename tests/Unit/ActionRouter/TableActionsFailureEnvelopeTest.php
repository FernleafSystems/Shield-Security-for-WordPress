<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\{
	ActivityLogTableAction,
	Investigation\InvestigationTableContract,
	InvestigationTableAction,
	IpRulesTableAction,
	SessionsTableAction,
	TrafficLogTableAction
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class TableActionsFailureEnvelopeTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) => $text );
	}

	public function testSessionsFailureUsesNoPageReload() :void {
		$payload = ( new SessionsTableActionTestDouble( [ 'sub_action' => 'unknown' ] ) )->runExecForTest();

		$this->assertFalse( $payload[ 'success' ] ?? true );
		$this->assertFalse( $payload[ 'page_reload' ] ?? true );
	}

	public function testActivityFailureUsesPageReload() :void {
		$payload = ( new ActivityLogTableActionTestDouble( [ 'sub_action' => 'unknown' ] ) )->runExecForTest();

		$this->assertFalse( $payload[ 'success' ] ?? true );
		$this->assertTrue( $payload[ 'page_reload' ] ?? false );
	}

	public function testTrafficFailureUsesPageReload() :void {
		$payload = ( new TrafficLogTableActionTestDouble( [ 'sub_action' => 'unknown' ] ) )->runExecForTest();

		$this->assertFalse( $payload[ 'success' ] ?? true );
		$this->assertTrue( $payload[ 'page_reload' ] ?? false );
	}

	public function testIpRulesFailureUsesPageReload() :void {
		$payload = ( new IpRulesTableActionTestDouble( [ 'sub_action' => 'unknown' ] ) )->runExecForTest();

		$this->assertFalse( $payload[ 'success' ] ?? true );
		$this->assertTrue( $payload[ 'page_reload' ] ?? false );
	}

	public function testInvestigationFailureIncludesStableErrorCode() :void {
		$payload = ( new InvestigationTableActionTestDouble( [ 'sub_action' => 'unknown' ] ) )->runExecForTest();

		$this->assertFalse( $payload[ 'success' ] ?? true );
		$this->assertTrue( $payload[ 'page_reload' ] ?? false );
		$this->assertSame( 'unsupported_sub_action', $payload[ 'error_code' ] ?? '' );
	}

	public function testScopedActionHandlerMapsContainCallableContracts() :void {
		$cases = [
			[
				'action' => new ActivityLogTableActionTestDouble( [] ),
				'keys'   => [ 'retrieve_table_data', 'get_request_meta' ],
			],
			[
				'action' => new TrafficLogTableActionTestDouble( [] ),
				'keys'   => [ 'retrieve_table_data' ],
			],
			[
				'action' => new SessionsTableActionTestDouble( [] ),
				'keys'   => [ 'retrieve_table_data', 'delete' ],
			],
			[
				'action' => new IpRulesTableActionTestDouble( [] ),
				'keys'   => [ 'retrieve_table_data' ],
			],
			[
				'action' => new InvestigationTableActionTestDouble( [] ),
				'keys'   => [ InvestigationTableContract::SUB_ACTION_RETRIEVE_TABLE_DATA ],
			],
		];

		foreach ( $cases as $case ) {
			$handlerMap = $case[ 'action' ]->getHandlerMapForTest();
			foreach ( $case[ 'keys' ] as $requiredKey ) {
				$this->assertArrayHasKey( $requiredKey, $handlerMap );
				$this->assertIsCallable( $handlerMap[ $requiredKey ] );
			}
		}
	}
}

trait RunsTableActionExecForTest {

	public function runExecForTest() :array {
		$this->exec();
		return $this->response()->payload();
	}

	public function getHandlerMapForTest() :array {
		return $this->getSubActionHandlers();
	}
}

class ActivityLogTableActionTestDouble extends ActivityLogTableAction {

	use RunsTableActionExecForTest;
}

class TrafficLogTableActionTestDouble extends TrafficLogTableAction {

	use RunsTableActionExecForTest;
}

class SessionsTableActionTestDouble extends SessionsTableAction {

	use RunsTableActionExecForTest;
}

class IpRulesTableActionTestDouble extends IpRulesTableAction {

	use RunsTableActionExecForTest;
}

class InvestigationTableActionTestDouble extends InvestigationTableAction {

	use RunsTableActionExecForTest;
}
