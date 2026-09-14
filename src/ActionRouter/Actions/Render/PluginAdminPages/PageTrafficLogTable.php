<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build\ForTraffic;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;

class PageTrafficLogTable extends PageTrafficLogBase {

	public const SLUG = 'page_admin_plugin_traffic_log_table';
	public const TEMPLATE = '/wpadmin/plugin_pages/inner/table_traffic.twig';

	protected function getPageContextualHrefs() :array {
		$hrefs = parent::getPageContextualHrefs();
		\array_unshift( $hrefs, [
			'title' => __( 'Switch To Live Logs', 'wp-simple-firewall' ),
			'href'  => self::con()->plugin_urls->trafficLive(),
		] );
		return $hrefs;
	}

	protected function getLandingMode() :string {
		return PluginNavs::MODE_INVESTIGATE;
	}

	protected function hasOperatorModeParent() :bool {
		return true;
	}

	protected function getRenderData() :array {
		$con = self::con();
		return \array_replace_recursive( parent::getRenderData(), [
			'flags'   => [
				'is_enabled' => $con->comps->opts_lookup->enabledTrafficLogger(),
			],
			'vars'    => [
				'datatables_init' => ( new ForTraffic() )->build(),
			],
		] );
	}
}
