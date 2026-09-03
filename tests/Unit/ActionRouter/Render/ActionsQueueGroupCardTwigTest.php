<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PluginPathsTrait;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use Twig\{
	Environment,
	Loader\FilesystemLoader
};

class ActionsQueueGroupCardTwigTest extends BaseUnitTest {

	use PluginPathsTrait;

	public function test_group_card_exposes_neutral_key_and_keeps_static_outers_non_drilling() :void {
		$category = $this->renderGroup( [
			'key'             => 'maintenance',
			'card_type'       => 'category',
			'icon_class'      => 'bi bi-wrench',
			'label'           => 'Maintenance',
			'management_link' => [],
			'maintenance_rows' => [],
			'summary_row'     => [],
			'status'          => 'good',
			'is_interactive'  => false,
			'is_pro_upsell'   => false,
		] );
		$linked = $this->renderGroup( [
			'key'             => 'vulnerabilities',
			'card_type'       => 'linked',
			'icon_class'      => 'bi bi-shield-fill-exclamation',
			'label'           => 'Vulnerabilities',
			'status'          => 'good',
			'status_label'    => 'Good',
			'narrative'       => 'No vulnerable assets remain.',
			'drill_hint'      => '',
			'is_interactive'  => false,
			'is_pro_upsell'   => false,
			'links'           => [ [
				'label'      => 'Open details',
				'href'       => '#details',
				'target'     => '',
				'rel'        => '',
				'icon_class' => '',
			] ],
		] );
		$xpath = $this->xpath( $category.$linked );

		$this->assertSame( 1, $xpath->query( '//*[@data-actions-queue-group-key="maintenance"]' )->length );
		$this->assertSame(
			1,
			$xpath->query( '//div[@data-actions-queue-group-key="vulnerabilities" and not(@data-drill-target) and not(@data-drill-bucket-selection) and not(@data-drill-group-selection)]' )->length
		);
		$this->assertSame(
			1,
			$xpath->query( '//div[@data-actions-queue-group-key="vulnerabilities"]//a[@href="#details"]' )->length
		);
	}

	public function test_tile_card_keeps_non_actions_queue_callers_unchanged_when_the_group_key_is_absent() :void {
		$html = $this->twig()->render( '/wpadmin/components/operator/tile_card.twig', [
			'tile' => [
				'tag'                              => 'div',
				'status'                           => 'good',
				'class_name'                       => '',
				'icon_class'                       => 'bi bi-shield',
				'title'                            => 'Security Admin',
				'status_label'                     => 'Good',
				'oneliner'                         => '',
				'action_label'                     => '',
				'footer_links'                     => [],
				'data_drill_target'                => '',
				'data_drill_zone_selection'        => '',
				'data_drill_bucket_selection'      => '',
				'data_drill_group_selection'       => '',
				'data_reports_workspace_selection' => '',
				'is_disabled'                      => false,
			],
		] );

		$this->assertSame( 0, $this->xpath( $html )->query( '//*[@data-actions-queue-group-key]' )->length );
	}

	public function test_pro_upsell_group_is_a_launcher_without_drill_attributes() :void {
		$upsell = $this->renderGroup( [
			'key'             => 'malware',
			'card_type'       => 'expandable',
			'icon_class'      => 'bi bi-bug-fill',
			'label'           => 'MAL{ai} malware scanning',
			'status'          => 'neutral',
			'status_label'    => 'Upgrade Required',
			'narrative'       => 'MAL{ai} malware scanning requires an upgrade.',
			'drill_hint'      => 'Explore malware findings',
			'is_interactive'  => true,
			'is_pro_upsell'   => true,
			'links'           => [],
			'selection'       => [ 'selection_json' => '{"key":"malware"}' ],
		] );
		$ordinary = $this->renderGroup( [
			'key'             => 'wordpress',
			'card_type'       => 'expandable',
			'icon_class'      => 'bi bi-wordpress',
			'label'           => 'WordPress Core File Scanning',
			'status'          => 'neutral',
			'status_label'    => 'Not Enabled',
			'narrative'       => 'WordPress Core File Scanning is not enabled.',
			'drill_hint'      => 'Explore WordPress Core File Scanning',
			'is_interactive'  => true,
			'is_pro_upsell'   => false,
			'links'           => [],
			'selection'       => [ 'selection_json' => '{"key":"wordpress"}' ],
		] );
		$xpath = $this->xpath( $upsell.$ordinary );

		$this->assertSame(
			1,
			$xpath->query( '//button[@data-actions-queue-group-key="malware" and @data-actions-queue-pro-upsell="1" and not(@data-drill-target) and not(@data-drill-bucket-selection) and not(@data-drill-group-selection)]' )->length
		);
		$this->assertSame(
			1,
			$xpath->query( '//button[@data-actions-queue-group-key="wordpress" and @data-drill-target="detail" and @data-drill-bucket-selection and @data-drill-group-selection and not(@data-actions-queue-pro-upsell)]' )->length
		);
	}

	/**
	 * @param array<string,mixed> $group
	 */
	private function renderGroup( array $group ) :string {
		return $this->twig()->render( '/wpadmin/components/actions_queue/group_card.twig', [
			'group' => $group,
			'bucket_selection' => [ 'key' => 'critical', 'selection_json' => '{}' ],
			'is_healthy' => true,
		] );
	}

	private function twig() :Environment {
		return new Environment( new FilesystemLoader( $this->getPluginFilePath( 'templates/twig' ) ), [
			'cache'            => false,
			'debug'            => false,
			'strict_variables' => true,
		] );
	}

	private function xpath( string $html ) :\DOMXPath {
		$dom = new \DOMDocument();
		$previous = \libxml_use_internal_errors( true );
		try {
			$dom->loadHTML( '<?xml encoding="utf-8" ?><div>'.$html.'</div>', \LIBXML_HTML_NOIMPLIED | \LIBXML_HTML_NODEFDTD );
		}
		finally {
			\libxml_clear_errors();
			\libxml_use_internal_errors( $previous );
		}

		return new \DOMXPath( $dom );
	}
}
