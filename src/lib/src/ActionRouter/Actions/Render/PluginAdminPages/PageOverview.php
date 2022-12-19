<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Meters\ProgressMeters;

class PageOverview extends BasePluginAdminPage {

	public const SLUG = 'admin_plugin_page_overview';
	public const PRIMARY_MOD = 'plugin';
	public const TEMPLATE = '/wpadmin_pages/insights/overview/index.twig';

	protected function getRenderData() :array {
		return [
			'content' => [
				'progress_meters' => $this->getCon()->action_router->render( ProgressMeters::SLUG ),
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