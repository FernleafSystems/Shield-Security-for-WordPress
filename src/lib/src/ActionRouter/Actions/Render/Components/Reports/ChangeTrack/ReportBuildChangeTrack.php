<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Reports\ChangeTrack;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ChangeTrack\Ops\{
	ArrangeDiffByZone,
	Diff,
	RetrieveDiffs,
	RetrieveSnapshot
};
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionException;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Lib\Request\FormParams;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ChangeTrack\SnapshotVO;
use FernleafSystems\Wordpress\Services\Services;

class ReportBuildChangeTrack extends BaseRender {

	public const SLUG = 'render_report_build_changetrack';
	public const TEMPLATE = '/wpadmin_pages/insights/reports/changetrack/report/index.twig';

	protected function getRenderData() :array {
		$req = Services::Request();

		$formParams = FormParams::Retrieve();

		$startDate = \explode( '-', $formParams[ 'start_date' ] );
		$startDate = $req->carbon( true )
						 ->setDate( $startDate[ 0 ], $startDate[ 1 ], $startDate[ 2 ] )
						 ->startOfDay();
		$endDate = \explode( '-', $formParams[ 'end_date' ] );
		$endDate = $req->carbon( true )
					   ->setDate( $endDate[ 0 ], $endDate[ 1 ], $endDate[ 2 ] )
					   ->endOfDay();

		try {
			$aggregatedDiff = $this->getDiffDataForDisplay(
				( new Diff(
					( new RetrieveSnapshot() )->latestFollowing( $startDate->timestamp ),
					( new RetrieveSnapshot() )->latestPreceding( $endDate->timestamp )
				) )->run()
			);
		}
		catch ( \Exception $e ) {
			throw new ActionException( $e->getMessage() );
		}

		try {
			$detailedDiffs = \array_reverse( \array_filter( \array_map(
				function ( SnapshotVO $diff ) {
					return $this->getDiffDataForDisplay( $diff );
				},
				( new RetrieveDiffs() )->between( $startDate->timestamp, $endDate->timestamp )
			) ) );
		}
		catch ( \Exception $e ) {
			throw new ActionException( $e->getMessage() );
		}

		return [
			'flags'   => [
				'has_diffs' => true,
			],
			'strings' => [
			],
			'vars'    => [
				'aggregated_diff' => $aggregatedDiff,
				'detailed_diffs'  => $detailedDiffs,
			],
		];
	}

	private function getDiffDataForDisplay( SnapshotVO $diff ) :?array {
		if ( count( $diff->data ) === 0 ) {
			return null;
		}

		$zonesForDisplay = [
			'title'      => Services::Request()
									->carbon( true )
									->setTimestamp( $diff->snapshot_at )
									->toIso8601String(),
			'slug'       => uniqid(),
			'zones_data' => []
		];

		foreach ( ArrangeDiffByZone::run( $diff->data ) as $zoneSlug => $zoneDiff ) {
			$zone = $this->con()->getModule_Plugin()->getChangeTrackCon()->getZone( $zoneSlug );
			if ( !empty( $zone ) ) {
				$reporter = $zone->getZoneReporter();
				$zonesForDisplay[ 'zones_data' ][ $zoneSlug ] = [
					'title'            => $reporter->getZoneName(),
					'description'      => 'Zone Description',
					'diff_for_display' => $reporter->processDiffForDisplay( $zoneDiff ),
				];
			}
		}

		return $zonesForDisplay;
	}
}