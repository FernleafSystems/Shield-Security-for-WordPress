<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	Actions\TrafficLogTableAction
};
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\Options;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build\ForTraffic;

class PageTrafficLogTable extends PageTrafficLogBase {

	public const SLUG = 'page_admin_plugin_traffic_log_table';
	public const TEMPLATE = '/wpadmin/plugin_pages/inner/table_traffic.twig';

	protected function getPageContextualHrefs() :array {
		$hrefs = parent::getPageContextualHrefs();
		\array_unshift( $hrefs, [
			'text' => __( 'Switch To Live Logs', 'wp-simple-firewall' ),
			'href' => self::con()->plugin_urls->adminTopNav( PluginNavs::NAV_TRAFFIC, PluginNavs::SUBNAV_LIVE ),
		] );
		return $hrefs;
	}

	protected function getRenderData() :array {
		/** @var Options $opts */
		$opts = self::con()->getModule_Traffic()->opts();
		return [
			'ajax'    => [
				'traffictable_action' => ActionData::BuildJson( TrafficLogTableAction::class ),
			],
			'flags'   => [
				'is_enabled' => $opts->isTrafficLoggerEnabled(),
			],
			'hrefs'   => [
				'please_enable' => self::con()->plugin_urls->modCfgOption( 'enable_logger' ),
			],
			'imgs'    => [
				'inner_page_title_icon' => self::con()->svgs->raw( 'stoplights' ),
			],
			'strings' => [
				'inner_page_title'    => __( 'Request Logs', 'wp-simple-firewall' ),
				'inner_page_subtitle' => __( 'View and explore details of HTTP requests made to your site.', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'datatables_init' => ( new ForTraffic() )->build()
			],
		];
	}
}