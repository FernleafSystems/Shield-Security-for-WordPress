<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Enum;

/**
 * The parts of the plugin a site owner can choose to show in the WordPress dashboard ("dashboard_display").
 */
class EnumDashboardDisplay {

	public const NOTICES = 'notices';
	public const ADMIN_BAR = 'admin_bar';
	public const DASHBOARD_WIDGET = 'dashboard_widget';
	public const ADMIN_MENU = 'admin_menu';
}
