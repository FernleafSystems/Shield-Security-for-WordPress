<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\ActivityLogTableAction;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build\ForActivityLog;

class ActivityLogTable extends BasePluginAdminPage {

	public const SLUG = 'page_admin_plugin_activity_log_table';
	public const PRIMARY_MOD = 'audit_trail';
	public const TEMPLATE = '/wpadmin_pages/insights/plugin_admin/table_activity.twig';

	protected function getRenderData() :array {
		return [
			'ajax'    => [
				'logtable_action' => ActionData::BuildJson( ActivityLogTableAction::SLUG ),
			],
			'flags'   => [],
			'strings' => [
				'table_title' => __( 'Activity Log', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'datatables_init' => ( new ForActivityLog() )
					->setMod( $this->primary_mod )
					->build()
			],
		];
	}
}