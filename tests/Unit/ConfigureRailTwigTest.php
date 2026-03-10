<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PluginPathsTrait;
use Twig\{
	Environment,
	Loader\FilesystemLoader,
	TemplateWrapper
};

class ConfigureRailTwigTest extends BaseUnitTest {

	use PluginPathsTrait;

	private function twig() :Environment {
		return new Environment(
			new FilesystemLoader( $this->getPluginFilePath( 'templates/twig' ) ),
			[
				'cache'            => false,
				'debug'            => false,
				'strict_variables' => false,
			]
		);
	}

	private function createDomXPathFromHtml( string $html ) :\DOMXPath {
		$doc = new \DOMDocument();
		$previous = \libxml_use_internal_errors( true );
		try {
			$doc->loadHTML(
				'<?xml encoding="utf-8" ?>'.$html,
				\LIBXML_HTML_NOIMPLIED | \LIBXML_HTML_NODEFDTD
			);
		}
		finally {
			\libxml_clear_errors();
			\libxml_use_internal_errors( $previous );
		}

		return new \DOMXPath( $doc );
	}

	private function assertXPathExists( \DOMXPath $xpath, string $query, string $label ) :\DOMNode {
		$nodes = $xpath->query( $query );
		$this->assertNotFalse( $nodes, $label.' query failed: '.$query );
		$this->assertGreaterThan( 0, $nodes->length, $label.' missing for query: '.$query );
		return $nodes->item( 0 );
	}

	private function buildRenderContext() :array {
		$zoneTiles = [
			[
				'key'               => 'secadmin',
				'label'             => 'Security Admin',
				'status'            => 'good',
				'status_icon_class' => 'bi bi-check-circle-fill',
				'settings_href'     => '/admin/zones/secadmin',
				'settings_label'    => 'Configure Security Admin Settings',
				'settings_action'   => [
					'classes' => [ 'zone_component_action' ],
					'title'   => 'Open Security Admin settings',
					'data'    => [
						'zone_component_action' => 'offcanvas_zone_component_config',
						'form_context'          => 'offcanvas',
					],
				],
				'nav_id'            => 'configure-rail-tab-secadmin',
				'pane_id'           => 'configure-rail-pane-secadmin',
				'target'            => '#configure-rail-pane-secadmin',
				'controls'          => 'configure-rail-pane-secadmin',
				'is_active'         => false,
				'panel'             => [
					'status'        => 'good',
					'detail_groups' => [
						[
							'status' => 'good',
							'rows'   => [
								[
									'title'             => 'PIN Protection',
									'summary'           => 'PIN is configured.',
									'status'            => 'good',
									'status_label'      => 'Active',
									'status_icon_class' => 'bi bi-check-circle-fill',
									'count_badge'       => null,
									'badge_status'      => 'good',
									'explanations'      => [],
									'action'            => [],
								],
							],
						],
					],
				],
			],
			[
				'key'               => 'firewall',
				'label'             => 'Firewall',
				'status'            => 'critical',
				'status_icon_class' => 'bi bi-x-circle-fill',
				'settings_href'     => '/admin/zones/firewall',
				'settings_label'    => 'Configure Firewall Settings',
				'settings_action'   => [
					'classes' => [ 'zone_component_action' ],
					'title'   => 'Open Firewall settings',
					'data'    => [
						'zone_component_action' => 'offcanvas_zone_component_config',
						'zone_component_slug'   => 'firewall',
						'form_context'          => 'offcanvas',
					],
				],
				'nav_id'            => 'configure-rail-tab-firewall',
				'pane_id'           => 'configure-rail-pane-firewall',
				'target'            => '#configure-rail-pane-firewall',
				'controls'          => 'configure-rail-pane-firewall',
				'is_active'         => true,
				'panel'             => [
					'status'        => 'critical',
					'detail_groups' => [
						[
							'status' => 'critical',
							'rows'   => [
								[
									'title'             => 'WAF Rules',
									'summary'           => 'Critical protection is disabled.',
									'status'            => 'critical',
									'status_label'      => 'Issue',
									'status_icon_class' => 'bi bi-x-circle-fill',
									'count_badge'       => 2,
									'badge_status'      => 'critical',
									'explanations'      => [ 'Directory traversal protection is disabled.' ],
									'action'            => [
										'data' => [
											'zone_component_action' => 'offcanvas_zone_component_config',
											'zone_component_slug'   => 'firewall_waf_rules',
											'form_context'          => 'offcanvas',
										],
									],
								],
							],
						],
					],
				],
			],
			[
				'key'               => 'general',
				'label'             => 'General',
				'status'            => 'neutral',
				'status_icon_class' => 'bi bi-info-circle-fill',
				'settings_href'     => '/admin/zone_components/plugin_general',
				'settings_label'    => 'Configure General Settings',
				'nav_id'            => 'configure-rail-tab-general',
				'pane_id'           => 'configure-rail-pane-general',
				'target'            => '#configure-rail-pane-general',
				'controls'          => 'configure-rail-pane-general',
				'is_active'         => false,
				'panel'             => [
					'status'        => 'neutral',
					'detail_groups' => [
						[
							'status' => 'neutral',
							'rows'   => [
								[
									'title'             => 'Activity Logging',
									'summary'           => 'General logging preferences.',
									'status'            => 'neutral',
									'status_label'      => 'General',
									'status_icon_class' => 'bi bi-info-circle-fill',
									'count_badge'       => null,
									'badge_status'      => 'info',
									'explanations'      => [],
									'action'            => [],
								],
							],
						],
					],
				],
			],
		];

		return [
			'vars' => [
				'rail' => [
					'id'            => 'ConfigureRailSidebar',
					'accent_status' => 'critical',
					'items'         => [
						[
							'key'               => 'secadmin',
							'label'             => 'Security Admin',
							'status'            => 'good',
							'status_label'      => 'Good',
							'status_icon_class' => 'bi bi-check-circle-fill',
							'nav_id'            => 'configure-rail-tab-secadmin',
							'target'            => '#configure-rail-pane-secadmin',
							'controls'          => 'configure-rail-pane-secadmin',
							'is_active'         => false,
						],
						[
							'key'               => 'firewall',
							'label'             => 'Firewall',
							'status'            => 'critical',
							'status_label'      => 'Critical',
							'status_icon_class' => 'bi bi-x-circle-fill',
							'nav_id'            => 'configure-rail-tab-firewall',
							'target'            => '#configure-rail-pane-firewall',
							'controls'          => 'configure-rail-pane-firewall',
							'is_active'         => true,
						],
						[
							'key'               => 'general',
							'label'             => 'General',
							'status'            => 'info',
							'status_label'      => 'General',
							'status_icon_class' => 'bi bi-info-circle-fill',
							'nav_id'            => 'configure-rail-tab-general',
							'target'            => '#configure-rail-pane-general',
							'controls'          => 'configure-rail-pane-general',
							'is_active'         => false,
						],
					],
				],
				'zone_tiles' => $zoneTiles,
			],
		];
	}

