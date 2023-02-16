<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Merlin\MerlinController;

class PageMerlin extends BasePluginAdminPage {

	public const SLUG = 'admin_plugin_page_merlin';
	public const TEMPLATE = '/components/merlin/container.twig';

	/**
	 * TODO: Fix 1: show_sidebar_nav
	 * TODO: Fix 2: $subNavSection
	 */
	protected function getRenderData() :array {
		return [
			'content' => [
				'steps' => ( new MerlinController() )
					->buildSteps( empty( $subNavSection ) ? 'guided_setup_wizard' : $subNavSection )
			],
			'flags'   => [
				'show_sidebar_nav' => 0
			],
		];
	}
}