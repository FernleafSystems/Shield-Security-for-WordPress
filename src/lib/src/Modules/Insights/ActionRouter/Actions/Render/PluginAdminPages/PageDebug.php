<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Debug\Collate;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Debug\RecentEvents;

class PageDebug extends BasePluginAdminPage {

	const SLUG = 'admin_plugin_page_debug';

	protected function getDefaults() :array {
		return [
			'primary_mod_slug' => 'plugin',
			'template'         => '/wpadmin_pages/insights/plugin_admin/debug.twig',
		];
	}

	protected function getRenderData() :array {
		return [
			'strings' => [
				'page_title' => sprintf( __( '%s Debug Page' ), $this->getCon()->getHumanName() )
			],
			'hrefs'   => [
				'check_visitor_ip_source' => add_query_arg( [ 'shield_check_ip_source' => '1' ] ),
			],
			'vars'    => [
				'debug_data' => ( new Collate() )
					->setMod( $this->getMod() )
					->run()
			],
			'content' => [
				'recent_events' => ( new RecentEvents() )
					->setMod( $this->getMod() )
					->build(),
			]
		];
	}
}