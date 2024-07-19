<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\ActivityLogTableAction;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build\ForActivityLog;

class PageActivityLogTable extends BasePluginAdminPage {

	public const SLUG = 'page_admin_plugin_activity_log_table';
	public const TEMPLATE = '/wpadmin/plugin_pages/inner/table_activity.twig';

	protected function getPageContextualHrefs_Help() :array {
		return [
			'title'      => sprintf( '%s: %s', __( 'Help', 'wp-simple-firewall' ), __( 'Activity Log', 'wp-simple-firewall' ) ),
			'href'       => 'https://help.getshieldsecurity.com/article/238-review-your-site-activities-with-the-activity-log-viewer',
			'new_window' => true,
		];
	}

	protected function getRenderData() :array {
		return [
			'ajax'    => [
				'logtable_action' => ActionData::BuildJson( ActivityLogTableAction::class ),
			],
			'imgs'    => [
				'inner_page_title_icon' => self::con()->svgs->raw( 'person-lines-fill' ),
			],
			'strings' => [
				'inner_page_title'    => __( 'View Logs', 'wp-simple-firewall' ),
				'inner_page_subtitle' => __( 'Track and monitor activity on your site by users, visitors and bots.', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'datatables_init' => ( new ForActivityLog() )->build()
			],
		];
	}
}