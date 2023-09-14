<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\ActivityLogTableAction;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build\ForActivityLog;

class PageActivityLogTable extends BasePluginAdminPage {

	public const SLUG = 'page_admin_plugin_activity_log_table';
	public const TEMPLATE = '/wpadmin/plugin_pages/inner/table_activity.twig';

	protected function getPageContextualHrefs() :array {
		$con = self::con();
		return [
			[
				'text' => __( 'Configure Activity Logging', 'wp-simple-firewall' ),
				'href' => $con->plugin_urls->offCanvasConfigRender( $con->getModule_AuditTrail()->cfg->slug ),
			]
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