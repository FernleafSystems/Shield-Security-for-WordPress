<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionException;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Merlin\MerlinController;

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
					'steps' => ( new MerlinController() )->buildSteps( $this->action_data[ 'nav_sub' ] )
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