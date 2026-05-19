<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\UserManagement;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Lib\Password\{
	QueryUserPasswordExpired,
	UserPasswordHandler
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\RuntimeTestState;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Support\CurrentRequestFixture;

class UserPasswordPolicyIntegrationTest extends ShieldIntegrationTestCase {

	use CurrentRequestFixture;

	private const PASSWORD_HOOKS = [
		'after_password_reset',
		'registration_errors',
		'user_profile_update_errors',
		'validate_password_reset',
		'wp_loaded',
		'wp_login',
		'set_logged_in_cookie',
	];

	private array $optionSnapshot = [];

	private array $requestSnapshot = [];

	public function set_up() {
		parent::set_up();

		$this->requireDb( 'ips' );
		$this->requireDb( 'req_logs' );
		$this->requireDb( 'user_meta' );
		$this->optionSnapshot = $this->snapshotSelectedOptions( [
			'enable_password_policies',
			'pass_min_strength',
			'pass_prevent_pwned',
			'pass_expire',
			'pass_force_existing',
		] );
		$this->requestSnapshot = $this->snapshotCurrentRequestState();
	}

	public function tear_down() {
		$this->restoreCurrentRequestState( $this->requestSnapshot );
		$this->restoreSelectedOptions( $this->optionSnapshot );
		\wp_set_current_user( 0 );

		parent::tear_down();
	}

	public function test_minimum_strength_blocks_weak_password_and_fires_policy_event() :void {
		$this->configurePasswordPolicies( [
			'pass_min_strength'  => 4,
			'pass_prevent_pwned' => 'N',
		] );
		$this->captureShieldEvents();

		$errors = $this->passwordErrorsThroughRegisteredHandler( 'password' );

		$this->assertTrue( $errors->has_errors() );
		$this->assertSame( 'shield_password_policy', $errors->get_error_code() );
		$this->assertCount( 1, $this->getCapturedEventsByKey( 'password_policy_block' ) );
	}

	public function test_disabled_password_policies_do_not_register_blocking_checks() :void {
		$this->configurePasswordPolicies( [
			'enable_password_policies' => 'N',
			'pass_min_strength'        => 4,
			'pass_prevent_pwned'       => 'Y',
		] );
		$this->captureShieldEvents();

		$errors = $this->passwordErrorsThroughRegisteredHandler( 'password' );

		$this->assertFalse( $errors->has_errors() );
		$this->assertSame( [], $this->getCapturedEventsByKey( 'password_policy_block' ) );
	}

	public function test_pwned_password_match_uses_local_range_double_and_blocks() :void {
		$password = 'CorrectHorseBatteryStaple!2026Shield';
		$this->configurePasswordPolicies( [
			'pass_min_strength'  => 0,
			'pass_prevent_pwned' => 'Y',
		] );
		$this->captureShieldEvents();

		$rangeWasCalled = false;
		$errors = $this->withPwnedRangeResponse( $password, 17, $rangeWasCalled, function () use ( $password ) {
			return $this->passwordErrorsThroughRegisteredHandler( $password );
		} );

		$this->assertTrue( $rangeWasCalled );
		$this->assertTrue( $errors->has_errors() );
		$this->assertSame( 'shield_password_policy', $errors->get_error_code() );
		$this->assertCount( 1, $this->getCapturedEventsByKey( 'password_policy_block' ) );
	}

	public function test_pwned_range_failure_does_not_block_password() :void {
		$password = 'CorrectHorseBatteryStaple!2026Shield';
		$this->configurePasswordPolicies( [
			'pass_min_strength'  => 0,
			'pass_prevent_pwned' => 'Y',
		] );
		$this->captureShieldEvents();

		$rangeWasCalled = false;
		$errors = $this->withPwnedRangeFailure( $rangeWasCalled, function () use ( $password ) {
			return $this->passwordErrorsThroughRegisteredHandler( $password );
		} );

		$this->assertTrue( $rangeWasCalled );
		$this->assertFalse( $errors->has_errors() );
		$this->assertSame( [], $this->getCapturedEventsByKey( 'password_policy_block' ) );
	}

	public function test_login_capture_records_failed_check_and_clears_after_passing_check() :void {
		$user = $this->createPasswordUser();
		\wp_set_current_user( (int)$user->ID );
		$this->configurePasswordPolicies( [
			'pass_min_strength'  => 4,
			'pass_prevent_pwned' => 'N',
		] );

		$this->applyPasswordRequest( 'password' );
		( new UserPasswordHandler() )->onWpLogin( $user->user_login, $user );
		$failedMeta = $this->requireController()->user_metas->for( $user );

		$this->assertGreaterThan( 0, $failedMeta->pass_check_failed_at );

		$this->applyPasswordRequest( 'CorrectHorseBatteryStaple!2026Shield' );
		( new UserPasswordHandler() )->onWpLogin( $user->user_login, $user );
		$passedMeta = $this->requireController()->user_metas->for( $user );

		$this->assertSame( 0, $passedMeta->pass_check_failed_at );
		$this->assertGreaterThan( 0, $passedMeta->record->pass_started_at );
		$this->assertNotEmpty( $passedMeta->pass_hash );
	}

	public function test_password_expiry_query_and_ajax_event_path_are_local_contracts() :void {
		$user = $this->createPasswordUser();
		\wp_set_current_user( (int)$user->ID );
		$this->configurePasswordPolicies( [
			'pass_expire' => 1,
		] );

		$meta = $this->requireController()->user_metas->for( $user );
		$meta->record->pass_started_at = \time() - ( 2*\DAY_IN_SECONDS );

		$this->assertTrue( ( new QueryUserPasswordExpired() )->check( $user ) );

		$this->captureShieldEvents();
		$ajaxFilter = static fn() :bool => true;
		\add_filter( 'wp_doing_ajax', $ajaxFilter );
		try {
			$method = new \ReflectionMethod( UserPasswordHandler::class, 'processExpiredPassword' );
			$method->setAccessible( true );
			$method->invoke( new UserPasswordHandler() );
		}
		finally {
			\remove_filter( 'wp_doing_ajax', $ajaxFilter );
		}

		$events = $this->getCapturedEventsByKey( 'password_expired' );
		$this->assertCount( 1, $events );
		$this->assertSame( $user->user_login, $events[ 0 ][ 'meta' ][ 'audit_params' ][ 'user_login' ] ?? '' );
	}

	private function configurePasswordPolicies( array $options = [] ) :void {
		$this->enablePremiumCapabilities( [ 'user_password_policies' ] );
		RuntimeTestState::restoreOptions( \array_merge( [
			'enable_password_policies' => 'Y',
			'pass_min_strength'        => 0,
			'pass_prevent_pwned'       => 'N',
			'pass_expire'              => 0,
			'pass_force_existing'      => 'N',
		], $options ) );
		$this->requireController()->this_req->request_bypasses_all_restrictions = false;
	}

	private function passwordErrorsThroughRegisteredHandler( string $password ) :\WP_Error {
		$this->applyPasswordRequest( $password );
		$snapshot = $this->snapshotHooks( self::PASSWORD_HOOKS );
		try {
			( new UserPasswordHandler() )->execute();
			return \apply_filters( 'validate_password_reset', new \WP_Error() );
		}
		finally {
			$this->restoreHooks( $snapshot );
		}
	}

	private function applyPasswordRequest( string $password ) :void {
		$this->applyCurrentRequestState(
			[
				'REQUEST_METHOD' => 'POST',
				'REQUEST_URI'    => '/wp-login.php',
			],
			[],
			[
				'pwd' => $password,
			],
			[
				'path'                     => '/wp-login.php',
				'request_bypasses_all_restrictions' => false,
			]
		);
	}

	private function withPwnedRangeResponse( string $password, int $count, bool &$rangeWasCalled, callable $callback ) :\WP_Error {
		$sha1 = \strtoupper( \hash( 'sha1', $password ) );
		$prefix = \substr( $sha1, 0, 5 );
		$suffix = \substr( $sha1, 5 );
		$filter = function ( $preempt, $args, $url ) use ( &$rangeWasCalled, $prefix, $suffix, $count ) {
			$rangeWasCalled = true;
			$this->assertStringEndsWith( '/'.$prefix, (string)$url );
			return [
				'headers'  => [],
				'body'     => $suffix.':'.$count."\r\n",
				'response' => [
					'code'    => 200,
					'message' => 'OK',
				],
				'cookies'  => [],
				'filename' => null,
			];
		};

		\add_filter( 'pre_http_request', $filter, 10, 3 );
		try {
			return $callback();
		}
		finally {
			\remove_filter( 'pre_http_request', $filter, 10 );
		}
	}

	private function withPwnedRangeFailure( bool &$rangeWasCalled, callable $callback ) :\WP_Error {
		$filter = function () use ( &$rangeWasCalled ) {
			$rangeWasCalled = true;
			return new \WP_Error( 'hibp_unavailable', 'Local HIBP double unavailable.' );
		};

		\add_filter( 'pre_http_request', $filter, 10, 3 );
		try {
			return $callback();
		}
		finally {
			\remove_filter( 'pre_http_request', $filter, 10 );
		}
	}

	private function createPasswordUser() :\WP_User {
		$userID = self::factory()->user->create( [
			'role'       => 'administrator',
			'user_login' => 'shield-policy-'.\wp_generate_uuid4(),
			'user_pass'  => 'vLC4#g7P8!zQ2mR9$2026',
		] );
		if ( \is_wp_error( $userID ) ) {
			$this->fail( $userID->get_error_code() );
		}
		$user = \get_user_by( 'id', $userID );
		$this->assertInstanceOf( \WP_User::class, $user );
		return $user;
	}

	private function snapshotHooks( array $hooks ) :array {
		$snapshot = [];
		foreach ( $hooks as $hook ) {
			$snapshot[ $hook ] = \array_key_exists( $hook, $GLOBALS[ 'wp_filter' ] ?? [] )
				? clone $GLOBALS[ 'wp_filter' ][ $hook ]
				: null;
		}
		return $snapshot;
	}

	private function restoreHooks( array $snapshot ) :void {
		foreach ( $snapshot as $hook => $filter ) {
			if ( $filter === null ) {
				unset( $GLOBALS[ 'wp_filter' ][ $hook ] );
			}
			else {
				$GLOBALS[ 'wp_filter' ][ $hook ] = $filter;
			}
		}
	}
}
