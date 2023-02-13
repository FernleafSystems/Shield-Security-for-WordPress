<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\ActivityLogTableAction;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build\ForActivityLog;

class PageActivityLogTable extends BasePluginAdminPage {

	public const SLUG = 'page_admin_plugin_activity_log_table';
	public const TEMPLATE = '/wpadmin_pages/plugin_admin/table_activity.twig';

	protected function getPageContextualHrefs() :array {
		$con = $this->getCon();
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
			'strings' => [
				'inner_page_title'    => __( 'Users, Visitors & Bots Activity', 'wp-simple-firewall' ),
				'inner_page_subtitle' => __( 'Track and monitor activity on your site by users, visitors and bots.', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'datatables_init' => ( new ForActivityLog() )
					->setMod( $this->getCon()->getModule_AuditTrail() )
					->build()
			],
		];
	}
}