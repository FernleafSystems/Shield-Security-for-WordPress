<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Reports\ChangeTrack;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Lib\Request\FormParams;
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

		$zonesForDisplay = [
			'title'      => Services::Request()
									->carbon( true )
									->setTimestamp( $endDate->timestamp )
									->toIso8601String(),
			'slug'       => uniqid(),
			'zones_data' => []
		];

		foreach ( $this->con()->getModule_Plugin()->getChangeTrackCon()->getZones() as $reporter ) {
			if ( \in_array( $reporter::Slug(), $formParams[ 'zones' ] ?? [] ) ) {
				$reporter->setFrom( $startDate->timestamp );
				$reporter->setUntil( $endDate->timestamp );
				$zonesForDisplay[ 'zones_data' ][ $reporter::Slug() ] = [
					'title'       => $reporter->getZoneName(),
					'description' => $reporter->getZoneDescription(),
					'summary'     => $reporter->buildChangeReportData( true ),
					'detailed'    => $reporter->buildChangeReportData( false ),
				];
			}
		}

		return [
			'flags'   => [
				'has_diffs' => true,
			],
			'strings' => [
			],
			'vars'    => [
				'changes' => $zonesForDisplay,
			],
		];
	}
}