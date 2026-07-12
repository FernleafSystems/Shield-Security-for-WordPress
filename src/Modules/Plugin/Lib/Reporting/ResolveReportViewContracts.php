<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting;

use FernleafSystems\Wordpress\Services\{
	Core\General,
	Services
};

class ResolveReportViewContracts {

	/**
	 * @return array{
	 *   current:array{label:string,date_start:string,date_end:string},
	 *   previous:array{label:string,date_start:string,date_end:string}
	 * }
	 */
	public function statisticsPeriods( ReportVO $report ) :array {
		$WP = Services::WpGeneral();
		return [
			'current'  => $this->statisticsPeriod(
				__( 'Current', 'wp-simple-firewall' ),
				$report->start_at,
				$report->end_at,
				$WP
			),
			'previous' => $this->statisticsPeriod(
				__( 'Previous', 'wp-simple-firewall' ),
				$report->previous_start_at,
				$report->previous_end_at,
				$WP
			),
		];
	}

	/**
	 * @return array{label:string,date_start:string,date_end:string}
	 */
	private function statisticsPeriod( string $label, int $startAt, int $endAt, General $WP ) :array {
		return [
			'label'      => $label,
			'date_start' => $WP->getTimeStringForDisplay( $startAt, false ),
			'date_end'   => $WP->getTimeStringForDisplay( $endAt, false ),
		];
	}

	/**
	 * @return array{
	 *   has_new_items:bool,
	 *   notification_target_ids:list<int>,
	 *   summary:array{row_count:int,new_total:int,current_total:int,outstanding_total:int,actions_queue_href:string},
	 *   rows:list<array{
	 *     title:string,
	 *     count:int,
	 *     new_count:int,
	 *     outstanding_count:int,
	 *     has_new:bool,
	 *     new_items:list<array{label:string}>,
	 *     outstanding_items:list<array{label:string}>,
	 *     hidden_new_count:int,
	 *     hidden_outstanding_count:int,
	 *     review_href:string,
	 *     review_action:string
	 *   }>
	 * }
	 */
	public function alertDigest( ReportVO $report ) :array {
		return !empty( $report->alert_digest )
			? $report->alert_digest
			: ( new BuildAlertDigestContract() )->build( $report );
	}

	/**
	 * @return array{
	 *   summary:array{
	 *     title:string,
	 *     subtitle:string,
	 *     state?:'attention'|'all_clear',
	 *     total_issues?:int
	 *   },
	 *   cards:list<array{
	 *     label:string,
	 *     value:string,
	 *     meta:string,
	 *     key?:'attention'|'coverage'|'scans',
	 *     state?:'attention'|'all_clear'|'running'|'completed'|'not_started',
	 *     severity?:'good'|'warning'|'critical',
	 *     total_issues?:int,
	 *     percentage?:int,
	 *     zones?:array{total:int,good:int,warning:int,critical:int},
	 *     enqueued_count?:int,
	 *     latest_completed_at?:int
	 *   }>
	 * }
	 */
	public function infoHeadline( ReportVO $report ) :array {
		if ( $report->type !== Constants::REPORT_TYPE_INFO ) {
			return $this->emptyInfoHeadline();
		}

		return !empty( $report->info_headline )
			? $report->info_headline
			: ( new BuildInfoHeadlineContract() )->build();
	}

	/**
	 * @return array{
	 *   summary:array{
	 *     title:string,
	 *     subtitle:string
	 *   },
	 *   cards:list<array{label:string,value:string,meta:string}>
	 * }
	 */
	private function emptyInfoHeadline() :array {
		return [
			'summary' => [
				'title'    => '',
				'subtitle' => '',
			],
			'cards'   => [],
		];
	}
}
