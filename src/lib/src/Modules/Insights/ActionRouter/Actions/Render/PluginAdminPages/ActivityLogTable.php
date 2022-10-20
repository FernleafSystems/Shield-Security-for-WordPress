<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\ActivityLogTableAction;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build\ForAuditTrail;

class ActivityLogTable extends BasePluginAdminPage {

	const SLUG = 'page_admin_plugin_activity_log_table';
	const PRIMARY_MOD = 'audit_trail';
	const TEMPLATE = '/wpadmin_pages/insights/plugin_admin/table_activity.twig';

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
				'datatables_init' => ( new ForAuditTrail() )
					->setMod( $this->primary_mod )
					->build()
			],
		];
	}
}