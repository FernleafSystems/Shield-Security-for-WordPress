<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\Results;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets\NeedsAttentionQueueDataBuilder;
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
		$needsAttentionPayload = [
			'render_data' => ( new NeedsAttentionQueueDataBuilder() )->build( [
				'compact_all_clear' => true,
			] ),
		];
		$assessmentBuilder = new ActionsQueueLandingAssessmentBuilder();
		$landingViewData = ( new ActionsQueueLandingViewBuilder() )->build(
			$needsAttentionPayload,
			[
				'scans'       => $assessmentBuilder->buildForZone( 'scans' ),
				'maintenance' => $assessmentBuilder->buildForZone( 'maintenance' ),
			]
		);
		$metrics = ( new ActionsQueueScanRailMetricsBuilder() )->build( $needsAttentionPayload );
		$zoneTiles = [];
		foreach ( $landingViewData[ 'zone_tiles' ] as $zoneTile ) {
			$zoneTiles[ (string)( $zoneTile[ 'key' ] ?? '' ) ] = $zoneTile;
		}

		$tab = ( new ActionsQueueScanRailBuilder() )->buildMaintenanceTabDefinition(
			$zoneTiles[ 'maintenance' ][ 'items' ] ?? [],
			$zoneTiles[ 'maintenance' ][ 'assessment_rows' ] ?? [],
			$metrics[ 'tabs' ][ 'maintenance' ] ?? [
				'count'  => 0,
				'status' => 'good',
			]
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
