<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting;

class ResolveReportViewContracts {

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
