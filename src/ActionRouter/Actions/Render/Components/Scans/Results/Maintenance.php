<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\Results;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\{
	ActionsQueueLandingAssessmentBuilder,
	ActionsQueueLandingViewBuilder,
	ActionsQueueScanRailBuilder,
	ActionsQueueScanRailMetricsBuilder
};
use FernleafSystems\Wordpress\Services\Services;

class Maintenance extends Base {

	public const SLUG = 'scanresults_maintenance';
	public const TEMPLATE = '/wpadmin_pages/insights/scans/results/scan_results_rail_pane.twig';

	protected function getRenderData() :array {
		$attentionQuery = self::con()->comps->site_query->attention();
		$assessmentBuilder = new ActionsQueueLandingAssessmentBuilder();
		$landingViewData = ( new ActionsQueueLandingViewBuilder() )->build(
			$attentionQuery,
			[
				'scans'       => $assessmentBuilder->buildForZone( 'scans' ),
				'maintenance' => $assessmentBuilder->buildForZone( 'maintenance' ),
			]
		);
		$metrics = ( new ActionsQueueScanRailMetricsBuilder() )->build( $attentionQuery );
		$zoneTiles = [];
		foreach ( $landingViewData[ 'zone_tiles' ] as $zoneTile ) {
			$zoneTiles[ $zoneTile[ 'key' ] ] = $zoneTile;
		}

		$tab = ( new ActionsQueueScanRailBuilder() )->buildMaintenanceTabDefinition(
			$zoneTiles[ 'maintenance' ][ 'items' ],
			$zoneTiles[ 'maintenance' ][ 'assessment_rows' ],
			$metrics[ 'tabs' ][ 'maintenance' ]
		);

		return Services::DataManipulation()->mergeArraysRecursive( parent::getRenderData(), [
			'strings' => [
				'no_issues' => __( 'No issues found in this section.', 'wp-simple-firewall' ),
			],
			'tab'     => $tab,
			'content' => [],
		] );
	}
}
