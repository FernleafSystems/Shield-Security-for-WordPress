<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

class PageDashboardOverview extends BasePluginAdminPage {

	public const SLUG = 'plugin_admin_page_dashboard_overview';
	public const TEMPLATE = '/wpadmin/plugin_pages/inner/dashboard_overview.twig';

	protected function getRenderData() :array {
		$con = self::con();
		return [
			'content' => [
				'operator_mode_landing' => $con->action_router->render( PageOperatorModeLanding::class ),
			],
			'imgs'    => [
				'inner_page_title_icon' => $con->svgs->iconClass( 'speedometer' ),
			],
			'strings' => [
				'inner_page_title'    => '',
				'inner_page_subtitle' => '',
			],
			'vars'    => [
				'mode_shell' => [
					'mode'            => '',
					'accent_status'   => 'good',
					'header_density'  => 'compact',
					'is_mode_landing' => true,
					'is_interactive'  => false,
				],
			],
		];
	}
}
