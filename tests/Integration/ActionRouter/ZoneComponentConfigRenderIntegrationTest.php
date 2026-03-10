<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\OffCanvas\ZoneComponentConfig;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter\Support\{
	HtmlDomAssertions,
	PluginAdminRouteRenderAssertions
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Plugin\Shield\Zones\Component\PluginGeneral;

class ZoneComponentConfigRenderIntegrationTest extends ShieldIntegrationTestCase {

	use HtmlDomAssertions;
	use PluginAdminRouteRenderAssertions;

	public function set_up() {
		parent::set_up();
		$this->loginAsSecurityAdmin();
		$this->requireController()->this_req->wp_is_ajax = false;
	}

	public function test_zone_component_config_renders_expansion_context_inside_existing_offcanvas_contract() :void {
		$payload = $this->processActionPayloadWithAdminBypass( ZoneComponentConfig::SLUG, [
			'zone_component_slug' => PluginGeneral::Slug(),
			'form_context'        => 'expansion',
		] );
		$html = $this->assertRouteRenderOutputHealthy( $payload, 'zone component config offcanvas render' );
		$xpath = $this->createDomXPathFromHtml( $html );

		$this->assertXPathExists(
			$xpath,
			'//*[@id="AptoOffcanvasLabel" and contains(@class,"offcanvas-title") and normalize-space()!=""]',
			'Zone component config offcanvas title contract'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[contains(@class,"offcanvas-body")]//form[contains(concat(" ", normalize-space(@class), " "), " options_form_for ") and @data-context="expansion"]',
			'Zone component config should pass the expansion form context to the rendered options form'
		);
	}
}
