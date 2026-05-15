<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Rules;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses\RequestPolicyGate;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class RequestPolicyGateTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();

		Functions\when( '__' )->returnArg();
	}

	public function test_legacy_responses_parameter_is_required() :void {
		$params = ( new RequestPolicyGate() )->getParamsDef();

		$this->assertArrayHasKey( 'legacy_responses', $params );
		$this->assertArrayNotHasKey( 'default', $params[ 'legacy_responses' ] );
	}
}
