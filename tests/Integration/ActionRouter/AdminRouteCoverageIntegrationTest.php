<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PageAdminPluginRouteResolver;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter\Support\PluginAdminRouteRenderAssertions;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class AdminRouteCoverageIntegrationTest extends ShieldIntegrationTestCase {

	use PluginAdminRouteRenderAssertions;

	public function set_up() {
		parent::set_up();
		$this->loginAsSecurityAdmin();
		$this->requireController()->this_req->wp_is_ajax = false;
	}

	public function test_every_registered_route_resolves_and_renders_its_page() :void {
		$resolver = new PageAdminPluginRouteResolver();
		foreach ( PluginNavs::GetNavHierarchy() as $nav => $definition ) {
			foreach ( $definition[ 'sub_navs' ] as $subNav => $route ) {
				$label = $nav.'/'.$subNav;
				$input = [ 'nav' => $nav, 'nav_sub' => $subNav ];
				$resolved = $resolver->resolve( $input, true );
				$this->assertSame( $nav, $resolved[ 'nav' ], $label );
				$this->assertSame( $subNav, $resolved[ 'subnav' ], $label );
				// Exercise the actual inner renderer too: the outer shell can catch an inner error.
				$payload = $this->processActionPayloadWithAdminBypass(
					$resolved[ 'delegate_action' ]::SLUG, $resolved[ 'delegate_payload' ]
				);
				$this->assertNotSame( '', $this->assertRouteRenderOutputHealthy( $payload, $label ), $label );
			}
		}
	}

	public function test_existing_legacy_redirects_reach_registered_destinations_without_a_chain() :void {
		$urls = $this->requireController()->plugin_urls;
		foreach ( [
			[ 'scans', 'results', 'scans', 'overview', [ 'zone' => 'scans' ] ],
			[ 'scans', 'history', 'scans', 'overview', [ 'zone' => 'scans' ] ],
			[ 'scans', 'state', 'scans', 'overview', [ 'zone' => 'scans' ] ],
			[ 'reports', 'alerts', 'reports', 'overview', [ 'workspace' => 'settings' ] ],
			[ 'reports', 'reporting', 'reports', 'overview', [ 'workspace' => 'settings' ] ],
			[ 'reports', 'list', 'reports', 'overview', [ 'workspace' => 'list' ] ],
			[ 'reports', 'settings', 'reports', 'overview', [ 'workspace' => 'settings' ] ],
			[ 'reports', 'charts', 'reports', 'overview', [ 'workspace' => 'charts' ] ],
			[ 'traffic', 'live', 'activity', 'overview', [ 'subject' => 'live_traffic' ] ],
			[ 'tools', 'sessions', 'activity', 'sessions', [] ],
		] as [ $nav, $subNav, $targetNav, $targetSubNav, $state ] ) {
			$url = $urls->legacyAdminRouteRedirect( $nav, $subNav );
			$this->assertNotNull( $url, $nav.'/'.$subNav );
			\parse_str( \parse_url( $url, PHP_URL_QUERY ), $query );
			$this->assertSame( $targetNav, $query[ 'nav' ] );
			$this->assertSame( $targetSubNav, $query[ 'nav_sub' ] );
			foreach ( $state as $key => $value ) {
				$this->assertSame( $value, $query[ $key ] );
			}
			$this->assertTrue( PluginNavs::NavExists( $targetNav, $targetSubNav ) );
			$this->assertNull( $urls->legacyAdminRouteRedirect( $targetNav, $targetSubNav ) );
			$resolved = ( new PageAdminPluginRouteResolver() )->resolve( [ 'nav' => $nav, 'nav_sub' => $subNav ], true );
			$this->assertSame( \array_merge( [ 'nav' => $targetNav, 'nav_sub' => $targetSubNav ], $state ), $resolved[ 'delegate_payload' ] );
		}
	}

	public function test_report_deep_links_select_the_workspace_instead_of_the_menu() :void {
		foreach ( [ 'list', 'settings', 'charts' ] as $workspace ) {
			$route = ( new PageAdminPluginRouteResolver() )->resolve( [
				'nav' => 'reports', 'nav_sub' => 'overview', 'workspace' => $workspace,
			], true );
			$this->assertSame( $workspace, $route[ 'delegate_payload' ][ 'workspace' ] ?? null );
			$payload = $this->processActionPayloadWithAdminBypass(
				$route[ 'delegate_action' ]::SLUG, $route[ 'delegate_payload' ]
			);
			$this->assertRouteRenderOutputHealthy( $payload, $workspace );
			$this->assertSame( 1, $payload[ 'render_data' ][ 'vars' ][ 'drill_shell' ][ 'active_index' ] );
			$this->assertSame( $workspace, $payload[ 'render_data' ][ 'vars' ][ 'reports_workspace' ] );
		}
	}

	public function test_invalid_report_workspace_does_not_break_the_route() :void {
		$resolver = new PageAdminPluginRouteResolver();
		foreach ( [ [ 'charts' ], new \stdClass(), 42, 'unknown' ] as $workspace ) {
			$input = [ 'nav' => 'reports', 'nav_sub' => 'overview', 'workspace' => $workspace ];
			$this->assertSame( [ 'nav' => 'reports', 'nav_sub' => 'overview' ], $resolver->buildAdminPageActionData( $input ) );
			$this->assertArrayNotHasKey( 'workspace', $resolver->resolve( $input, true )[ 'delegate_payload' ] );
			$payload = $this->processActionPayloadWithAdminBypass(
				\FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\PageReportsLanding::SLUG, $input
			);
			$this->assertRouteRenderOutputHealthy( $payload, 'reports/overview' );
			$this->assertSame( 0, $payload[ 'render_data' ][ 'vars' ][ 'drill_shell' ][ 'active_index' ] );
		}
	}

	public function test_report_creation_action_retains_its_capability_gate() :void {
		foreach ( [ false, true ] as $allowed ) {
			if ( $allowed ) {
				$this->enablePremiumCapabilities( [ 'reports_local' ] );
			}
			else {
				$this->disablePremiumCapabilities();
			}
			$payload = $this->processActionPayloadWithAdminBypass(
				\FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\PageReportsLanding::SLUG,
				[ 'nav' => 'reports', 'nav_sub' => 'overview', 'workspace' => 'list' ]
			);
			$actions = \array_filter( $payload[ 'render_data' ][ 'hrefs' ][ 'inner_page_contextual_hrefs' ],
				static fn( array $href ) :bool => \in_array( 'offcanvas_report_create_form', $href[ 'classes' ], true )
			);
			$this->assertCount( $allowed ? 1 : 0, $actions );
		}
	}

	public function test_every_legacy_configuration_route_has_an_exact_live_destination() :void {
		$urls = $this->requireController()->plugin_urls;
		$special = [
			'reporting' => [ 'reports', 'overview', 'workspace', 'settings' ],
			'activity_logging' => [ 'activity', 'logs' ],
			'request_logging' => [ 'traffic', 'logs' ],
			'scans' => [ 'scans', 'overview' ],
			'server_software_status' => [ 'tools', 'debug' ],
		];
		foreach ( $this->requireController()->comps->zones->enumZoneComponents() as $slug => $component ) {
			$destination = $urls->legacyAdminRouteRedirect( 'zone_components', $slug );
			$this->assertNotNull( $destination, $slug );
			if ( $slug === 'wordpress_updates' ) {
				$this->assertSame( \FernleafSystems\Wordpress\Services\Services::WpGeneral()->getAdminUrl( 'update-core.php', false ), $destination );
				$this->assertSame( $destination, $urls->cfgForZoneComponent( $slug ) );
				continue;
			}
			\parse_str( \parse_url( $destination, PHP_URL_QUERY ), $query );
			$expected = $special[ $slug ] ?? [ 'zones', 'overview', 'component', $slug ];
			$this->assertSame( $expected[ 0 ], $query[ 'nav' ], $slug );
			$this->assertSame( $expected[ 1 ], $query[ 'nav_sub' ], $slug );
			if ( isset( $expected[ 2 ] ) ) {
				$this->assertSame( $expected[ 3 ], $query[ $expected[ 2 ] ], $slug );
			}
			$this->assertTrue( PluginNavs::NavExists( $query[ 'nav' ], $query[ 'nav_sub' ] ), $slug );
			$this->assertNull( $urls->legacyAdminRouteRedirect( $query[ 'nav' ], $query[ 'nav_sub' ] ), $slug );
			$this->assertSame( $destination, $urls->cfgForZoneComponent( $slug ), $slug );
		}
		$this->assertNull( $urls->legacyAdminRouteRedirect( 'zone_components', 'not-a-component' ) );
	}
}
