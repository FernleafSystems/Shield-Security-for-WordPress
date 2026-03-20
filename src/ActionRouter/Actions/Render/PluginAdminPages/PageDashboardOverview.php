<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;

class PageDashboardOverview extends PageModeLandingBase {

	public const SLUG = 'plugin_admin_page_dashboard_overview';
	public const TEMPLATE = '/wpadmin/plugin_pages/inner/dashboard_overview.twig';

	protected function getLandingTitle() :string {
		return __( 'Dashboard', 'wp-simple-firewall' );
	}

	protected function getLandingSubtitle() :string {
		return __( 'Review the current security picture and jump into the next operator mode.', 'wp-simple-firewall' );
	}

	protected function getLandingIcon() :string {
		return 'speedometer';
	}

	protected function getLandingMode() :string {
		return PluginNavs::NAV_DASHBOARD;
	}

	protected function getLandingAccentStatus() :string {
		return 'good';
	}

	protected function getLandingContent() :array {
		return [
			'operator_mode_landing' => self::con()->action_router->render( PageOperatorModeLanding::class ),
		];
	}

	protected function getOperatorRootStep() :array {
		return \array_replace(
			parent::getOperatorRootStep(),
			[
				'focus'     => __( 'Use the cards below to move directly into actions, investigation, configuration, or reports.', 'wp-simple-firewall' ),
				'next_step' => __( 'Open the area that needs attention next.', 'wp-simple-firewall' ),
			]
		);
	}
}
