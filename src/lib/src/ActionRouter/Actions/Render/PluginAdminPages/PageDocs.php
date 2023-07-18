<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

class PageDocs extends BasePluginAdminPage {

	use Actions\Traits\SecurityAdminNotRequired;

	public const SLUG = 'admin_plugin_page_docs';
	public const TEMPLATE = '/wpadmin_pages/insights/docs/index.twig';

	protected function getRenderData() :array {
		$con = $this->con();
		return [
			'content' => [
				'tab_updates' => $con->action_router->render( Actions\Render\Components\Docs\DocsChangelog::SLUG ),
				'tab_events'  => $con->action_router->render( Actions\Render\Components\Docs\DocsEvents::SLUG ),
			],
			'flags'   => [
				'show_free_trial' => !$con->isPremiumActive(),
			],
			'hrefs'   => [
				'free_trial'    => 'https://shsec.io/shieldfreetrialinplugin',
				'knowledgebase' => 'https://help.getshieldsecurity.com',
			],
			'strings' => [
				'tab_updates'       => __( 'Updates and Changes', 'wp-simple-firewall' ),
				'tab_knowledgebase' => __( 'Knowledgebase', 'wp-simple-firewall' ),
				'tab_events'        => __( 'Event Details', 'wp-simple-firewall' ),
				'tab_freetrial'     => __( 'Free Trial', 'wp-simple-firewall' ),
			],
		];
	}
}