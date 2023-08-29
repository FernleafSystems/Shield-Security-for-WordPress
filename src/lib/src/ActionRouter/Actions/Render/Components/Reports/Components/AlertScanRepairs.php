<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Reports\Components;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginURLs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\DB\Logs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\DB\Meta;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Events\DB\Event\Ops as DBEvents;

/**
 * @deprecated 18.3.0
 */
class AlertScanRepairs extends BaseBuilderForScans {

	public const SLUG = 'alert_scan_repairs';
	public const TEMPLATE = '/components/reports/components/alert_scanrepairs.twig';

	protected function getRenderData() :array {
		$con = self::con();
		/** @var DBEvents\Select $selectorEvents */
		$selectorEvents = $con->getModule_Events()
							  ->getDbH_Events()
							  ->getQuerySelector();

		$report = $this->getReport();

		$repairs = [];
		$repairEvents = [
			'scan_item_repair_success',
			'scan_item_repair_fail',
			'scan_item_delete_success',
		];

		$total = 0;
		$srvEvents = $con->loadEventsService();
		foreach ( $repairEvents as $event ) {
			$eventTotal = $selectorEvents
				->filterByBoundary( $report->start_at, $report->end_at )
				->sumEvent( $event );
			$total += $eventTotal;

			if ( $eventTotal > 0 ) {
				$modAudit = $con->getModule_AuditTrail();

				/** @var Logs\Ops\Select $logSelect */
				$logSelect = $modAudit->getDbH_Logs()->getQuerySelector();
				/** @var Logs\Ops\Record[] $logs */
				$logIDs = \array_map(
					function ( $log ) {
						return $log->id;
					},
					$logSelect->filterByEvent( $event )
							  ->filterByBoundary( $report->start_at, $report->end_at )
							  ->setLimit( $eventTotal )
							  ->queryWithResult()
				);

				/** @var Meta\Ops\Select $metaSelect */
				$metaSelect = $modAudit->getDbH_Meta()->getQuerySelector();

				$repairs[] = [
					'count'   => $eventTotal,
					'name'    => $srvEvents->getEventName( $event ),
					'repairs' => \array_unique( \array_map(
						function ( $meta ) {
							/** @var Meta\Ops\Record $meta */
							return \str_replace( ABSPATH, '', $meta->meta_value );
						},
						$metaSelect->filterByLogRefs( $logIDs )
								   ->filterByMetaKey( 'path_full' )
								   ->queryWithResult()
					) ),
				];
			}
		}

		return [
			'flags'   => [
				'render_required' => !empty( $repairs ),
			],
			'hrefs'   => [
				'activity_log' => $con->plugin_urls->adminTopNav( PluginURLs::NAV_ACTIVITY_LOG ),
			],
			'strings' => [
				'title'        => \__( 'Scanner Repairs', 'wp-simple-firewall' ),
				'activity_log' => \__( 'View all repairs and file deletions in the Activity Log', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'total'   => $total,
				'repairs' => $repairs,
			],
		];
	}
}