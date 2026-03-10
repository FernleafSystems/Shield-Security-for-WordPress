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
		return [
			'vars' => [
				'mode_panel' => [
					'active_target' => 'firewall',
				],
				'zone_tiles' => [
					[
						'key'            => 'secadmin',
						'label'          => 'Security Admin',
						'status'         => 'good',
						'settings_href'  => '/admin/zones/secadmin',
						'settings_label' => 'Configure Security Admin Settings',
						'panel'          => [
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
						'key'            => 'firewall',
						'label'          => 'Firewall',
						'status'         => 'critical',
						'settings_href'  => '/admin/zones/firewall',
						'settings_label' => 'Configure Firewall Settings',
						'panel'          => [
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
						'key'            => 'general',
						'label'          => 'General',
						'status'         => 'neutral',
						'settings_href'  => '/admin/zone_components/plugin_general',
						'settings_label' => 'Configure General Settings',
						'panel'          => [
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
				],
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
			'//*[contains(concat(" ", normalize-space(@class), " "), " shield-rail-sidebar__accent--critical ")]',
			'Configure rail should derive a critical accent from the worst zone status'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-shield-rail-target="firewall" and contains(concat(" ", normalize-space(@class), " "), " is-active ")]',
			'Explicit active target should activate the matching rail item'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-shield-rail-pane="secadmin" and contains(@style, "display: none")]',
			'Inactive panes should render hidden'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-shield-rail-target="general"]//*[contains(concat(" ", normalize-space(@class), " "), " shield-rail-sidebar__pip--info ")]',
			'Neutral zones should map to info in the rail UI'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-shield-rail-pane="firewall"]//*[@data-shield-expand-trigger="1" and @data-shield-expand-target="cfg-expand-firewall-0-0"]',
			'Configurable rows should render an expand trigger'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-shield-rail-pane="firewall"]//*[@data-configure-expand-ajax="1" and @data-zone_component_action="offcanvas_zone_component_config" and @data-zone_component_slug="firewall_waf_rules"]',
			'Configurable rows should carry the AJAX placeholder contract'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-shield-rail-pane="firewall"]//*[contains(concat(" ", normalize-space(@class), " "), " shield-detail-row__gear-icon ")]',
			'Configurable rows should show the gear icon'
		);
		$this->assertSame(
			0,
			$xpath->query( '//*[@data-shield-rail-pane="secadmin"]//*[@data-configure-expand-ajax="1"]' )->length,
			'Non-configurable rows should not render AJAX placeholders'
		);
		$this->assertSame(
			0,
			$xpath->query( '//*[@data-shield-rail-pane="general"]//*[contains(concat(" ", normalize-space(@class), " "), " shield-detail-row__gear-icon ")]' )->length,
			'Non-configurable rows should not render gear icons'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-shield-rail-pane="general"]//*[contains(concat(" ", normalize-space(@class), " "), " shield-detail-row__status-icon--info ")]',
			'Neutral rows should map to info in the detail UI'
		);
	}
}
