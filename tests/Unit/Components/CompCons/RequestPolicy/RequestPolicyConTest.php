<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Components\CompCons\RequestPolicy;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\RequestPolicy\{
	PolicyEvidence,
	RequestPolicyCon,
	RequestPolicyEvaluator
};
use FernleafSystems\Wordpress\Plugin\Shield\Rules\RuleVO;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	UnitTestControllerFactory
};

class RequestPolicyConTest extends BaseUnitTest {

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_legacy_component_startup_does_not_setup_policy_recorder() :void {
		Functions\expect( 'add_action' )->never();
		$opts = new RequestPolicyConOptionsStub( RequestPolicyEvaluator::MODE_LEGACY );
		UnitTestControllerFactory::install( null, null, (object)[
			'opts' => $opts,
		] );

		( new RequestPolicyCon() )->execute();

		$this->assertSame( [ 'request_policy_mode' ], $opts->requestedKeys );
	}

	public function test_legacy_rule_enforcement_does_not_setup_policy_context_or_recorder() :void {
		Functions\expect( 'add_action' )->never();
		$opts = new RequestPolicyConOptionsStub( RequestPolicyEvaluator::MODE_LEGACY );
		UnitTestControllerFactory::install( null, null, (object)[
			'opts' => $opts,
		] );

		$rule = ( new RuleVO() )->applyFromArray( [
			'slug'           => 'legacy/no_context',
			'condition_meta' => [
				'match_category' => 'sql_queries',
			],
		] );

		( new RequestPolicyCon() )->enforceRule( $rule, PolicyEvidence::DETECTOR_EVENT, [] );

		$this->assertSame( [ 'request_policy_mode' ], $opts->requestedKeys );
	}

	public function test_legacy_action_router_guard_returns_false_without_policy_context() :void {
		Functions\expect( 'add_action' )->never();
		$opts = new RequestPolicyConOptionsStub( RequestPolicyEvaluator::MODE_LEGACY );
		UnitTestControllerFactory::install( null, null, (object)[
			'opts' => $opts,
		] );

		$this->assertFalse( ( new RequestPolicyCon() )->isActionRouterIpAllowed() );
		$this->assertSame( [ 'request_policy_mode' ], $opts->requestedKeys );
	}
}

class RequestPolicyConOptionsStub {

	public array $requestedKeys = [];

	private string $mode;

	public function __construct( string $mode ) {
		$this->mode = $mode;
	}

	public function optGet( string $key ) {
		$this->requestedKeys[] = $key;
		return $key === 'request_policy_mode' ? $this->mode : '';
	}
}
