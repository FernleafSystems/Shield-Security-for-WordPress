<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;

class PageDrillDownTestLanding extends PageDrillDownLandingBase {

	public const SLUG = 'plugin_admin_page_drill_down_test';
	public const TEMPLATE = '/wpadmin/plugin_pages/inner/drill_down_test.twig';

	protected function getLandingTitle() :string {
		return __( 'Drill-Down Test', 'wp-simple-firewall' );
	}

	protected function getLandingSubtitle() :string {
		return __( 'Temporary verification page for the drill-down base class.', 'wp-simple-firewall' );
	}

	protected function getLandingIcon() :string {
		return 'shield-shaded';
	}

	protected function getLandingMode() :string {
		return PluginNavs::MODE_ACTIONS;
	}

	protected function getLayers() :array {
		return [
			'not-a-layer',
			[
				'label' => 'Skip this layer',
			],
			[
				'key'          => 'Layer One',
				'label'        => 'Overview',
				'badge'        => '3 items',
				'badge_status' => 'critical',
				'body'         => '<div class="p-3"><h4>Layer 1</h4><p>Overview content.</p></div>',
				'context'      => [
					'path'      => [ ' Start ', '', 'Queue ' ],
					'focus'     => '  Focus on the highest-priority bucket.  ',
					'next_step' => '  Choose the first area to inspect.  ',
				],
			],
			[
				'key'          => 'bucket_detail',
				'label'        => 'Bucket Detail',
				'badge'        => 'Review',
				'badge_status' => 'warning',
				'body'         => '<div class="p-3"><h4>Layer 2</h4><p>Bucket detail content.</p></div>',
				'context'      => [
					'path'      => [ 'Start', 'Queue', ' Bucket ' ],
					'focus'     => '  Narrow the queue to a specific group.  ',
					'next_step' => '  Open the next layer for a concrete item.  ',
				],
			],
			[
				'key'          => 'Final Detail',
				'label'        => 'Item Detail',
				'badge'        => 'Ready',
				'badge_status' => 'unknown-status',
				'body'         => '<div class="p-3"><h4>Layer 3</h4><p>Item detail content.</p></div>',
				'context'      => [
					'path'      => [ 'Start', 'Queue', 'Bucket', ' Item ' ],
					'focus'     => '  Review the selected item.  ',
					'next_step' => '  Take the specific recommended action.  ',
				],
			],
			[
				'key'          => 'fourth_layer',
				'label'        => 'Dropped Layer',
				'badge'        => 'Skip',
				'badge_status' => 'good',
				'body'         => '<div>Should not render.</div>',
				'context'      => [
					'path'      => [ 'Dropped' ],
					'focus'     => 'Should not render.',
					'next_step' => 'Should not render.',
				],
			],
		];
	}

	protected function getActiveLayerIndex() :int {
		return (int)$this->getTextInputFromRequestOrActionData( 'layer', '0' );
	}
}
