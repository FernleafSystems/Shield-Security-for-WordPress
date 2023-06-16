<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Reports\ChangeTrack;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\DB\Changes\Ops as ChangesDB;
use FernleafSystems\Wordpress\Services\Services;

class PageReportGenerateNewChangeTrack extends BaseRender {

	public const SLUG = 'render_report_generate_new_changetrack';
	public const TEMPLATE = '/wpadmin_pages/insights/reports/changetrack/page_generate_new.twig';

	protected function getRenderData() :array {
		$req = Services::Request();
		$dbh = $this->con()->getModule_Plugin()->getChangeTrackCon()->getDbH_Changes();

		/** @var ?ChangesDB\Record $firstSnap */
		$firstSnap = $dbh->getQuerySelector()
						 ->filterIsFull()
						 ->setOrderBy( 'created_at', 'ASC', true )
						 ->first();
		/** @var ?ChangesDB\Record $lastSnap */
		$lastSnap = $dbh->getQuerySelector()
						->setOrderBy( 'created_at', 'DESC', true )
						->first();

		$c = $req->carbon( true );
		return [
			'ajax'    => [
				'build_change_report'      => ActionData::BuildJson( ReportBuildChangeTrack::class ),
				'build_change_report_slug' => ReportBuildChangeTrack::SLUG,
			],
			'flags'   => [
				'can_run_report' => !empty( $firstSnap ) && $firstSnap->id !== $lastSnap->id,
			],
			'strings' => [
				'build_change_report' => __( 'Build Change Report', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'earliest_date' => empty( $firstSnap ) ? $req->ts() :
					$c->setTimestamp( $firstSnap->created_at )->toIso8601String(),
				'latest_date'   => empty( $lastSnap ) ? $req->ts() :
					$c->setTimestamp( $lastSnap->created_at )->toIso8601String()
			],
		];
	}
}