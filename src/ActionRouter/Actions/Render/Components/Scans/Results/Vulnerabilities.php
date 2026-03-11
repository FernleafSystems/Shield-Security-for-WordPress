<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\Results;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\ActionsQueueScanRailBuilder;

class Vulnerabilities extends BaseRender {

	public const SLUG = 'scanresults_vulnerabilities';
	public const TEMPLATE = '/wpadmin_pages/insights/scans/results/scan_results_rail_pane.twig';

	protected function getRenderData() :array {
		return [
			'strings' => [
				'no_issues' => __( "Previous scans didn't detect any vulnerable or abandoned assets.", 'wp-simple-firewall' ),
			],
			'tab'     => ( new ActionsQueueScanRailBuilder() )->buildVulnerabilitiesPane(),
			'content' => [],
		];
	}
}
