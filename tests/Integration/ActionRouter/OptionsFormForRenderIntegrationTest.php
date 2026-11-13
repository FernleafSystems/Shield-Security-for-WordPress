<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Options\OptionsFormFor;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\ConfigureGoalPanels;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter\Support\PluginAdminRouteRenderAssertions;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

/**
 * A settings form is always a Configure panel, and it saves exactly the settings that panel shows.
 */
class OptionsFormForRenderIntegrationTest extends ShieldIntegrationTestCase {

	use PluginAdminRouteRenderAssertions;

	public function set_up() {
		parent::set_up();
		$this->loginAsSecurityAdmin();
		$this->requireController()->this_req->wp_is_ajax = false;
	}

	public function test_form_saves_exactly_the_settings_its_panel_shows() :void {
		$payload = $this->renderPanel( [ 'enable_core_file_integrity_scan', 'file_scan_areas' ], 'code:file_scanning' );
		$this->assertSame(
			[ 'enable_core_file_integrity_scan', 'file_scan_areas' ],
			$payload[ 'render_data' ][ 'vars' ][ 'all_opts_keys' ]
		);
		$this->assertStringContainsString( 'name="file_scan_areas[]"', (string)$payload[ 'render_output' ] );
	}

	public function test_a_setting_the_panel_cannot_show_is_neither_shown_nor_saved() :void {
		// File areas only appear beneath the file scanning switch, so on their own the panel has no place for them.
		$payload = $this->renderPanel( [ 'file_scan_areas' ], 'code:file_scanning' );
		$this->assertSame( [], $payload[ 'render_data' ][ 'vars' ][ 'all_opts_keys' ] );
		$this->assertStringNotContainsString( 'name="file_scan_areas[]"', (string)$payload[ 'render_output' ] );
	}

	public function test_settings_outside_the_named_topic_are_not_saved() :void {
		$topicKeys = ConfigureGoalPanels::topicOptionKeys( 'code', 'file_scanning' );
		$payload = $this->renderPanel( [ 'enable_core_file_integrity_scan', 'session_lock' ], 'code:file_scanning' );
		$saved = $payload[ 'render_data' ][ 'vars' ][ 'all_opts_keys' ];
		$this->assertContains( 'enable_core_file_integrity_scan', $saved );
		$this->assertNotContains( 'session_lock', $saved );
		$this->assertSame( [], \array_diff( $saved, $topicKeys ) );
	}

	public function test_panel_form_posts_to_the_standard_save_action() :void {
		$payload = $this->renderPanel( [ 'enable_core_file_integrity_scan' ], 'code:file_scanning' );
		$this->assertStringContainsString( 'data-options-save-action="form_save"', (string)$payload[ 'render_output' ] );
	}

	private function renderPanel( array $options, string $panel ) :array {
		$payload = $this->processActionPayloadWithAdminBypass( OptionsFormFor::SLUG, [
			'options'         => $options,
			'configure_panel' => $panel,
			'form_context'    => 'offcanvas',
		] );
		$this->assertRouteRenderOutputHealthy( $payload, 'options form render' );
		return $payload;
	}
}