	public function testConfigureRailTemplateCompiles() :void {
		try {
			$template = $this->twig()->load( '/wpadmin/components/configure/configure_rail.twig' );
		}
		catch ( \Throwable $e ) {
			$this->fail(
				\sprintf(
					'Failed compiling %s. %s: %s',
					'/wpadmin/components/configure/configure_rail.twig',
					\get_class( $e ),
					$e->getMessage()
				)
			);
			return;
		}

		$this->assertInstanceOf( TemplateWrapper::class, $template );
	}

	public function testConfigureRailTemplateRendersRailAndExpandableContracts() :void {
		$html = $this->twig()->render(
			'/wpadmin/components/configure/configure_rail.twig',
			$this->buildRenderContext()
		);
		$xpath = $this->createDomXPathFromHtml( $html );

		$this->assertXPathExists(
			$xpath,
			'//*[@data-shield-rail-scope="1"]',
			'Configure rail should render the scoped rail layout'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-shield-rail-target="firewall" and @data-bs-toggle="tab" and @aria-selected="true"]',
			'Configure rail should render the active Bootstrap rail trigger'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-shield-rail-target="firewall"]//*[contains(concat(" ", normalize-space(@class), " "), " shield-rail-sidebar__pip ")]',
			'Configure rail should keep the status pip when no explicit icon is supplied'
		);
		$this->assertSame(
			0,
			$xpath->query( '//*[@data-shield-rail-target="firewall"]//*[contains(concat(" ", normalize-space(@class), " "), " shield-rail-sidebar__icon ")]' )->length,
			'Configure rail should not render the scan-tab icon treatment'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-shield-rail-scope="1"]//*[contains(concat(" ", normalize-space(@class), " "), " tab-content ")]/*[@data-shield-rail-pane="firewall"]',
			'Configure rail should render zone panes inside a Bootstrap tab-content container'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-shield-rail-pane="firewall" and contains(concat(" ", normalize-space(@class), " "), " active ")]',
			'Configure rail should keep the active pane in sync with the active trigger'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-shield-rail-pane="firewall"]//*[@data-shield-expand-trigger="1" and @data-shield-expand-target="cfg-expand-firewall-0-0"]',
			'Configurable rows should render an expand trigger'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-shield-rail-pane="firewall"]//*[@data-configure-expand-ajax="1"]',
			'Configurable rows should carry the AJAX placeholder contract'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-shield-rail-pane="general"]//a[contains(concat(" ", normalize-space(@class), " "), " configure-landing__panel-cta ") and @data-configure-zone-settings="general"]',
			'Configure rail should render the shared Configure CTA inside the zone pane'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-shield-rail-pane="secadmin"]//a[contains(concat(" ", normalize-space(@class), " "), " configure-landing__panel-cta ") and @data-zone_component_action="offcanvas_zone_component_config"]',
			'Configure rail should preserve settings action data on CTA actions'
		);
	}
}
