<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\Merlin\MerlinController;

class PageMerlin extends BasePluginAdminPage {

	const SLUG = 'admin_plugin_page_merlin';

	protected function getDefaults() :array {
		return [
			'primary_mod_slug' => 'insights',
			'template'         => '/components/merlin/container.twig',
		];
	}

	/**
	 * TODO: Fix 1: show_sidebar_nav
	 * TODO: Fix 2: $subNavSection
	 */
	protected function getRenderData() :array {
		return [
			'content' => [
				'steps' => ( new MerlinController() )
					->setMod( $this->primary_mod )
					->buildSteps( empty( $subNavSection ) ? 'guided_setup_wizard' : $subNavSection )
			],
			'flags'   => [
				'show_sidebar_nav' => 0
			],
		];
	}
}