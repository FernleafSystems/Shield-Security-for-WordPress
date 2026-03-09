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
			'/wpadmin_pages/insights/scans/results/scan_results_pane_body.twig',
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

	public function testRailScanResultsTemplateDerivesCriticalAccentAndHiddenPanes() :void {
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
			'//*[@data-shield-rail-target="summary" and @role="button" and @tabindex="0" and @aria-current="true"]',
			'Summary rail item should be keyboard focusable and active by default'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-shield-rail-target="summary"]//*[contains(concat(" ", normalize-space(@class), " "), " shield-rail-sidebar__pip--critical ")]',
			'Summary rail item should inherit the worst critical status'
		);
		$this->assertXPathExists(
			$xpath,
			'//*[@data-shield-rail-pane="plugins" and contains(@style, "display: none")]',
			'Inactive rail panes should render hidden by default'
		);
	}
}
