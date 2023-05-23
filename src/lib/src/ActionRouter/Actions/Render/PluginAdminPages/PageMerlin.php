<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionException;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Merlin\MerlinController;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Merlin\Wizards;

class PageMerlin extends BasePluginAdminPage {

	public const SLUG = 'admin_plugin_page_merlin';
	public const TEMPLATE = '/components/merlin/container.twig';

	/**
	 * TODO: Fix 1: show_sidebar_nav
	 * TODO: Fix 2: $subNavSection
	 */
	protected function getRenderData() :array {
		try {
			$steps = ( new MerlinController() )->buildSteps( $this->action_data[ 'nav_sub' ] );
		}
		catch ( \Exception $ae ) {
			throw new ActionException( $ae->getMessage() );
		}
		return [
			'content' => [
				'steps' => $steps
			],
			'flags'   => [
				'show_sidebar_nav' => 0
			],
		];
	}
}