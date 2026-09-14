<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\AjaxRender;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\OffCanvas\ZoneComponentConfig;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\PageConfigureLanding;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Constants;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter\Support\PluginAdminRouteRenderAssertions;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class ConfigurationDeepLinkIntegrationTest extends ShieldIntegrationTestCase {

	use PluginAdminRouteRenderAssertions;

	public function set_up() {
		parent::set_up();
		$this->loginAsSecurityAdmin();
		$this->requireController()->this_req->wp_is_ajax = false;
	}

	public function test_every_configurable_component_preserves_its_complete_displayable_scope() :void {
		$con = $this->requireController();
		foreach ( $con->comps->zones->enumZoneComponents() as $slug => $class ) {
			$options = \array_values( \array_filter(
				( new $class() )->getOptions(),
				static fn( string $key ) :bool => !\in_array(
					$con->cfg->configuration->options[ $key ][ 'section' ],
					[ 'section_hidden', 'section_deprecated' ],
					true
				)
			) );
			$action = $this->componentAction( [ 'component' => $slug ] );
			if ( empty( $options ) ) {
				$this->assertSame( [], $action, $slug );
				continue;
			}

			$this->assertSame( AjaxRender::SLUG, $action[ ActionData::FIELD_EXECUTE ] ?? null, $slug );
			$this->assertSame( ZoneComponentConfig::SLUG, $action[ 'render_slug' ] ?? null, $slug );
			$this->assertSame( $slug, $action[ 'zone_component_slug' ] ?? null, $slug );
			$this->assertEqualsCanonicalizing( $options, \explode( ',', $action[ 'option_keys' ] ?? '' ), $slug );
			$this->assertSame( 'offcanvas', $action[ 'form_context' ] ?? null, $slug );
		}
	}

	public function test_complete_module_and_split_component_forms_render_through_existing_offcanvas() :void {
		foreach ( [ 'whitelabel', 'module_integrations', 'two_factor_auth' ] as $slug ) {
			$action = $this->componentAction( [ 'component' => $slug ] );
			$this->assertSame( ZoneComponentConfig::SLUG, $action[ 'render_slug' ] ?? null, $slug );
			$payload = $this->processActionPayloadWithAdminBypass( $action[ 'render_slug' ], $action );
			$this->assertRouteRenderOutputHealthy( $payload, $slug );
			$this->assertNotEmpty( $payload[ 'render_data' ][ 'content' ][ 'canvas_body' ] ?? null, $slug );
		}
	}

	public function test_component_selection_cannot_inject_action_or_out_of_scope_option() :void {
		foreach ( [ '', 'unknown_component', 'whitelabel,module_integrations', '../whitelabel' ] as $slug ) {
			$this->assertSame( [], $this->componentAction( [ 'component' => $slug ] ), $slug );
		}
		$action = $this->componentAction( [
			'component' => 'whitelabel',
			'config_item' => 'rename_wplogin_path',
			'render_slug' => 'arbitrary_action',
		] );
		$this->assertSame( ZoneComponentConfig::SLUG, $action[ 'render_slug' ] ?? null );
		$this->assertArrayNotHasKey( 'config_item', $action );
		$focused = $this->componentAction( [ 'component' => 'whitelabel', 'config_item' => 'whitelabel_enable' ] );
		$this->assertSame( 'whitelabel_enable', $focused[ 'config_item' ] ?? null );
	}

	private function componentAction( array $params ) :array {
		$payload = $this->processActionPayloadWithAdminBypass( PageConfigureLanding::SLUG, \array_merge( [
			Constants::NAV_ID => PluginNavs::NAV_ZONES,
			Constants::NAV_SUB_ID => PluginNavs::SUBNAV_ZONES_OVERVIEW,
		], $params ) );
		$this->assertRouteRenderOutputHealthy( $payload, 'configure component destination' );
		$action = \json_decode( (string)( $payload[ 'render_data' ][ 'vars' ][ 'configure_component_action_json' ] ?? '' ), true );
		return \is_array( $action ) ? $action : [];
	}
}
