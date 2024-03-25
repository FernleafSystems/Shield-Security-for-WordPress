<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionException;

class PageMerlin extends BasePluginAdminPage {

	public const SLUG = 'admin_plugin_page_merlin';
	public const TEMPLATE = '/components/merlin/container.twig';

	/**
	 * @throws ActionException
	 */
	protected function getRenderData() :array {
		try {
			return [
				'content' => [
					'steps' => self::con()->comps->wizards->buildSteps( $this->action_data[ 'nav_sub' ] )
				],
				'flags'   => [
					'show_sidebar_nav' => 0
				],
			];
		}
		catch ( \Exception $ae ) {
			throw new ActionException( $ae->getMessage() );
		}
	}
}