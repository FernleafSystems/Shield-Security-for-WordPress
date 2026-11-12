<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PluginPathsTrait;
use Twig\{
	Environment,
	Loader\FilesystemLoader
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

	public function testDetailRowOnlyEmitsTooltipAttributesForNonEmptyTooltips() :void {
		$html = $this->twig()->render( '/wpadmin/components/page/shield_detail_row.twig', [
			'row' => [
				'status'  => 'warning',
				'title'   => 'Tooltip Row',
				'expandable' => true,
				'expand_target' => 'tooltip-row-expansion',
				'expand_cta_label' => 'Details',
				'expand_accessible_label' => 'Details for Tooltip Row',
				'expand_title' => 'Open details',
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
				],
			],
		] );
		$xpath = $this->createDomXPathFromHtml( $html );

		$this->assertSame(
			1,
			$xpath->query( '//*[@data-shield-expand-row="1" and @data-shield-expand-target="tooltip-row-expansion" and not(@role) and not(@tabindex) and not(@aria-controls) and not(@aria-expanded)]' )->length,
			'Expandable row wrappers should stay layout containers instead of acting as buttons'
		);
		$this->assertSame(
			1,
			$xpath->query( '//button[@type="button" and @data-shield-expand-trigger="1" and @aria-controls="tooltip-row-expansion" and @aria-expanded="false" and string-length(normalize-space(@aria-label)) > 0]' )->length,
			'Expandable rows should render a real button with a non-empty accessible collapse contract'
		);
		$this->assertSame(
			0,
			$xpath->query( '//button[@data-shield-expand-trigger="1"]//*[contains(concat(" ", normalize-space(@class), " "), " shield-action-chip ")]' )->length,
			'Nested action controls should not be descendants of the expand button'
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
	}

	public function testDetailRowRendersNonWarningExplanationsWithoutWarningIcon() :void {
		$html = $this->twig()->render( '/wpadmin/components/page/shield_detail_row.twig', [
			'row' => [
				'status'       => 'good',
				'title'        => 'Healthy Row',
				'description'  => 'Everything is configured.',
				'expandable'   => false,
				'expand_target' => '',
				'expand_cta_label' => '',
				'expand_accessible_label' => '',
				'expand_title' => '',
				'explanations' => [
					'This setting still needs a short explanation.',
					'The explanation should render without a warning icon.',
				],
			],
		] );
		$xpath = $this->createDomXPathFromHtml( $html );

		$this->assertSame(
			1,
			$xpath->query( '//*[contains(concat(" ", normalize-space(@class), " "), " shield-detail-row__explanations ")]' )->length,
			'Rows should render explanation lists whenever explanations are supplied'
		);
		$this->assertSame(
			2,
			$xpath->query( '//*[contains(concat(" ", normalize-space(@class), " "), " shield-detail-row__explanations ")]/li' )->length,
			'Rows should render each supplied explanation as its own list item'
		);
		$this->assertSame(
			0,
			$xpath->query( '//*[contains(concat(" ", normalize-space(@class), " "), " shield-detail-row__expl-icon ")]' )->length,
			'Non-warning rows should not render the warning-style explanation icon'
		);
		$this->assertSame(
			2,
			$xpath->query(
				'//*[contains(concat(" ", normalize-space(@class), " "), " shield-detail-row__explanations ")]/li[normalize-space()!=""]'
			)->length,
			'Non-warning rows should render non-empty explanation items'
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
