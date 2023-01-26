<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	Actions\TrafficLogTableAction
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\Options;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build\ForTraffic;

class PageTrafficLogTable extends BasePluginAdminPage {

	public const SLUG = 'page_admin_plugin_traffic_log_table';
	public const PRIMARY_MOD = 'traffic';
	public const TEMPLATE = '/wpadmin_pages/insights/plugin_admin/table_traffic.twig';

	protected function getRenderData() :array {
		$con = $this->getCon();
		/** @var Options $opts */
		$opts = $this->primary_mod->getOptions();
		return [
			'ajax'    => [
				'traffictable_action' => ActionData::BuildJson( TrafficLogTableAction::SLUG ),
			],
			'flags'   => [
				'is_enabled' => $opts->isTrafficLoggerEnabled(),
			],
			'hrefs'   => [
				'please_enable'     => $con->plugin_urls->modCfgOption( 'enable_logger' ),
				'inner_page_config' => $con->plugin_urls->offCanvasConfigRender( $this->primary_mod->getSlug() ),
			],
			'strings' => [
				'inner_page_title'    => __( 'Traffic & Request Logs', 'wp-simple-firewall' ),
				'inner_page_subtitle' => __( 'View and explore details of HTTP requests made to your site.', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'datatables_init' => ( new ForTraffic() )
					->setMod( $this->primary_mod )
					->build()
			],
		];
	}
}