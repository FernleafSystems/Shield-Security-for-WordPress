<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionRoutingController,
	Constants,
	Actions\Render\BaseRender,
	Utility\ExternalActionTransportPolicy
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class ExternalActionTransportPolicyCoverageTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		Functions\when( 'is_admin' )->justReturn( false );
		Functions\when( 'is_network_admin' )->justReturn( false );
	}

	public function test_registered_render_actions_are_denied_from_external_transports() :void {
		$policy = new ExternalActionTransportPolicy();
		$failures = [];
		$renderActionCount = 0;

		foreach ( Constants::ACTIONS as $actionClass ) {
			if ( !\is_a( $actionClass, BaseRender::class, true )
				 || ( new \ReflectionClass( $actionClass ) )->isAbstract() ) {
				continue;
			}
			$renderActionCount++;

			foreach ( $this->externalTransportTypes() as $transportName => $type ) {
				if ( $policy->isAllowed( $actionClass::SLUG, [], $type ) ) {
					$failures[] = $actionClass::SLUG.':'.$transportName.':slug';
				}
				if ( $policy->isAllowed( $actionClass, [], $type ) ) {
					$failures[] = $actionClass::SLUG.':'.$transportName.':class';
				}
			}
		}

		$this->assertGreaterThan( 100, $renderActionCount );
		$this->assertSame( [], $failures );
	}

	private function externalTransportTypes() :array {
		return [
			'shield' => ActionRoutingController::ACTION_SHIELD,
			'ajax'  => ActionRoutingController::ACTION_AJAX,
			'rest'  => ActionRoutingController::ACTION_REST,
		];
	}
}
