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

	public function testSimpleTableSupportsIconOnlyPrimaryActionsAndEmptyPrimaryActions() :void {
		$html = $this->twig()->render( '/wpadmin/components/page/detail_expansion_simple_table.twig', [
			'table' => [
				'columns' => [
					'item'    => 'Item',
					'details' => 'Details',
					'action'  => 'Action',
				],
				'rows'    => [
					[
						'title'      => 'Inactive Plugin',
						'subtitle'   => 'Plugin is currently inactive',
						'context'    => 'Version: 1.0.0',
						'identifier' => 'inactive-plugin/inactive-plugin.php',
						'is_ignored' => true,
						'ignored_label' => 'Currently ignored',
						'action'     => [
							'href'         => '/wp-admin/plugins.php?s=inactive-plugin%2Finactive-plugin.php',
							'label'        => 'Manage this plugin',
							'icon'         => 'bi bi-arrow-right-circle-fill',
							'tooltip'      => 'Manage this plugin',
							'target'       => '_blank',
							'is_icon_only' => true,
						],
						'secondary_actions' => [],
					],
					[
						'title'      => 'Inactive Theme',
						'subtitle'   => 'Theme is currently inactive',
						'context'    => 'Version: 1.0.0',
						'identifier' => 'inactive-theme',
						'ignored_label' => '',
						'action'     => [],
						'secondary_actions' => [],
					],
				],
				'empty_text' => 'No items are currently available.',
			],
		] );
		$xpath = $this->createDomXPathFromHtml( $html );

		$this->assertSame(
			1,
			$xpath->query( '//table[contains(concat(" ", normalize-space(@class), " "), " actions-landing__maintenance-table ")]' )->length,
			'Simple tables should expose the maintenance table styling hook'
		);
		$this->assertSame(
			1,
			$xpath->query( '//a[@href="/wp-admin/plugins.php?s=inactive-plugin%2Finactive-plugin.php" and @target="_blank" and @data-bs-title and contains(concat(" ", normalize-space(@class), " "), " actions-landing__table-icon-action ")]' )->length,
			'Icon-only maintenance primary actions should render tooltip-enabled icon buttons'
		);
		$this->assertSame(
			1,
			$xpath->query( '//tr[@data-actions-queue-maintenance-ignored="1"]//*[contains(concat(" ", normalize-space(@class), " "), " actions-landing__maintenance-ignored-badge ")]' )->length,
			'Ignored maintenance rows should render the PHP-provided ignored label badge'
		);
		$this->assertSame(
			1,
			$xpath->query( '//a[@href="/wp-admin/plugins.php?s=inactive-plugin%2Finactive-plugin.php"]//*[contains(concat(" ", normalize-space(@class), " "), " visually-hidden ") and normalize-space()!=""]' )->length,
			'Icon-only maintenance primary actions should keep an accessible text label'
		);
		$this->assertSame(
			0,
			$xpath->query( '//tr[td//*[normalize-space()="Inactive Theme"]]//a' )->length,
			'Rows without a primary maintenance action should not render an empty button'
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
