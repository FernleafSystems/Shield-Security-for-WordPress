<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\PluginFeaturesEnable;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\PluginNotices\GloballyDisabled;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\RuntimeTestState;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter\Support\{
	ExternalEntryRefusalAssertions,
	PluginAdminRouteRenderAssertions
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Support\CurrentRequestFixture;

/**
 * When all protection is switched off, the notice offers one switch to turn it back on.
 */
class PluginFeaturesEnableIntegrationTest extends ShieldIntegrationTestCase {

	use CurrentRequestFixture;
	use ExternalEntryRefusalAssertions;
	use PluginAdminRouteRenderAssertions;

	private array $optionsSnapshot = [];

	public function set_up() {
		parent::set_up();
		$this->optionsSnapshot = $this->snapshotSelectedOptions( [ 'global_enable_plugin_features' ] );
		$this->loginAsSecurityAdmin();
		$this->requireController()->this_req->wp_is_ajax = false;
		RuntimeTestState::restoreOptions( [ 'global_enable_plugin_features' => 'N' ], true );
	}

	public function tear_down() {
		$this->restoreSelectedOptions( $this->optionsSnapshot );
		parent::tear_down();
	}

	public function test_action_turns_protection_back_on_and_reloads_the_page() :void {
		$payload = $this->processActionPayloadWithAdminBypass( PluginFeaturesEnable::SLUG );
		$this->assertTrue( $payload[ 'success' ] );
		$this->assertTrue( $payload[ 'page_reload' ] ?? false );
		$this->assertTrue( $this->requireController()->comps->opts_lookup->isPluginEnabled() );
	}

	public function test_action_refuses_an_invalid_nonce_without_changing_anything() :void {
		$this->assertAjaxRequestRefused( PluginFeaturesEnable::class, [], 'invalid' );
		$this->assertFalse( $this->requireController()->comps->opts_lookup->isPluginEnabled() );
	}

	public function test_notice_opens_the_dialog_that_runs_the_action() :void {
		$notice = ( new GloballyDisabled() )->check();
		$this->assertIsArray( $notice );
		$this->assertMatchesRegularExpression( '#<button[^>]+data-enable-protection="([^"]+)"#', $notice[ 'text' ][ 0 ] );
		\preg_match( '#data-enable-protection="([^"]+)"#', $notice[ 'text' ][ 0 ], $matches );
		$dialog = \json_decode( \html_entity_decode( $matches[ 1 ], \ENT_QUOTES ), true, 512, \JSON_THROW_ON_ERROR );
		$this->assertSame( PluginFeaturesEnable::SLUG, $dialog[ 'action' ][ 'ex' ] ?? null );
		$this->assertNotSame( '', $dialog[ 'title' ] );
		$this->assertNotSame( '', $dialog[ 'setting_label' ] );
	}

	public function test_notice_is_absent_while_protection_is_on() :void {
		RuntimeTestState::restoreOptions( [ 'global_enable_plugin_features' => 'Y' ], true );
		$this->assertNull( ( new GloballyDisabled() )->check() );
	}
}
