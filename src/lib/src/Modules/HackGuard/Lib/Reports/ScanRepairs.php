<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Reports;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Events as DBEvents;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Events;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting\Lib\Reports\BaseReporter;

class ScanRepairs extends BaseReporter {

	/**
	 * @inheritDoc
	 */
	public function build() {
		$alerts = [];

		/** @var Events\ModCon $mod */
		$mod = $this->getMod();
		/** @var DBEvents\Select $selectorEvents */
		$selectorEvents = $mod->getDbHandler_Events()->getQuerySelector();
		/** @var Events\Strings $strings */
		$strings = $mod->getStrings();

		$report = $this->getReport();

		$counts = [];

		try {
			$event = 'scan_item_repair_success';
			$total = $selectorEvents
				->filterByEvent( $event )
				->filterByBoundary( $report->interval_start_at, $report->interval_end_at )
				->count();
			if ( $total == 0 ) {
			}
			elseif ( $total > 100 ) {
			}
			else {
				$counts[] = [
					'count' => $total,
					'name'  => $strings->getEventName( $event ),
				];
			}
		}
		catch ( \Exception $e ) {
			$total = 0;
		}

		if ( $total > 0 ) {
			$alerts[] = $this->getMod()->renderTemplate(
				'/components/reports/mod/events/alert_scanrepairs.twig',
				[
					'vars'    => [
						'total'  => $total,
						'counts' => $counts,
					],
					'strings' => [
						'title' => \__( 'Scanner Repairs', 'wp-simple-firewall' ),
					],
				]
			);
		}

		return $alerts;
	}
}