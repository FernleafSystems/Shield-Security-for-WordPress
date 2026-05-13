<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\OffCanvas\ZoneComponentConfig;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter\Support\PluginAdminRouteRenderAssertions;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Plugin\Shield\Zones\Component\PluginGeneral;

class ZoneComponentConfigRenderIntegrationTest extends ShieldIntegrationTestCase {

	use PluginAdminRouteRenderAssertions;

	public function set_up() {
		parent::set_up();
		$this->loginAsSecurityAdmin();
		$this->requireController()->this_req->wp_is_ajax = false;
	}

	public function test_zone_component_config_exposes_offcanvas_context_contract() :void {
		$payload = $this->processActionPayloadWithAdminBypass( ZoneComponentConfig::SLUG, [
			'zone_component_slug' => PluginGeneral::Slug(),
			'form_context'        => 'expansion',
		] );
		$this->assertRouteRenderOutputHealthy( $payload, 'zone component config offcanvas render' );

		$renderData = (array)( $payload[ 'render_data' ] ?? [] );
		$this->assertSame( ZoneComponentConfig::TEMPLATE, (string)( $payload[ 'render_template' ] ?? '' ) );
		$this->assertArrayHasKey( 'canvas_title', $renderData[ 'content' ] ?? [] );
		$this->assertArrayHasKey( 'canvas_body', $renderData[ 'content' ] ?? [] );
		$this->assertIsString( $renderData[ 'content' ][ 'canvas_title' ] );
		$this->assertIsString( $renderData[ 'content' ][ 'canvas_body' ] );
	}
}
