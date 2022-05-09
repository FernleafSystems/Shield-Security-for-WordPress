<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Reports;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Events as DBEvents;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\DB\Logs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\DB\Meta;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting\Lib\Reports\BaseReporter;

class ScanRepairs extends BaseReporter {

	public function build() :array {
		$alerts = [];

		/** @var DBEvents\Select $selectorEvents */
		$selectorEvents = $this->getCon()
							   ->getModule_Events()
							   ->getDbHandler_Events()
							   ->getQuerySelector();

		$report = $this->getReport();

		$repairs = [];
		$repairEvents = [
			'scan_item_repair_success',
			'scan_item_repair_fail',
			'scan_item_delete_success',
		];

		$total = 0;
		$srvEvents = $this->getCon()->loadEventsService();
		foreach ( $repairEvents as $event ) {
			$eventTotal = $selectorEvents
				->filterByBoundary( $report->interval_start_at, $report->interval_end_at )
				->sumEvent( $event );
			$total += $eventTotal;

			if ( $eventTotal > 0 ) {
				$modAudit = $this->getCon()->getModule_AuditTrail();

				/** @var Logs\Ops\Select $logSelect */
				$logSelect = $modAudit->getDbH_Logs()->getQuerySelector();
				/** @var Logs\Ops\Record[] $logs */
				$logIDs = array_map(
					function ( $log ) {
						return $log->id;
					},
					$logSelect->filterByEvent( $event )
							  ->filterByBoundary( $report->interval_start_at, $report->interval_end_at )
							  ->setLimit( $eventTotal )
							  ->queryWithResult()
				);

				/** @var Meta\Ops\Select $metaSelect */
				$metaSelect = $modAudit->getDbH_Meta()->getQuerySelector();

				$repairs[] = [
					'count'   => $eventTotal,
					'name'    => $srvEvents->getEventName( $event ),
					'repairs' => array_unique( array_map(
						function ( $meta ) {
							/** @var Meta\Ops\Record $meta */
							return str_replace( ABSPATH, '', $meta->meta_value );
						},
						$metaSelect->filterByLogRefs( $logIDs )
								   ->filterByMetaKey( 'path_full' )
								   ->queryWithResult()
					) ),
				];
			}
		}

		if ( !empty( $repairs ) ) {
			$alerts[] = $this->getMod()->renderTemplate(
				'/components/reports/mod/hack_protect/alert_scanrepairs.twig',
				[
					'vars'    => [
						'total'   => $total,
						'repairs' => $repairs,
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
				]
			);
		}

		return $alerts;
	}
}