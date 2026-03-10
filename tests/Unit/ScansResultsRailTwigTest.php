<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PluginPathsTrait;
use Twig\{
	Environment,
	Loader\FilesystemLoader,
	TemplateWrapper
};

class ScansResultsRailTwigTest extends BaseUnitTest {

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
			'vars'    => [
				'tabs'            => [
					[
						'key'       => 'summary',
						'pane_id'   => 'h-tabs-summary',
						'nav_id'    => 'h-tabs-summary-tab',
						'label'     => 'Summary',
						'count'     => 3,
						'is_active' => true,
						'target'    => '#h-tabs-summary',
						'controls'  => 'h-tabs-summary',
					],
					[
						'key'       => 'plugins',
						'pane_id'   => 'h-tabs-plugins',
						'nav_id'    => 'h-tabs-plugins-tab',
						'label'     => 'Plugins',
						'count'     => 2,
						'is_active' => false,
						'target'    => '#h-tabs-plugins',
						'controls'  => 'h-tabs-plugins',
					],
					[
						'key'       => 'vulnerabilities',
						'pane_id'   => 'h-tabs-vulnerabilities',
						'nav_id'    => 'h-tabs-vulnerabilities-tab',
						'label'     => 'Vulnerabilities',
						'count'     => 1,
						'is_active' => false,
						'target'    => '#h-tabs-vulnerabilities',
						'controls'  => 'h-tabs-vulnerabilities',
					],
					[
						'key'       => 'malware',
						'pane_id'   => 'h-tabs-malware',
						'nav_id'    => 'h-tabs-malware-tab',
						'label'     => 'Malware',
						'count'     => 0,
						'is_active' => false,
						'target'    => '#h-tabs-malware',
						'controls'  => 'h-tabs-malware',
					],
				],
				'rail_tabs'       => [
					[
						'key'       => 'summary',
						'pane_id'   => 'h-tabs-summary',
						'nav_id'    => 'h-tabs-summary-tab',
						'label'     => 'Summary',
						'count'     => 3,
						'status'     => 'critical',
						'icon_class' => 'bi bi-clipboard2-pulse-fill',
						'is_active'  => true,
						'target'     => '#h-tabs-summary',
						'controls'  => 'h-tabs-summary',
						'items'     => [
							[
								'title'       => 'Plugins',
								'description' => '2 plugin files need review.',
								'status'      => 'warning',
								'count_badge' => 2,
								'actions'     => [
									[
										'type'  => 'navigate',
										'label' => 'Investigate',
										'href'  => '/investigate/plugin',
										'icon'  => 'bi bi-arrow-right-circle-fill',
									],
								],
							],
						],
					],
					[
						'key'       => 'plugins',
						'pane_id'   => 'h-tabs-plugins',
						'nav_id'    => 'h-tabs-plugins-tab',
						'label'     => 'Plugins',
						'count'     => 2,
						'status'     => 'warning',
						'icon_class' => 'bi bi-plug-fill',
						'is_active'  => false,
						'target'     => '#h-tabs-plugins',
						'controls'  => 'h-tabs-plugins',
						'items'     => [
							[
								'title'       => 'Example Plugin',
								'description' => '2 file modifications need review.',
								'status'      => 'warning',
								'count_badge' => 2,
								'actions'     => [
									[
										'type'  => 'navigate',
										'label' => 'Investigate',
										'href'  => '/investigate/plugin',
										'icon'  => 'bi bi-arrow-right-circle-fill',
									],
								],
							],
						],
					],
					[
						'key'       => 'vulnerabilities',
						'pane_id'   => 'h-tabs-vulnerabilities',
						'nav_id'    => 'h-tabs-vulnerabilities-tab',
						'label'     => 'Vulnerabilities',
						'count'     => 1,
						'status'     => 'critical',
						'icon_class' => 'bi bi-shield-exclamation',
						'is_active'  => false,
						'target'     => '#h-tabs-vulnerabilities',
						'controls'  => 'h-tabs-vulnerabilities',
						'items'     => [
							[
								'title'         => 'Example Plugin',
								'description'   => '1 known vulnerability needs review.',
								'status'        => 'critical',
								'count_badge'   => 1,
								'section_label' => 'Known Vulnerabilities',
								'actions'       => [
									[
										'type'  => 'navigate',
										'label' => 'Investigate',
										'href'  => '/investigate/plugin',
										'icon'  => 'bi bi-arrow-right-circle-fill',
									],
								],
							],
						],
					],
					[
						'key'       => 'malware',
						'pane_id'   => 'h-tabs-malware',
						'nav_id'    => 'h-tabs-malware-tab',
						'label'     => 'Malware',
						'count'     => 0,
						'status'     => 'good',
						'icon_class' => 'bi bi-bug-fill',
						'is_active'  => false,
						'target'     => '#h-tabs-malware',
						'controls'  => 'h-tabs-malware',
						'items'     => [],
					],
				],
				'summary_rows'    => [
					[
						'severity'    => 'warning',
						'label'       => 'Plugins',
						'description' => '2 plugin files need review.',
						'count'       => 2,
					],
				],
				'assessment_rows' => [],
				'vulnerabilities' => [
					'count'    => 1,
					'status'   => 'critical',
					'sections' => [
						[
							'label' => 'Known Vulnerabilities',
							'items' => [
								[
									'severity'    => 'critical',
									'label'       => 'Example Plugin',
									'description' => '1 known vulnerability needs review.',
									'count'       => 1,
									'cta'         => [
										'href'  => '/investigate/plugin',
										'label' => 'Investigate',
									],
								],
							],
						],
					],
				],
			],
			'content' => [
				'section' => [
					'plugins'    => '<div id="PluginsPaneBody">Plugins content</div>',
					'malware'    => '<div id="MalwarePaneBody">Malware content</div>',
					'wordpress'  => '',
					'themes'     => '',
					'filelocker' => '',
				],
			],
		];
	}

	public function testScanResultsTemplatesCompileWithTwigParser() :void {
		$twig = $this->twig();

		foreach ( [
			'/wpadmin_pages/insights/scans/results/scan_results.twig',
			'/wpadmin_pages/insights/scans/results/scan_results_rail.twig',
			'/wpadmin_pages/insights/scans/results/scan_results_rail_pane.twig',
			'/wpadmin_pages/insights/scans/results/scan_results_pane_body.twig',
			'/wpadmin_pages/insights/scans/results/scan_file_table.twig',
		] as $templatePath ) {
			try {
				$template = $twig->load( $templatePath );
			}
			catch ( \Throwable $e ) {
				$this->fail(
					\sprintf(
						'Failed compiling %s. %s: %s',
						$templatePath,
						\get_class( $e ),
						$e->getMessage()
					)
				);
				return;
			}

			$this->assertInstanceOf( TemplateWrapper::class, $template );
		}
	}

	public function testBootstrapScanResultsTemplateKeepsLegacyTabWrapper() :void {
		$html = $this->twig()->render(
			'/wpadmin_pages/insights/scans/results/scan_results.twig',
			$this->buildRenderContext()
		);
		$xpath = $this->createDomXPathFromHtml( $html );

		$this->assertXPathExists(
			$xpath,
			'//*[@id="ScanResultsTabsNav"]',
			'Bootstrap scans results template should keep the legacy tab nav'
		);
		$this->assertSame(
			0,
			$xpath->query( '//*[@data-shield-rail-scope="1"]' )->length,
			'Bootstrap scans results template should not render the rail scope marker'
		);
	}

	public function testRailScanResultsTemplateDerivesCriticalAccentAndBootstrapTabContracts() :void {
		$html = $this->twig()->render(
			'/wpadmin_pages/insights/scans/results/scan_results_rail.twig',
			$this->buildRenderContext()
		);
		$xpath = $this->createDomXPathFromHtml( $html );

		$this->assertXPathExists(
			$xpath,
			'//*[contains(concat(" ", normalize-space(@class), " "), " shield-rail-sidebar__accent--critical ")]',
			'Rail scans results template should derive a critical accent from vulnerability findings'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-shield-rail-scope="1"]',
			'Rail scans results template should render a scoped rail layout'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-shield-rail-target="summary" and @data-bs-toggle="tab" and @role="tab" and @aria-selected="true"]',
			'Summary rail item should render a Bootstrap tab trigger'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-shield-rail-target="summary"]//*[contains(concat(" ", normalize-space(@class), " "), " shield-rail-sidebar__pip--critical ")]',
			'Summary rail item should inherit the worst critical status'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-shield-rail-scope="1"]//ul[@role="tablist" and @aria-orientation="vertical"]',
			'Rail scans results template should render a vertical Bootstrap tablist'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-shield-rail-scope="1"]//*[contains(concat(" ", normalize-space(@class), " "), " tab-content ")]/*[@data-shield-rail-pane="summary"]',
			'Rail scans results panes should render inside a Bootstrap tab-content container'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-shield-rail-pane="plugins" and contains(concat(" ", normalize-space(@class), " "), " tab-pane ") and not(contains(concat(" ", normalize-space(@class), " "), " active "))]',
			'Inactive rail panes should render as Bootstrap tab panes'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[contains(concat(" ", normalize-space(@class), " "), " shield-mode-strip__eyebrow ") and normalize-space()="Known Vulnerabilities"]',
			'Rail pane should render section headers for grouped vulnerability items'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-shield-rail-pane="malware"]//*[contains(concat(" ", normalize-space(@class), " "), " shield-scan-pane-empty ")]',
			'Clean rail panes should render an empty-state message'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[contains(concat(" ", normalize-space(@class), " "), " shield-pane-header ")]',
			'Rail panes should render a pane header with icon and title'
		);
	}
}
