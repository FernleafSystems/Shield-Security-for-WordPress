<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionNonce,
	Actions\PluginBadgeClose,
	Actions\PluginSetUiPreference
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

/**
 * Action nonces are issued into pages, links and emails, so their value is a compatibility contract:
 * a nonce issued by an earlier release must keep validating for its lifetime after an update.
 */
class ActionNonceIntegrationTest extends ShieldIntegrationTestCase {

	private const TICK_SECONDS = 12*\HOUR_IN_SECONDS;

	public function set_up() {
		parent::set_up();
		$this->loginAsAdministrator();
	}

	public function test_issued_nonce_keeps_its_established_format() :void {
		$this->assertSame(
			$this->nonceForTick( PluginBadgeClose::SLUG, \get_current_user_id(), $this->currentTick() ),
			ActionNonce::Create( PluginBadgeClose::class )
		);
	}

	public function test_nonce_from_the_current_and_previous_tick_is_accepted() :void {
		$userId = \get_current_user_id();
		$tick = $this->currentTick();

		$this->assertTrue( ActionNonce::Verify( PluginBadgeClose::class, $this->nonceForTick( PluginBadgeClose::SLUG, $userId, $tick ) ) );
		$this->assertTrue( ActionNonce::Verify( PluginBadgeClose::SLUG, $this->nonceForTick( PluginBadgeClose::SLUG, $userId, $tick - 1 ) ) );
	}

	public function test_nonce_older_than_the_previous_tick_is_refused() :void {
		$this->assertFalse( ActionNonce::Verify(
			PluginBadgeClose::class,
			$this->nonceForTick( PluginBadgeClose::SLUG, \get_current_user_id(), $this->currentTick() - 2 )
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
	 */
	private function nonceForTick( string $slug, int $userId, int $tick ) :string {
		return \substr( \wp_hash( \sprintf( 'shield_action-%s|%s|-|%s', $slug, $userId, $tick ), 'nonce' ), -12, 10 );
	}
}
