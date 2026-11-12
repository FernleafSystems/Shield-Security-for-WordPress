<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	ActionNonce,
	Actions\PluginBadgeClose,
	Actions\PluginSetUiPreference,
	Actions\SecurityAdminRemove,
	Exceptions\InvalidActionNonceException,
	Utility\ExternalEntryGuard
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\ActionRouter\StartsLoginSessions;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

/**
 * Action nonces are issued into pages, links and emails, so their value is a compatibility contract. A nonce
 * that isn't bound to a login session (a logged-out visitor's, or one issued for any session) must keep
 * validating for its lifetime after an update. A logged-in user's nonce is bound to their login session.
 */
class ActionNonceIntegrationTest extends ShieldIntegrationTestCase {

	use StartsLoginSessions;

	private const TICK_SECONDS = 12*\HOUR_IN_SECONDS;

	public function set_up() {
		parent::set_up();
		$this->rememberLoginCookie();
		unset( $_COOKIE[ \LOGGED_IN_COOKIE ] );
		$this->loginAsAdministrator();
	}

	public function tear_down() {
		$this->restoreLoginCookie();
		parent::tear_down();
	}

	public function test_nonce_without_a_session_keeps_its_established_format() :void {
		$userId = \get_current_user_id();
		$expected = $this->nonceForTick( PluginBadgeClose::SLUG, $userId, '-', $this->currentTick() );

		// Logged in without a login session, as a request authenticated without a cookie is.
		$this->assertSame( $expected, ActionNonce::Create( PluginBadgeClose::class ) );

		// Issued for any session: the session the user is in makes no difference.
		$this->startLoginSession( $userId );
		$this->assertSame( $expected, ActionNonce::Create( PluginBadgeClose::class, false ) );
	}

	public function test_logged_out_nonce_ignores_a_leftover_login_cookie() :void {
		$this->startLoginSession( \get_current_user_id() );
		\wp_set_current_user( 0 );

		$this->assertSame(
			$this->nonceForTick( PluginBadgeClose::SLUG, 0, '-', $this->currentTick() ),
			ActionNonce::Create( PluginBadgeClose::class )
		);
	}

	public function test_logged_in_nonce_is_bound_to_the_login_session() :void {
		$userId = \get_current_user_id();
		$token = $this->startLoginSession( $userId );

		$nonce = ActionNonce::Create( PluginBadgeClose::class );
		$this->assertSame( $this->nonceForTick( PluginBadgeClose::SLUG, $userId, $token, $this->currentTick() ), $nonce );
		$this->assertTrue( ActionNonce::Verify( PluginBadgeClose::class, $nonce ) );

		// The same user logs in again: a new session, so the earlier page's nonce no longer works.
		$this->startLoginSession( $userId );
		$this->assertFalse( ActionNonce::Verify( PluginBadgeClose::class, $nonce ) );
	}

	public function test_nonce_issued_for_any_session_survives_a_new_login_session() :void {
		$userId = \get_current_user_id();
		$this->startLoginSession( $userId );
		$nonce = ActionNonce::Create( PluginBadgeClose::class, false );

		$this->startLoginSession( $userId );
		$this->assertTrue( ActionNonce::Verify( PluginBadgeClose::class, $nonce, false ) );
		// It is still not a session-bound nonce.
		$this->assertFalse( ActionNonce::Verify( PluginBadgeClose::class, $nonce ) );
	}

	public function test_entry_guard_verifies_the_kind_of_nonce_each_action_declares() :void {
		$userId = \get_current_user_id();
		$guard = new ExternalEntryGuard();

		$this->startLoginSession( $userId );
		$emailedLink = ActionData::Build( SecurityAdminRemove::class, false );
		$pageRequest = ActionData::Build( PluginBadgeClose::class );

		$guard->verifyProof( SecurityAdminRemove::SLUG, ExternalEntryGuard::ENTRY_ORDINARY, $emailedLink );
		$guard->verifyProof( PluginBadgeClose::SLUG, ExternalEntryGuard::ENTRY_AJAX, $pageRequest );

		$this->startLoginSession( $userId );
		// The emailed link still works in the new session; the page's request does not.
		$guard->verifyProof( SecurityAdminRemove::SLUG, ExternalEntryGuard::ENTRY_ORDINARY, $emailedLink );
		$this->expectException( InvalidActionNonceException::class );
		$guard->verifyProof( PluginBadgeClose::SLUG, ExternalEntryGuard::ENTRY_AJAX, $pageRequest );
	}

	public function test_nonce_from_the_current_and_previous_tick_is_accepted() :void {
		$userId = \get_current_user_id();
		$tick = $this->currentTick();

		$this->assertTrue( ActionNonce::Verify( PluginBadgeClose::class, $this->nonceForTick( PluginBadgeClose::SLUG, $userId, '-', $tick ) ) );
		$this->assertTrue( ActionNonce::Verify( PluginBadgeClose::SLUG, $this->nonceForTick( PluginBadgeClose::SLUG, $userId, '-', $tick - 1 ) ) );
	}

	public function test_nonce_older_than_the_previous_tick_is_refused() :void {
		$this->assertFalse( ActionNonce::Verify(
			PluginBadgeClose::class,
			$this->nonceForTick( PluginBadgeClose::SLUG, \get_current_user_id(), '-', $this->currentTick() - 2 )
		) );
	}

	public function test_nonce_is_bound_to_its_action() :void {
		$this->assertFalse( ActionNonce::Verify( PluginSetUiPreference::class, ActionNonce::Create( PluginBadgeClose::class ) ) );
	}

	public function test_nonce_is_bound_to_its_user() :void {
		$nonce = ActionNonce::Create( PluginBadgeClose::class );

		$this->loginAsAdministrator();
		$this->assertFalse( ActionNonce::Verify( PluginBadgeClose::class, $nonce ) );

		\wp_set_current_user( 0 );
		$this->assertFalse( ActionNonce::Verify( PluginBadgeClose::class, $nonce ) );
	}

	public function test_empty_nonce_is_refused() :void {
		$this->assertFalse( ActionNonce::Verify( PluginBadgeClose::class, '' ) );
	}

	private function currentTick() :int {
		return (int)\ceil( \time()/self::TICK_SECONDS );
	}

	/**
	 * Written out in full, not built from the plugin's constants, so a change to any part of the value is caught.
	 * @param string $session the login session token, or '-' for a nonce with no session
	 */
	private function nonceForTick( string $slug, int $userId, string $session, int $tick ) :string {
		return \substr( \wp_hash( \sprintf( 'shield_action-%s|%s|%s|%s', $slug, $userId, $session, $tick ), 'nonce' ), -12, 10 );
	}
}
