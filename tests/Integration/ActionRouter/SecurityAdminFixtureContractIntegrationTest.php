<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionRoutingController;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\{
	SecurityAdminAuthClear,
	SecurityAdminCheck,
	SecurityAdminLogin
};
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Opts\HandleOptionsSaveRequest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\ActionRouter\RawOptionStoreSnapshot;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\ActionRouter\SecurityAdminFixtureBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\RuntimeTestState;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter\Support\ActionRequestNonceFixture;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Services\Services;

class SecurityAdminFixtureContractIntegrationTest extends ShieldIntegrationTestCase {

	use ActionRequestNonceFixture;

	public function test_fixture_contract_owns_scenarios_and_cleanup_restores_raw_option_stores() :void {
		$this->loginAsAdministrator();
		$rawStores = new RawOptionStoreSnapshot();
		$originalStores = $rawStores->snapshot();
		$builder = new SecurityAdminFixtureBuilder();
		$base = $builder->inspect();
		$originalOptions = RuntimeTestState::snapshotOptions( $base[ 'option_keys' ] );

		try {
			foreach ( [
				SecurityAdminFixtureBuilder::SCENARIO_PIN_UNSET,
				SecurityAdminFixtureBuilder::SCENARIO_LOCKED,
				SecurityAdminFixtureBuilder::SCENARIO_ACTIVE_SESSION,
				SecurityAdminFixtureBuilder::SCENARIO_EXPIRED_SESSION,
				SecurityAdminFixtureBuilder::SCENARIO_DIRECT_DISABLE_READY,
				SecurityAdminFixtureBuilder::SCENARIO_EMAIL_OVERRIDE_ENABLED,
				SecurityAdminFixtureBuilder::SCENARIO_EMAIL_OVERRIDE_DISABLED,
				SecurityAdminFixtureBuilder::SCENARIO_RESTRICTION_ZONES_LOCKED,
				SecurityAdminFixtureBuilder::SCENARIO_RESTRICTION_ZONES_ACTIVE_ADMIN,
				SecurityAdminFixtureBuilder::SCENARIO_PERSISTENT_ADMIN,
			] as $scenario ) {
				$result = $builder->seed( $scenario );

				try {
					$contract = $result[ 'contract' ];
					$expected = $contract[ 'expected' ];
					$this->assertSame( $scenario, $contract[ 'scenario' ] );
					$this->assertContains( $scenario, $contract[ 'scenarios' ] );
					$this->assertSame( 'zones', $contract[ 'routes' ][ 'configure' ][ 'nav' ] );
					$this->assertSame( 'secadmin', $contract[ 'configure_focus' ][ 'zone_key' ] );
					$this->assertSame( 'secadmin_enabled', $contract[ 'configure_focus' ][ 'row_key' ] );
					$this->assertSame( 'admin_access_key', $contract[ 'configure_focus' ][ 'config_item' ] );
					$this->assertSame( 'mod_options_save', $contract[ 'action_slugs' ][ 'module_options_save' ] );
					$this->assertSame( 'sec_admin_login', $contract[ 'action_slugs' ][ 'sec_admin_login' ] );
					$this->assertSame( 'sec_admin_check', $contract[ 'action_slugs' ][ 'sec_admin_check' ] );
					$this->assertSame( 'sec_admin_auth_clear', $contract[ 'action_slugs' ][ 'sec_admin_auth_clear' ] );
					$this->assertSame( 'sec_admin_login', $contract[ 'boundary_action_slugs' ][ 'sec_admin_login' ] );
					$this->assertSame( 'sec_admin_check', $contract[ 'boundary_action_slugs' ][ 'sec_admin_check' ] );
					$this->assertSame( 'sec_admin_auth_clear', $contract[ 'boundary_action_slugs' ][ 'sec_admin_auth_clear' ] );
					$this->assertSame( 'secadmin_remove_confirm', $contract[ 'boundary_action_slugs' ][ 'secadmin_remove_confirm' ] );
					$this->assertSame( 'req_email_remove', $contract[ 'boundary_action_slugs' ][ 'req_email_remove' ] );
					$this->assertSame( 'secadmin_remove_confirm', $contract[ 'boundary_actions' ][ 'direct_disable' ][ 'slug' ] );
					$this->assertSame( 'req_email_remove', $contract[ 'boundary_actions' ][ 'email_override' ][ 'slug' ] );
					$this->assertSame( 'admin_plugin_page_security_admin_restricted', $contract[ 'render_slugs' ][ 'restricted_page' ] );
					$this->assertSame( 'render_form_security_admin_loginbox', $contract[ 'render_slugs' ][ 'login_box' ] );

					$inspection = $builder->inspect( $result[ 'state' ] );
					$this->assertTrue( $inspection[ 'fixture_state_present' ] );
					$this->assertSame( $expected[ 'enabled' ], $inspection[ 'current' ][ 'enabled' ] );
					$this->assertSame( $expected[ 'session_active' ], $inspection[ 'current' ][ 'session_active' ] );
					$this->assertSame( $expected[ 'registered_sec_admin' ], $inspection[ 'current' ][ 'registered_sec_admin' ] );
					$this->assertSame( $expected[ 'currently_sec_admin' ], $inspection[ 'current' ][ 'currently_sec_admin' ] );
					$this->assertSame( $expected[ 'pin_hash_format' ], $inspection[ 'current' ][ 'admin_access_key_hash_format' ] );
					if ( $expected[ 'time_remaining_is_zero' ] ) {
						$this->assertSame( 0, $inspection[ 'current' ][ 'time_remaining' ] );
					}
					if ( $scenario === SecurityAdminFixtureBuilder::SCENARIO_EXPIRED_SESSION ) {
						$this->assertGreaterThan( 0, $inspection[ 'current' ][ 'secadmin_at' ] );
					}
				}
				finally {
					$builder->cleanup( $result[ 'state' ] );
				}

				$this->assertSame( $originalOptions, RuntimeTestState::snapshotOptions( $base[ 'option_keys' ] ) );
				$this->assertSame( $originalStores, $rawStores->snapshot() );
			}
		}
		finally {
			$rawStores->restore( $originalStores, 'Security Admin fixture contract test' );
		}
	}

