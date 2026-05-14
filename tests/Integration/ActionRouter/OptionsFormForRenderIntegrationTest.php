<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Options\OptionsFormFor;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter\Support\PluginAdminRouteRenderAssertions;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class OptionsFormForRenderIntegrationTest extends ShieldIntegrationTestCase {

	use PluginAdminRouteRenderAssertions;

	public function set_up() {
		parent::set_up();
		$this->loginAsSecurityAdmin();
		$this->requireController()->this_req->wp_is_ajax = false;
	}

	private function renderOptionsFormPayload( array $options, array $extra = [] ) :array {
		$payload = $this->processActionPayloadWithAdminBypass(
			OptionsFormFor::SLUG,
			\array_merge( [
				'options' => $options,
			], $extra )
		);
		$this->assertRouteRenderOutputHealthy( $payload, 'options form render' );
		return $payload;
	}

	public function test_option_render_data_preserves_requested_option_order_and_focus() :void {
		$payload = $this->renderOptionsFormPayload(
			[
				'session_lock',
				'session_idle_timeout_interval',
			],
			[
				'config_item' => 'session_lock',
			]
		);

		$renderData = (array)( $payload[ 'render_data' ] ?? [] );
		$sections = (array)( $renderData[ 'vars' ][ 'all_options' ] ?? [] );

		$this->assertSame(
			[ 'session_lock', 'session_idle_timeout_interval' ],
			$renderData[ 'vars' ][ 'all_opts_keys' ] ?? []
		);
		$this->assertTrue( $this->containsFocusedOption( $sections, 'session_lock' ) );
		$this->assertFalse( $this->containsFocusedOption( $sections, 'session_idle_timeout_interval' ) );
	}

	public function test_option_render_data_exposes_transfer_contract() :void {
		$payload = $this->renderOptionsFormPayload( [
			'admin_access_key',
			'admin_access_restrict_plugins',
		] );

		$renderData = (array)( $payload[ 'render_data' ] ?? [] );
		$this->assertTrue( (bool)( $renderData[ 'flags' ][ 'show_transfer_switch' ] ?? false ) );
		$this->assertIsArray( $renderData[ 'vars' ][ 'xferable_opts' ] ?? null );
		$this->assertIsArray( $renderData[ 'vars' ][ 'xfer_excluded_opts' ] ?? null );
		$this->assertSame( 'normal', (string)( $renderData[ 'vars' ][ 'form_context' ] ?? '' ) );
	}

	private function containsFocusedOption( array $sections, string $optionKey ) :bool {
		foreach ( $sections as $section ) {
			foreach ( (array)( $section[ 'options' ] ?? [] ) as $option ) {
				if ( (string)( $option[ 'key' ] ?? '' ) === $optionKey ) {
					return (bool)( $option[ 'is_focus' ] ?? false );
				}
			}
		}
		return false;
	}
}
