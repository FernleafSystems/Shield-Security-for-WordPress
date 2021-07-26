<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Reports;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\AuditTrail as DBAudit;
use FernleafSystems\Wordpress\Plugin\Shield\Databases\Events as DBEvents;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Events;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting\Lib\Reports\BaseReporter;

class ScanRepairs extends BaseReporter {

	/**
	 * @inheritDoc
	 */
	public function build() {
		$alerts = [];

		$modEvents = $this->getCon()->getModule_Events();
		/** @var DBEvents\Select $selectorEvents */
		$selectorEvents = $this->getCon()
							   ->getModule_Events()
							   ->getDbHandler_Events()
							   ->getQuerySelector();
		/** @var Events\Strings $strings */
		$strings = $modEvents->getStrings();

		$report = $this->getReport();

		$repairs = [];
		$repairEvents = [
			'scan_item_repair_success',
			'scan_item_repair_fail',
			'scan_item_delete_success',
		];

		$total = 0;
		foreach ( $repairEvents as $event ) {
			$eventTotal = $selectorEvents
				->filterByBoundary( $report->interval_start_at, $report->interval_end_at )
				->sumEvent( $event );
			$total += $eventTotal;

			if ( $eventTotal > 0 ) {
				/** @var DBAudit\Select $auditSelector */
				$auditSelector = $this->getCon()
									  ->getModule_AuditTrail()
									  ->getDbHandler_AuditTrail()
									  ->getQuerySelector();
				/** @var DBAudit\EntryVO[] $audits */
				$audits = $auditSelector->filterByEvent( $event )
										->filterByBoundary( $report->interval_start_at, $report->interval_end_at )
										->setLimit( 10 )
										->query();

				$repairs[] = [
					'count'   => $eventTotal,
					'name'    => $strings->getEventName( $event ),
					'repairs' => array_filter( array_map( function ( $entry ) {
						// see Base ItemActionHandler for audit event data
						$fragment = $entry->meta[ 'path_full' ] ?? ( $entry->meta[ 'fragment' ] ?? false );
						if ( !empty( $fragment ) ) {
							$fragment = str_replace( wp_normalize_path( ABSPATH ), '', $fragment );
						}
						return $fragment;
					}, $audits ) ),
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
						'audit_trail' => \__( 'View all repairs and file deletions in the Audit Trail', 'wp-simple-firewall' ),
					],
					'hrefs'   => [
						'audit_trail' => $this->getCon()
											  ->getModule_Insights()
											  ->getUrl_SubInsightsPage( 'audit' ),
					],
				]
			);
		}

		return $alerts;
	}
}