	public function test_cleanup_restores_existing_session_and_request_security_admin_state() :void {
		$userId = $this->loginAsAdministrator();
		$manager = \WP_Session_Tokens::get_instance( $userId );
		$expiration = Services::Request()->ts() + \DAY_IN_SECONDS;
		$token = $manager->create( $expiration );
		$cookieName = \defined( 'LOGGED_IN_COOKIE' ) ? \LOGGED_IN_COOKIE : '';
		$originalCookieWasSet = $cookieName !== '' && \array_key_exists( $cookieName, $_COOKIE );
		$originalCookie = $originalCookieWasSet ? (string)$_COOKIE[ $cookieName ] : null;
		$originalRequestSecurityAdmin = (bool)$this->requireController()->this_req->is_security_admin;
		$sentinelSecAdminAt = Services::Request()->ts() - 10;
		$builder = new SecurityAdminFixtureBuilder();
		$result = null;

		try {
			if ( $cookieName !== '' ) {
				$_COOKIE[ $cookieName ] = \wp_generate_auth_cookie( $userId, $expiration, 'logged_in', $token );
			}
			$this->writeSessionSecAdminAt( $userId, $token, $sentinelSecAdminAt );
			$this->requireController()->this_req->is_security_admin = true;
			unset( $this->requireController()->this_req->session );

			$result = $builder->seed( SecurityAdminFixtureBuilder::SCENARIO_LOCKED );
			$seeded = $builder->inspect( $result[ 'state' ] );
			$this->assertSame( 0, $seeded[ 'current' ][ 'secadmin_at' ] );
			$this->assertFalse( $seeded[ 'current' ][ 'this_req_is_security_admin' ] );

			$builder->cleanup( $result[ 'state' ] );
			$result = null;

			$this->assertSame( $sentinelSecAdminAt, $this->sessionSecAdminAt( $userId, $token ) );
			$this->assertTrue( (bool)$this->requireController()->this_req->is_security_admin );
		}
		finally {
			if ( \is_array( $result ) ) {
				$builder->cleanup( $result[ 'state' ] );
			}
			$manager->destroy( $token );
			$this->restoreAuthCookie( $cookieName, $originalCookieWasSet, $originalCookie );
			$this->requireController()->this_req->is_security_admin = $originalRequestSecurityAdmin;
			unset( $this->requireController()->this_req->session );
		}
	}

