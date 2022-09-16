<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\Lib\MeterAnalysis\Handler;

class PageOverview extends BasePluginAdminPage {

	const SLUG = 'admin_plugin_page_overview';

	protected function getDefaults() :array {
		return [
			'primary_mod_slug' => 'plugin',
			'template'         => '/wpadmin_pages/insights/overview/index.twig',
		];
	}

	protected function getRenderData() :array {
		return [
			'content' => [
				'progress_meters' => ( new Handler() )
					->setMod( $this->getMod() )
					->renderDashboardMeters(),
			],
			'strings' => [
				'click_clear_filter' => __( 'Click To Filter By Security Area or Status', 'wp-simple-firewall' ),
				'clear_filter'       => __( 'Clear Filter', 'wp-simple-firewall' ),
				'go_to_options'      => sprintf(
					__( 'Go To %s', 'wp-simple-firewall' ),
					__( 'Options' )
				),
			],
		];
	}
}