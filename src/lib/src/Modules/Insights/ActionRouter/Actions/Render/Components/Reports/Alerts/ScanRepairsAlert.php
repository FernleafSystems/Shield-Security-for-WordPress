<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\Components\Reports\Alerts;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Traits;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\BaseRender;

class ScanRepairsAlert extends BaseRender {

	use Traits\AuthNotRequired;

	const PRIMARY_MOD = 'hack_protect';
	const SLUG = 'alert_scan_repairs';
	const TEMPLATE = '/components/reports/mod/hack_protect/alert_scanrepairs.twig';

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
				'audit_trail' => $this->getCon()
									  ->getModule_Insights()
									  ->getUrl_SubInsightsPage( 'audit_trail' ),
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