<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

class PageDocs extends BasePluginAdminPage {

	use Actions\Traits\SecurityAdminNotRequired;

	public const SLUG = 'admin_plugin_page_docs';
	public const TEMPLATE = '/wpadmin/plugin_pages/inner/docs.twig';

	protected function getRenderData() :array {
		$con = self::con();
		return [
			'content' => [
				'tab_changelog' => $con->action_router->render( Actions\Render\Components\Docs\Changelog::SLUG ),
				'tab_events'    => $con->action_router->render( Actions\Render\Components\Docs\EventsEnum::SLUG ),
			],
			'flags'   => [
				'show_free_trial' => !$con->isPremiumActive(),
			],
			'hrefs'   => [
				'free_trial'    => 'https://shsec.io/shieldfreetrialinplugin',
				'knowledgebase' => 'https://help.getshieldsecurity.com',
			],
			'imgs'    => [
				'inner_page_title_icon' => self::con()->svgs->raw( 'book-half' ),
			],
			'strings' => [
				'tab_changelog'     => __( 'Updates and Changes', 'wp-simple-firewall' ),
				'tab_knowledgebase' => __( 'Knowledgebase', 'wp-simple-firewall' ),
				'tab_events'        => __( 'Event Details', 'wp-simple-firewall' ),
				'tab_freetrial'     => __( 'Free Trial', 'wp-simple-firewall' ),
			],
		];
	}
}