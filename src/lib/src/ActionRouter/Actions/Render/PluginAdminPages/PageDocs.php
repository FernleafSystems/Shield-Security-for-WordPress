<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

class PageDocs extends BasePluginAdminPage {

	use Actions\Traits\SecurityAdminNotRequired;

	public const SLUG = 'admin_plugin_page_docs';
	public const PRIMARY_MOD = 'insights';
	public const TEMPLATE = '/wpadmin_pages/insights/docs/index.twig';

	protected function getRenderData() :array {
		$con = $this->getCon();
		return [
			'content' => [
				'tab_updates' => $con->action_router->render( Actions\Render\Components\Docs\DocsChangelog::SLUG ),
				'tab_events'  => $con->action_router->render( Actions\Render\Components\Docs\DocsEvents::SLUG ),
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