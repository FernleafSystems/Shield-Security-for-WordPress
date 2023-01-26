<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\ActivityLogTableAction;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build\ForActivityLog;

class PageActivityLogTable extends BasePluginAdminPage {

	public const SLUG = 'page_admin_plugin_activity_log_table';
	public const PRIMARY_MOD = 'audit_trail';
	public const TEMPLATE = '/wpadmin_pages/insights/plugin_admin/table_activity.twig';

	protected function getRenderData() :array {
		$con = $this->getCon();
		return [
			'ajax'    => [
				'logtable_action' => ActionData::BuildJson( ActivityLogTableAction::SLUG ),
			],
			'hrefs'   => [
				'inner_page_config' => $con->plugin_urls->offCanvasConfigRender( $this->primary_mod->getSlug() ),
			],
			'strings' => [
				'inner_page_title'    => __( 'Users, Visitors & Bots Activity', 'wp-simple-firewall' ),
				'inner_page_subtitle' => __( 'Track and monitor activity on your site by user, visitors and bots.', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'datatables_init' => ( new ForActivityLog() )
					->setMod( $this->primary_mod )
					->build()
			],
		];
	}
}