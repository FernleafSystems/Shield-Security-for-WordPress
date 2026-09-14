<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Events;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Plugin\Shield\Events\StatsWriter;
use FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses\UserSessionRotateAuthCookies;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Support\LoginSuccessFixture;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\RuntimeTestState;

class LoginSuccessRecorderIntegrationTest extends ShieldIntegrationTestCase {

	use LoginSuccessFixture;

	public function tear_down() :void {
		$this->stopLoginSuccessFixture();
		parent::tear_down();
	}

	public function test_normal_startup_registers_completed_login_tracking() :void {
		$recorder = $this->requireController()->comps->login_success;
		$this->assertNotNull( $recorder, 'Startup must provide the login success recorder.' );
		$this->assertSame( 5, \has_action( 'set_logged_in_cookie', [ $recorder, 'onSetLoggedInCookie' ] ) );
		$this->assertSame( 0, \has_action( 'clear_auth_cookie', [ $recorder, 'onClearAuthCookie' ] ) );
		$this->assertSame( 20, \has_action( 'wp_login', [ $recorder, 'onWpLogin' ] ) );
		$this->captureShieldEvents();
		$user = $this->passwordLogin();
		$this->assertInstanceOf( \WP_User::class, $user );
		$this->assertCount( 1, $this->getCapturedEventsByKey( 'login_success' ) );
	}

	public function test_signon_records_once_after_cookie_and_persists_one_count() :void {
		$this->requireDb( 'events' );
		$this->startLoginSuccessFixture();
		$writer = new class extends StatsWriter {
			public function flush() :void {
				$this->onShutdown();
			}
		};
		$before = $this->storedCount();
		$this->passwordLogin();
		$this->assertSame( [ 'cookie', 'login_success' ], $this->loginTimeline );
		$writer->flush();
		$this->assertSame( $before + 1, $this->storedCount() );
		$writer->flush();
		$this->assertSame( $before + 1, $this->storedCount() );
	}

	public function test_invalid_password_has_no_success() :void {
		$this->startLoginSuccessFixture();
		$user = self::factory()->user->create_and_get( [ 'user_pass' => 'correct-password' ] );
		$result = \wp_signon( [ 'user_login' => $user->user_login, 'user_password' => 'wrong-password' ], false );
		$this->assertWPError( $result );
		$this->assertLoginSuccessCount( 0 );
	}

	public function test_cookie_only_and_old_browser_cookie_do_not_count() :void {
		$recorder = $this->startLoginSuccessFixture();
		$user = self::factory()->user->create_and_get();
		$token = $this->issueCookie( $user );
		$this->assertLoginSuccessCount( 0 );
		\wp_clear_auth_cookie();
		$previous = $_COOKIE[ LOGGED_IN_COOKIE ] ?? null;
		$_COOKIE[ LOGGED_IN_COOKIE ] = \wp_generate_auth_cookie( $user->ID, \time() + 3600, 'logged_in', $token );
		try {
			$recorder->recordSuccessfulLogin( $user );
			$this->assertLoginSuccessCount( 0 );
		}
		finally {
			if ( $previous === null ) {
				unset( $_COOKIE[ LOGGED_IN_COOKIE ] );
			}
			else {
				$_COOKIE[ LOGGED_IN_COOKIE ] = $previous;
			}
		}
	}

	public function test_destroyed_and_mismatched_tokens_do_not_count() :void {
		$recorder = $this->startLoginSuccessFixture();
		$user = self::factory()->user->create_and_get();
		$other = self::factory()->user->create_and_get();
		$token = $this->issueCookie( $user );
		$recorder->recordSuccessfulLogin( $other );
		$this->assertLoginSuccessCount( 0 );
		\WP_Session_Tokens::get_instance( $user->ID )->destroy( $token );
		$recorder->recordSuccessfulLogin( $user );
		$this->assertLoginSuccessCount( 0 );
	}

	public function test_reentrant_and_replayed_completion_count_once_per_token() :void {
		$recorder = $this->startLoginSuccessFixture();
		$recorder->execute();
		$user = self::factory()->user->create_and_get();
		$token = $this->issueCookie( $user );
		\add_action( 'shield/event', static function ( $event ) use ( $recorder, $user ) {
			if ( $event === 'login_success' ) {
				$recorder->recordSuccessfulLogin( $user );
			}
		} );
		\do_action( 'wp_login', $user->user_login, $user );
		$recorder->recordSuccessfulLogin( $user );
		\wp_set_auth_cookie( $user->ID, false, '', $token );
		$recorder->recordSuccessfulLogin( $user );
		$this->assertLoginSuccessCount( 1 );
		$this->issueCookie( $user );
		$recorder->recordSuccessfulLogin( $user );
		$this->assertLoginSuccessCount( 2 );
	}

