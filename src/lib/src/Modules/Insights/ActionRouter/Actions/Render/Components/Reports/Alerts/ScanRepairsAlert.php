<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\Components\Reports\Alerts;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginURLs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions;

class ScanRepairsAlert extends Actions\Render\Components\Reports\ReportsBuilderBase {

	use Actions\Traits\AuthNotRequired;

	public const PRIMARY_MOD = 'hack_protect';
	public const SLUG = 'alert_scan_repairs';
	public const TEMPLATE = '/components/reports/mod/hack_protect/alert_scanrepairs.twig';

	protected function getRenderData() :array {
		return [
			'vars'    => [
				'total'   => $this->action_data[ 'total' ],
				'repairs' => $this->action_data[ 'repairs' ],
			],
			'strings' => [
				'title'       => \__( 'Scanner Repairs', 'wp-simple-firewall' ),
				'audit_trail' => \__( 'View all repairs and file deletions in the Activity Log', 'wp-simple-firewall' ),
			],
			'hrefs'   => [
				'audit_trail' => $this->getCon()->plugin_urls->adminTop( PluginURLs::NAV_ACTIVITY_LOG ),
			],
		];
	}

	protected function getRequiredDataKeys() :array {
		return [
			'total',
			'repairs',
		];
	}
}