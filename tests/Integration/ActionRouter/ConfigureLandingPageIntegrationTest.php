<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	Actions\AjaxRender,
	Actions\Render\PluginAdminPages\PageConfigureLanding,
	Constants
};
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter\Support\{
	HtmlDomAssertions,
	ModeLandingAssertions,
	PluginAdminRouteRenderAssertions
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class ConfigureLandingPageIntegrationTest extends ShieldIntegrationTestCase {

	use HtmlDomAssertions;
	use ModeLandingAssertions;
	use PluginAdminRouteRenderAssertions;

	public function set_up() {
		parent::set_up();
		$this->loginAsSecurityAdmin();
		$this->requireController()->this_req->wp_is_ajax = false;
	}

	private function renderConfigureLandingPage() :array {
		return $this->processActionPayloadWithAdminBypass( PageConfigureLanding::SLUG, [
			Constants::NAV_ID     => PluginNavs::NAV_ZONES,
			Constants::NAV_SUB_ID => PluginNavs::SUBNAV_ZONES_OVERVIEW,
		] );
	}

	public function test_configure_landing_exposes_payload_contract_for_route_and_tiles() :void {
		$payload = $this->renderConfigureLandingPage();
		$html = $this->assertRouteRenderOutputHealthy( $payload, 'configure landing' );
		$renderData = $payload[ 'render_data' ] ?? [];
		$vars = \is_array( $renderData[ 'vars' ] ?? null ) ? $renderData[ 'vars' ] : [];
		$strings = \is_array( $renderData[ 'strings' ] ?? null ) ? $renderData[ 'strings' ] : [];
		$tileDefinitions = PluginNavs::configureLandingTileDefinitions();
		$expectedCount = \count( $tileDefinitions );
		$tileDefinitionsByKey = [];
		foreach ( $tileDefinitions as $tileDefinition ) {
			$tileDefinitionsByKey[ (string)( $tileDefinition[ 'key' ] ?? '' ) ] = $tileDefinition;
		}
		$renderActionData = \is_array( $vars[ 'configure_render_action' ] ?? null ) ? $vars[ 'configure_render_action' ] : [];
		$xpath = $this->createDomXPathFromHtml( $html );
		$secadminIcon = (string)( $tileDefinitionsByKey[ 'secadmin' ][ 'icon' ] ?? '' );
		$this->assertSame( ActionData::FIELD_SHIELD, $renderActionData[ ActionData::FIELD_ACTION ] ?? '' );
		$this->assertSame( AjaxRender::SLUG, $renderActionData[ ActionData::FIELD_EXECUTE ] ?? '' );
		$this->assertSame( PageConfigureLanding::SLUG, $renderActionData[ 'render_slug' ] ?? '' );
		$this->assertSame( PluginNavs::NAV_ZONES, $renderActionData[ Constants::NAV_ID ] ?? '' );
		$this->assertSame( PluginNavs::SUBNAV_ZONES_OVERVIEW, $renderActionData[ Constants::NAV_SUB_ID ] ?? '' );
		$this->assertModeShellPayload( $vars, 'configure', 'good', true );
		$this->assertModePanelPayload( $vars, '', false );
		$this->assertCount( $expectedCount, $vars[ 'zone_tiles' ] ?? [] );
		$this->assertCount( $expectedCount, $vars[ 'mode_tiles' ] ?? [] );
		$this->assertSame( $expectedCount, \count( \array_unique( \array_column( $vars[ 'mode_tiles' ] ?? [], 'key' ) ) ) );
		$this->assertIsInt( $vars[ 'posture_percentage' ] ?? null );
		$this->assertNotSame( '', (string)( $vars[ 'posture_status' ] ?? '' ) );
		$this->assertNotSame( '', (string)( $vars[ 'posture_label' ] ?? '' ) );
		$this->assertNotSame( '', (string)( $vars[ 'posture_icon_class' ] ?? '' ) );
		$this->assertNotSame( '', (string)( $vars[ 'posture_summary' ] ?? '' ) );
		$this->assertXPathExists(
			$xpath,
			'//*[@data-configure-section="hero"]/*[contains(concat(" ", normalize-space(@class), " "), " shield-mode-strip ")]',
			'Configure landing shared strip root marker'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-configure-section="hero"]//*[contains(concat(" ", normalize-space(@class), " "), " shield-mode-strip__chip ")]',
			'Configure landing shared strip chip marker'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-configure-section="hero"]//*[@role="progressbar" and contains(concat(" ", normalize-space(@class), " "), " progress-bar ") and @aria-label="'.(string)( $strings[ 'posture_title' ] ?? '' ).'" and @aria-valuenow="'.(string)( $vars[ 'posture_percentage' ] ?? 0 ).'"]',
			'Configure landing posture progressbar marker'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-configure-section="zones"]//*[@data-shield-rail-scope="1"]',
			'Configure landing should render the scoped rail layout'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-configure-section="zones"]//*[@data-shield-rail-target="secadmin"]//*[contains(concat(" ", normalize-space(@class), " "), " shield-rail-sidebar__icon ")]/i[contains(concat(" ", normalize-space(@class), " "), " bi-'.htmlspecialchars( $secadminIcon, \ENT_QUOTES ).' ")]',
			'Configure landing should render the configure zone icon in the rail trigger'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-configure-section="zones"]//*[contains(concat(" ", normalize-space(@class), " "), " tab-content ")]//*[@data-shield-rail-pane]',
			'Configure landing should render rail panes inside a Bootstrap tab-content container'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-configure-section="zones"]//*[@data-shield-rail-pane]//a[contains(concat(" ", normalize-space(@class), " "), " configure-landing__panel-cta ") and @data-configure-zone-settings]',
			'Configure landing should render Configure CTA actions inside the rail panes'
		);
		$expandPlaceholders = $xpath->query( '//*[@data-configure-expand-ajax="1"]' );
		$this->assertNotFalse( $expandPlaceholders, 'Configure landing expansion placeholders query failed' );
		$this->assertGreaterThan( 0, $expandPlaceholders->length, 'Configure landing should render expansion AJAX placeholders' );
		$this->assertXPathCount(
			$xpath,
			'//*[@data-configure-expand-ajax="1" and @data-zone_component_action="offcanvas_zone_component_config" and string-length(normalize-space(@data-zone_component_slug)) > 0]',
			$expandPlaceholders->length,
			'Configure landing expansion placeholders should carry zone component AJAX contract'
		);

		foreach ( $tileDefinitions as $tileDefinition ) {
			$zoneKey = (string)$tileDefinition[ 'key' ];
			$matches = \array_values( \array_filter(
				$vars[ 'zone_tiles' ] ?? [],
				static fn( array $tile ) :bool => (string)( $tile[ 'key' ] ?? '' ) === $zoneKey
			) );
			$this->assertCount( 1, $matches, 'Configure zone tile payload for '.$zoneKey );
			$this->assertSame( $zoneKey, (string)( $matches[ 0 ][ 'panel_target' ] ?? '' ) );
			$this->assertArrayHasKey( 'settings_action', $matches[ 0 ] ?? [] );
			$this->assertArrayHasKey( 'panel', $matches[ 0 ] ?? [] );
		}
	}

}