	/** @dataProvider disabledRecordingProvider */
	public function test_disabled_and_uninitialized_recorders_do_not_emit( bool $execute, bool $enabled, bool $forceOff ) :void {
		$con = $this->requireController();
		$options = $this->snapshotSelectedOptions( [ 'global_enable_plugin_features' ] );
		$previousForceOff = $con->this_req->is_force_off;
		try {
			RuntimeTestState::restoreOptions( [ 'global_enable_plugin_features' => $enabled ? 'Y' : 'N' ], true );
			$con->this_req->is_force_off = $forceOff;
			$recorder = $this->startLoginSuccessFixture( $execute );
			$user = self::factory()->user->create_and_get();
			$token = $this->issueCookie( $user );
			// Direct callers must not bypass the execution or current enabled gates.
			$recorder->onSetLoggedInCookie( '', 0, 0, $user->ID, 'logged_in', $token );
			$recorder->recordSuccessfulLogin( $user );
			$this->assertLoginSuccessCount( 0 );
		}
		finally {
			$con->this_req->is_force_off = $previousForceOff;
			$this->restoreSelectedOptions( $options );
		}
	}

	public static function disabledRecordingProvider() :array {
		return [
			'uninitialized' => [ false, true, false ],
			'disabled' => [ true, false, false ],
			'force off' => [ true, true, true ],
		];
	}

	public function test_malformed_cookie_candidate_cannot_reuse_a_previous_token() :void {
		$recorder = $this->startLoginSuccessFixture();
		$user = self::factory()->user->create_and_get();
		$this->issueCookie( $user );
		$recorder->onSetLoggedInCookie( '', 0, 0, $user->ID, 'logged_in', [] );
		$recorder->recordSuccessfulLogin( $user );
		$this->assertLoginSuccessCount( 0 );
	}

	public function test_session_rotation_keeps_a_valid_session_without_counting_login() :void {
		$this->startLoginSuccessFixture();
		$con = $this->requireController();
		$user = self::factory()->user->create_and_get();
		\wp_set_current_user( $user->ID );
		$token = $this->issueCookie( $user );
		$cookie = \wp_generate_auth_cookie( $user->ID, \time() + 3600, 'logged_in', $token );
		$oldRequest = clone $con->this_req;
		try {
			$con->this_req->session = $con->comps->session->buildSession( $user->ID, $token );
			$request = clone $con->this_req->request;
			$request->cookie_copy = [ LOGGED_IN_COOKIE => $cookie ];
			$con->this_req->request = $request;
			( new UserSessionRotateAuthCookies() )->setThisRequest( $con->this_req )->execResponse();
			$this->assertFalse( \WP_Session_Tokens::get_instance( $user->ID )->verify( $token ) );
			$this->assertTrue( $con->this_req->session->valid );
			$this->assertTrue( \WP_Session_Tokens::get_instance( $user->ID )->verify( $con->this_req->session->token ) );
			$this->assertLoginSuccessCount( 0 );
		}
		finally {
			$con->this_req = $oldRequest;
		}
	}

	private function passwordLogin() :\WP_User {
		$user = self::factory()->user->create_and_get( [ 'user_pass' => 'login-success-test-password' ] );
		\wp_set_current_user( 0 );
		$result = \wp_signon( [ 'user_login' => $user->user_login, 'user_password' => 'login-success-test-password' ], false );
		$this->assertInstanceOf( \WP_User::class, $result );
		return $result;
	}

	private function issueCookie( \WP_User $user ) :string {
		$token = \WP_Session_Tokens::get_instance( $user->ID )->create( \time() + 3600 );
		\wp_set_auth_cookie( $user->ID, false, '', $token );
		return $token;
	}

	private function storedCount() :int {
		global $wpdb;
		$table = $this->requireController()->db_con->events->getTableSchema()->table;
		return (int)$wpdb->get_var( $wpdb->prepare( "SELECT SUM(`count`) FROM `{$table}` WHERE `event` = %s", 'login_success' ) );
	}
}
