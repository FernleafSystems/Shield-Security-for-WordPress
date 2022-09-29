<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions;

class PageDocs extends BasePluginAdminPage {

	use Actions\Traits\SecurityAdminNotRequired;

	const SLUG = 'admin_plugin_page_docs';
	const PRIMARY_MOD = 'insights';
	const TEMPLATE = '/wpadmin_pages/insights/docs/index.twig';

	protected function getRenderData() :array {
		$actionRouter = $this->getCon()
							 ->getModule_Insights()
							 ->getActionRouter();
		return [
			'content' => [
				'tab_updates' => $actionRouter->render( Actions\Render\Components\Docs\DocsChangelog::SLUG ),
				'tab_events'  => $actionRouter->render( Actions\Render\Components\Docs\DocsEvents::SLUG ),
			],
			'flags'   => [
				'is_pro' => $this->getCon()->isPremiumActive(),
			],
			'hrefs'   => [
				'free_trial' => 'https://shsec.io/shieldfreetrialinplugin',
			],
			'strings' => [
				'tab_updates'   => __( 'Updates and Changes', 'wp-simple-firewall' ),
				'tab_events'    => __( 'Event Details', 'wp-simple-firewall' ),
				'tab_freetrial' => __( 'Free Trial', 'wp-simple-firewall' ),
			],
		];
	}
}