	public function test_fixture_writes_security_admin_state_to_admin_auth_session_before_logged_in_session() :void {
		$userId = $this->loginAsAdministrator();
		$manager = \WP_Session_Tokens::get_instance( $userId );
		$expiration = Services::Request()->ts() + \DAY_IN_SECONDS;
		$loggedInToken = $manager->create( $expiration );
		$adminToken = $manager->create( $expiration );
		$loggedInCookieName = \defined( 'LOGGED_IN_COOKIE' ) ? \LOGGED_IN_COOKIE : '';
		$adminCookieName = \defined( 'SECURE_AUTH_COOKIE' ) ? \SECURE_AUTH_COOKIE : '';
		$loggedInCookieWasSet = $loggedInCookieName !== '' && \array_key_exists( $loggedInCookieName, $_COOKIE );
		$adminCookieWasSet = $adminCookieName !== '' && \array_key_exists( $adminCookieName, $_COOKIE );
		$loggedInCookie = $loggedInCookieWasSet ? (string)$_COOKIE[ $loggedInCookieName ] : null;
		$adminCookie = $adminCookieWasSet ? (string)$_COOKIE[ $adminCookieName ] : null;
		$builder = new SecurityAdminFixtureBuilder();
		$result = null;

		if ( $loggedInCookieName === '' || $adminCookieName === '' ) {
			$this->markTestSkipped( 'WordPress auth cookie constants are unavailable.' );
		}

		try {
			$_COOKIE[ $loggedInCookieName ] = \wp_generate_auth_cookie( $userId, $expiration, 'logged_in', $loggedInToken );
			$_COOKIE[ $adminCookieName ] = \wp_generate_auth_cookie( $userId, $expiration, 'secure_auth', $adminToken );
			$this->writeSessionSecAdminAt( $userId, $loggedInToken, 0 );
			$this->writeSessionSecAdminAt( $userId, $adminToken, 0 );
			unset( $this->requireController()->this_req->session );

			$result = $builder->seed( SecurityAdminFixtureBuilder::SCENARIO_DIRECT_DISABLE_READY );

			$this->assertGreaterThan( 0, $this->sessionSecAdminAt( $userId, $adminToken ) );
			$this->assertSame( 0, $this->sessionSecAdminAt( $userId, $loggedInToken ) );

			$builder->cleanup( $result[ 'state' ] );
			$result = null;

			$this->assertSame( 0, $this->sessionSecAdminAt( $userId, $adminToken ) );
			$this->assertSame( 0, $this->sessionSecAdminAt( $userId, $loggedInToken ) );
		}
		finally {
			if ( \is_array( $result ) ) {
				$builder->cleanup( $result[ 'state' ] );
			}
			$manager->destroy( $loggedInToken );
			$manager->destroy( $adminToken );
			$this->restoreAuthCookie( $loggedInCookieName, $loggedInCookieWasSet, $loggedInCookie );
			$this->restoreAuthCookie( $adminCookieName, $adminCookieWasSet, $adminCookie );
			unset( $this->requireController()->this_req->session );
		}
	}

	public function test_real_pin_save_login_and_migration_restore_cleanly() :void {
		$this->loginAsAdministrator();
		$builder = new SecurityAdminFixtureBuilder();
		$result = $builder->seed( SecurityAdminFixtureBuilder::SCENARIO_PIN_UNSET );

		try {
			$contract = $result[ 'contract' ];
			$this->savePinThroughOptionsOwner( $contract[ 'pins' ][ 'new' ] );
			$afterSave = $builder->inspect( $result[ 'state' ] );

			$this->assertTrue( $afterSave[ 'current' ][ 'enabled' ] );
			$this->assertSame( 'md5', $afterSave[ 'current' ][ 'admin_access_key_hash_format' ] );
			$this->assertFalse( $afterSave[ 'current' ][ 'session_active' ] );

			$payload = $this->runAction( SecurityAdminLogin::SLUG, [
				'sec_admin_key' => $contract[ 'pins' ][ 'new' ],
			] );
			$this->assertTrue( $payload[ 'success' ] );
			$this->assertTrue( $payload[ 'page_reload' ] );

			$afterLogin = $builder->inspect( $result[ 'state' ] );
			$this->assertTrue( $afterLogin[ 'current' ][ 'session_active' ] );
			$this->assertGreaterThan( 0, $afterLogin[ 'current' ][ 'time_remaining' ] );
			$this->assertSame( 'wp_hash', $afterLogin[ 'current' ][ 'admin_access_key_hash_format' ] );
		}
		finally {
			$builder->cleanup( $result[ 'state' ] );
		}
	}

