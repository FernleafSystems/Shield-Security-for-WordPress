<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets;

use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Collate\RecentStats;

class OverviewIpOffenses extends OverviewIpsBase {

	public const SLUG = 'render_widget_overview_ip_offenses';

	protected function getRenderData() :array {
		$data = parent::getRenderData();
		$data[ 'strings' ][ 'title' ] = __( 'IP Offenses', 'wp-simple-firewall' );
		return $data;
	}

	protected function getIPs() :array {
		return ( new RecentStats() )->getRecentlyOffendedIPs();
	}
}