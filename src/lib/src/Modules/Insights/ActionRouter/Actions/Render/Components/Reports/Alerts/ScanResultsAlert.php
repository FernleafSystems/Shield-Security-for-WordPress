<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\Components\Reports\Alerts;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginURLs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions;

class ScanResultsAlert extends Actions\Render\Components\Reports\ReportsBuilderBase {

	use Actions\Traits\AuthNotRequired;

	public const PRIMARY_MOD = 'hack_protect';
	public const SLUG = 'alert_scan_results';
	public const TEMPLATE = '/components/reports/mod/hack_protect/alert_scanresults.twig';

	protected function getRenderData() :array {
		return [
			'hrefs'   => [
				'view_results' => $this->getCon()->plugin_urls->adminTop( PluginURLs::NAV_SCANS_RESULTS ),
			],
			'strings' => [
				'title'        => __( 'New Scan Results', 'wp-simple-firewall' ),
				'view_results' => __( 'Click Here To View Scan Results Details', 'wp-simple-firewall' ),
				'note_changes' => sprintf( '%s: %s', __( 'Note', 'wp-simple-firewall' ),
					__( 'Depending on previous actions taken on the site or file system changes, these results may no longer be available to view.', 'wp-simple-firewall' ) ),

			],
			'vars'    => [
				'scan_counts' => $this->action_data[ 'scan_counts' ]
			],
		];
	}

	protected function getRequiredDataKeys() :array {
		return [
			'scan_counts',
		];
	}
}