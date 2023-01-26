<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Meters\ProgressMeters;

class PageOverview extends BasePluginAdminPage {

	public const SLUG = 'admin_plugin_page_overview';
	public const PRIMARY_MOD = 'plugin';
	public const TEMPLATE = '/wpadmin_pages/insights/plugin_admin/overview.twig';

	protected function getRenderData() :array {
		$con = $this->getCon();
		return [
			'content' => [
				'progress_meters' => $con->action_router->render( ProgressMeters::SLUG ),
			],
			'strings' => [
				'inner_page_title'    => __( 'Security Overview', 'wp-simple-firewall' ),
				'inner_page_subtitle' => __( 'View your entire WordPress site security at a glance and discover where you can improve.', 'wp-simple-firewall' ),

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