	public function test_invalid_pin_timeout_and_auth_clear_contracts_preserve_session_boundaries() :void {
		$this->loginAsAdministrator();
		$builder = new SecurityAdminFixtureBuilder();

		$locked = $builder->seed( SecurityAdminFixtureBuilder::SCENARIO_LOCKED );
		try {
			$payload = $this->runAction( SecurityAdminLogin::SLUG, [
				'sec_admin_key' => $locked[ 'contract' ][ 'pins' ][ 'invalid' ],
			] );
			$this->assertFalse( $payload[ 'success' ] );

			$inspection = $builder->inspect( $locked[ 'state' ] );
			$this->assertFalse( $inspection[ 'current' ][ 'session_active' ] );
			$this->assertSame( 0, $inspection[ 'current' ][ 'secadmin_at' ] );
			$this->assertFalse( $inspection[ 'current' ][ 'this_req_is_security_admin' ] );
		}
		finally {
			$builder->cleanup( $locked[ 'state' ] );
		}

		$expired = $builder->seed( SecurityAdminFixtureBuilder::SCENARIO_EXPIRED_SESSION );
		try {
			$payload = $this->runAction( SecurityAdminCheck::SLUG );
			$this->assertFalse( $payload[ 'success' ] );
			$this->assertSame( 0, $payload[ 'time_remaining' ] );

			$inspection = $builder->inspect( $expired[ 'state' ] );
			$this->assertFalse( $inspection[ 'current' ][ 'session_active' ] );
			$this->assertSame( 0, $inspection[ 'current' ][ 'time_remaining' ] );
		}
		finally {
			$builder->cleanup( $expired[ 'state' ] );
		}

		$active = $builder->seed( SecurityAdminFixtureBuilder::SCENARIO_ACTIVE_SESSION );
		try {
			$before = $builder->inspect( $active[ 'state' ] );
			$this->assertTrue( $before[ 'current' ][ 'session_active' ] );

			$payload = $this->runAction( SecurityAdminAuthClear::SLUG );
			$this->assertTrue( $payload[ 'success' ] );

			$after = $builder->inspect( $active[ 'state' ] );
			$this->assertFalse( $after[ 'current' ][ 'session_active' ] );
			$this->assertSame( 0, $after[ 'current' ][ 'secadmin_at' ] );
			$this->assertFalse( $after[ 'current' ][ 'this_req_is_security_admin' ] );
		}
		finally {
			$builder->cleanup( $active[ 'state' ] );
		}
	}

	private function savePinThroughOptionsOwner( string $pin ) :void {
		$snapshot = $this->snapshotCurrentRequestState();
		try {
			$this->applyCurrentShieldAjaxRequest( [
				'form_params' => [
					'all_opts_keys'            => 'admin_access_key',
					'admin_access_key'         => $pin,
					'admin_access_key_confirm' => $pin,
				],
			], true );

			$bypass = static fn() :bool => true;
			\add_filter( $this->requireController()->prefix( 'bypass_is_plugin_admin' ), $bypass, 1000 );
			try {
				$this->assertTrue( ( new HandleOptionsSaveRequest() )->handleSave() );
				RuntimeTestState::resetOptionsRuntimeCache();
			}
			finally {
				\remove_filter( $this->requireController()->prefix( 'bypass_is_plugin_admin' ), $bypass, 1000 );
			}
		}
		finally {
			$this->restoreCurrentRequestState( $snapshot );
		}
	}

	/**
	 * @param array<string,mixed> $actionData
	 * @return array<string,mixed>
	 */
	private function runAction( string $actionSlug, array $actionData = [] ) :array {
		$nonceSnapshot = null;
		$this->requireController()->this_req->wp_is_ajax = false;
		if ( $actionSlug === SecurityAdminAuthClear::SLUG ) {
			$nonceSnapshot = $this->seedActionNonceContext( SecurityAdminAuthClear::class );
		}

		try {
			$routed = $this->requireController()->action_router->action(
				$actionSlug,
				$actionData,
				ActionRoutingController::ACTION_REST
			);
			return $routed->payload();
		}
		finally {
			if ( \is_array( $nonceSnapshot ) ) {
				$this->restoreActionNonceContext( $nonceSnapshot );
			}
		}
	}

	private function writeSessionSecAdminAt( int $userId, string $token, int $timestamp ) :void {
		$manager = \WP_Session_Tokens::get_instance( $userId );
		$session = $manager->get( $token );
		if ( !\is_array( $session ) ) {
			throw new \RuntimeException( 'Test session is not available.' );
		}

		$shield = \is_array( $session[ 'shield' ] ?? null ) ? $session[ 'shield' ] : [];
		$shield[ 'user_id' ] = $userId;
		$shield[ 'secadmin_at' ] = $timestamp;
		$session[ 'shield' ] = $shield;
		$manager->update( $token, $session );
	}

	private function sessionSecAdminAt( int $userId, string $token ) :int {
		$session = \WP_Session_Tokens::get_instance( $userId )->get( $token );
		if ( !\is_array( $session ) ) {
			throw new \RuntimeException( 'Test session is not available.' );
		}

		$shield = \is_array( $session[ 'shield' ] ?? null ) ? $session[ 'shield' ] : [];
		return (int)( $shield[ 'secadmin_at' ] ?? 0 );
	}

	private function restoreAuthCookie( string $cookieName, bool $wasSet, ?string $value ) :void {
		if ( $cookieName === '' ) {
			return;
		}
		if ( $wasSet ) {
			$_COOKIE[ $cookieName ] = (string)$value;
		}
		else {
			unset( $_COOKIE[ $cookieName ] );
		}
	}
}
