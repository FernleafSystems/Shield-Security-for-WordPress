<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build\ForTraffic;

class PageTrafficLogTable extends PageTrafficLogBase {

	public const SLUG = 'page_admin_plugin_traffic_log_table';
	public const TEMPLATE = '/wpadmin/plugin_pages/inner/table_traffic.twig';

	protected function getPageContextualHrefs() :array {
		$hrefs = parent::getPageContextualHrefs();
		\array_unshift( $hrefs, [
			'title' => __( 'Switch To Live Logs', 'wp-simple-firewall' ),
			'href'  => self::con()->plugin_urls->adminTopNav( PluginNavs::NAV_TRAFFIC, PluginNavs::SUBNAV_LIVE ),
		] );
		return $hrefs;
	}

	protected function getRenderData() :array {
		$con = self::con();
		return [
			'flags'   => [
				'is_enabled' => $con->comps->opts_lookup->enabledTrafficLogger(),
			],
			'imgs'    => [
				'inner_page_title_icon' => $con->svgs->raw( 'stoplights' ),
			],
			'strings' => [
				'inner_page_title'    => __( 'Request Logs', 'wp-simple-firewall' ),
				'inner_page_subtitle' => __( 'View and explore details of HTTP requests made to your site.', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'datatables_init' => ( new ForTraffic() )->build(),
			],
		];
	}
}