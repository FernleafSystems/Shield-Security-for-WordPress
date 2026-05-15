<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionResponse;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\BaseAction;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\{
	InvalidActionNonceException,
	SecurityAdminRequiredException,
	UserAuthRequiredException
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState,
	UnitTestControllerFactory
};
use FernleafSystems\Wordpress\Services\Core\Users;

class BaseActionRequestPolicyGateTest extends BaseUnitTest {

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) => $text );
		$this->servicesSnapshot = ServicesState::snapshot();
	}

	protected function tearDown() :void {
		ServicesState::restore( $this->servicesSnapshot );
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_auth_checks_still_run_after_policy_allows_ip_guard() :void {
		$this->assertPolicyAllowStillHitsGate(
			BaseActionPolicyGateAuthTestAction::class,
			UserAuthRequiredException::class,
			false,
			false,
			false
		);
	}

	public function test_capability_checks_still_run_after_policy_allows_ip_guard() :void {
		$this->assertPolicyAllowStillHitsGate(
			BaseActionPolicyGateAuthTestAction::class,
			UserAuthRequiredException::class,
			true,
			false,
			false
		);
	}

	public function test_security_admin_checks_still_run_after_policy_allows_ip_guard() :void {
		$this->assertPolicyAllowStillHitsGate(
			BaseActionPolicyGateAuthTestAction::class,
			SecurityAdminRequiredException::class,
			true,
			true,
			false
		);
	}

	public function test_nonce_checks_still_run_after_policy_allows_ip_guard() :void {
		$this->assertPolicyAllowStillHitsGate(
			BaseActionPolicyGateNonceTestAction::class,
			InvalidActionNonceException::class,
			true,
			true,
			true
		);
	}

	private function assertPolicyAllowStillHitsGate(
		string $actionClass,
		string $exceptionClass,
		bool $isLoggedIn,
		bool $canUseAction,
		bool $isSecurityAdmin
	) :void {
		$policy = new BaseActionPolicyGatePolicyStub();
		Functions\when( 'user_can' )->alias( static fn() :bool => $canUseAction );

		UnitTestControllerFactory::install( null, null, (object)[
			'this_req' => (object)[
				'request_bypasses_all_restrictions' => false,
				'is_ip_blocked'                     => true,
				'wp_is_ajax'                        => false,
				'is_security_admin'                 => $isSecurityAdmin,
			],
			'comps'    => (object)[
				'request_policy' => $policy,
			],
			'cfg'      => (object)[
				'properties' => [
					'base_permissions' => 'manage_options',
				],
			],
		] );

		ServicesState::installItems( [
			'service_wpusers' => new BaseActionPolicyGateUsersStub( $isLoggedIn ),
		] );

		try {
			( new $actionClass( [], new ActionResponse() ) )->process();
			$this->fail( sprintf( 'Expected %s after policy allowed the IP guard.', $exceptionClass ) );
		}
		catch ( \Exception $e ) {
			$this->assertInstanceOf( $exceptionClass, $e );
			$this->assertSame( 1, $policy->calls );
		}
	}
}

class BaseActionPolicyGatePolicyStub {

	public int $calls = 0;

	public function isActionRouterIpAllowed() :bool {
		$this->calls++;
		return true;
	}
}

class BaseActionPolicyGateUsersStub extends Users {

	private bool $isLoggedIn;

	public function __construct( bool $isLoggedIn ) {
		$this->isLoggedIn = $isLoggedIn;
	}

	public function isUserLoggedIn() :bool {
		return $this->isLoggedIn;
	}

	public function getCurrentWpUser() {
		return (object)[ 'ID' => 123 ];
	}
}

class BaseActionPolicyGateAuthTestAction extends BaseAction {

	public const SLUG = 'policy_gate_auth_test';

	protected function exec() {
	}

	protected function isNonceVerifyRequired() :bool {
		return false;
	}
}

class BaseActionPolicyGateNonceTestAction extends BaseAction {

	public const SLUG = 'policy_gate_nonce_test';

	protected function exec() {
	}

	protected function verifyNonce() :bool {
		return false;
	}

	protected function isNonceVerifyRequired() :bool {
		return true;
	}
}
