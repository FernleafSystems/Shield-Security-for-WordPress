<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\ActivityLogTableAction;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\CommonDisplayStrings;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build\ForActivityLog;

class PageActivityLogTable extends PageModeLandingBase {

	public const SLUG = 'page_admin_plugin_activity_log_table';
	public const TEMPLATE = '/wpadmin/plugin_pages/inner/table_activity.twig';

	protected function getPageContextualHrefs_Help() :array {
		return [
			'title'      => sprintf( '%s: %s', CommonDisplayStrings::get( 'help_label' ), __( 'Activity Log', 'wp-simple-firewall' ) ),
			'href'       => 'https://help.getshieldsecurity.com/article/238-review-your-site-activities-with-the-activity-log-viewer',
			'new_window' => true,
		];
	}

	protected function getLandingTitle() :string {
		return __( 'WP Activity Log', 'wp-simple-firewall' );
	}

	protected function getLandingSubtitle() :string {
		return __( 'Track and monitor activity on your site by users, visitors and bots.', 'wp-simple-firewall' );
	}

	protected function getLandingIcon() :string {
		return 'person-lines-fill';
	}

	protected function getLandingMode() :string {
		return PluginNavs::MODE_INVESTIGATE;
	}

	protected function hasOperatorModeParent() :bool {
		return true;
	}

	protected function getRenderData() :array {
		return \array_replace_recursive( parent::getRenderData(), [
			'ajax'    => [
				'logtable_action' => ActionData::BuildJson( ActivityLogTableAction::class ),
			],
			'vars'    => [
				'datatables_init' => ( new ForActivityLog() )->build()
			],
		] );
	}
}
