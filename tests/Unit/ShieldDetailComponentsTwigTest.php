<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PluginPathsTrait;
use Twig\{
	Environment,
	Loader\FilesystemLoader,
	TemplateWrapper
};

class ShieldDetailComponentsTwigTest extends BaseUnitTest {

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

	/**
	 * @return string[]
	 */
	private function templates() :array {
		return [
			'/wpadmin/components/page/shield_detail_row.twig',
			'/wpadmin/components/page/shield_detail_expansion.twig',
			'/wpadmin/components/page/detail_expansion_simple_table.twig',
			'/wpadmin/components/page/shield_rail_sidebar.twig',
			'/wpadmin/components/page/shield_rail_layout.twig',
			'/wpadmin/components/page/shield_detail_demo.twig',
		];
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

	public function testDetailComponentTemplatesCompileWithTwigParser() :void {
		$twig = $this->twig();

		foreach ( $this->templates() as $templatePath ) {
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

	public function testDetailComponentsRenderWithMinimalPayloads() :void {
		$twig = $this->twig();

		$twig->render( '/wpadmin/components/page/shield_detail_row.twig', [
			'row' => [
				'status' => 'good',
				'title'  => 'Minimal Row',
			],
		] );

		$twig->render( '/wpadmin/components/page/shield_detail_expansion.twig', [
			'expansion' => [
				'id'   => 'exp-minimal',
				'body' => '<p>Body</p>',
			],
		] );

		$twig->render( '/wpadmin/components/page/detail_expansion_simple_table.twig', [
			'table' => [
				'rows' => [],
			],
		] );

		$twig->render( '/wpadmin/components/page/shield_rail_sidebar.twig', [
			'rail' => [
				'items' => [
					[
						'key'    => 'summary',
						'label'  => 'Summary',
						'status' => 'good',
					],
				],
			],
		] );

		$twig->render( '/wpadmin/components/page/shield_rail_layout.twig', [
			'layout' => [
				'rail_html'    => '<div>Rail</div>',
				'content_html' => '<div>Content</div>',
			],
		] );

		$this->addToAssertionCount( 1 );
	}

	public function testDetailComponentsRenderWithExpandedVariantsAndDemoTemplate() :void {
		$twig = $this->twig();

		$twig->render( '/wpadmin/components/page/shield_detail_row.twig', [
			'row' => [
				'status'        => 'critical',
				'title'         => 'Complex Row',
				'description'   => 'Has actions and explanations',
				'count_badge'   => 2,
				'expandable'    => true,
				'expand_target' => 'exp-complex',
				'show_gear'     => true,
				'explanations'  => [ 'First explanation', 'Second explanation' ],
				'actions'       => [
					[
						'type'    => 'update',
						'label'   => 'Update now',
						'href'    => '#',
						'tooltip' => 'Update tooltip',
					],
					[
						'type'    => 'deactivate',
						'label'   => 'Deactivate',
						'href'    => '#',
						'tooltip' => 'Deactivate tooltip',
					],
				],
			],
		] );

		$twig->render( '/wpadmin/components/page/shield_detail_expansion.twig', [
			'expansion' => [
				'id'     => 'exp-options',
				'type'   => 'options',
				'status' => 'warning',
				'body'   => '<div>Option content</div>',
			],
		] );

		$twig->render( '/wpadmin/components/page/shield_rail_sidebar.twig', [
			'rail' => [
				'accent_status' => 'critical',
				'items'         => [
					[
						'key'       => 'summary',
						'label'     => 'Summary',
						'status'    => 'critical',
						'is_active' => true,
						'count'     => 3,
					],
					[
						'key'    => 'general',
						'label'  => 'General',
						'status' => 'neutral',
						'count'  => 1,
					],
				],
			],
		] );

		$twig->render( '/wpadmin/components/page/shield_detail_demo.twig' );

		$this->addToAssertionCount( 1 );
	}

	public function testDetailRowOnlyEmitsTooltipAttributesForNonEmptyTooltips() :void {
		$html = $this->twig()->render( '/wpadmin/components/page/shield_detail_row.twig', [
			'row' => [
				'status'  => 'warning',
				'title'   => 'Tooltip Row',
				'expandable' => true,
				'expand_target' => 'tooltip-row-expansion',
				'actions' => [
					[
						'type'    => 'update',
						'label'   => 'Has tooltip',
						'href'    => '#update',
						'tooltip' => 'Go to updates',
					],
					[
						'type'    => 'navigate',
						'label'   => 'No tooltip',
						'href'    => '#view',
						'tooltip' => '',
					],
					[
						'type'  => 'navigate',
						'label' => 'Null tooltip',
						'href'  => '#null',
					],
				],
			],
		] );
		$xpath = $this->createDomXPathFromHtml( $html );

		$this->assertSame(
			1,
			$xpath->query( '//*[@data-shield-expand-trigger="1" and @aria-controls="tooltip-row-expansion" and @aria-expanded="false"]' )->length,
			'Expandable rows should expose the Bootstrap collapse target through aria-controls'
		);
		$this->assertSame(
			1,
			$xpath->query( '//*[@href="#update" and @data-bs-toggle="tooltip"]' )->length,
			'Only actions with non-empty tooltip strings should render tooltip attributes'
		);
		$this->assertSame(
			0,
			$xpath->query( '//*[@href="#view" and @data-bs-toggle="tooltip"]' )->length,
			'Blank tooltip strings should not render tooltip attributes'
		);
		$this->assertSame(
			0,
			$xpath->query( '//*[@href="#null" and @data-bs-toggle="tooltip"]' )->length,
			'Missing tooltip values should not render tooltip attributes'
		);
	}

	public function testDetailExpansionRendersBootstrapCollapseContract() :void {
		$html = $this->twig()->render( '/wpadmin/components/page/shield_detail_expansion.twig', [
			'expansion' => [
				'id'        => 'exp-bootstrap',
				'parent_id' => 'exp-parent',
				'type'      => 'table',
				'body'      => '<table><tbody><tr><td>Example</td></tr></tbody></table>',
			],
		] );
		$xpath = $this->createDomXPathFromHtml( $html );

		$this->assertSame(
			1,
			$xpath->query( '//*[@id="exp-bootstrap" and contains(concat(" ", normalize-space(@class), " "), " collapse ") and @data-bs-parent="#exp-parent"]' )->length,
			'Detail expansions should render Bootstrap collapse classes and parent wiring'
		);
	}
}
