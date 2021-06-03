<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Events\Lib\Reports;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Events as DBEvents;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Events;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Options;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting\Lib\Reports\BaseReporter;

class ScanRepairs extends BaseReporter {

	/**
	 * @inheritDoc
	 */
	public function build() {
		$aAlerts = [];

		/** @var Events\ModCon $mod */
		$mod = $this->getMod();
		/** @var DBEvents\Select $selectorEvents */
		$selectorEvents = $mod->getDbHandler_Events()->getQuerySelector();
		/** @var Events\Strings $strings */
		$strings = $mod->getStrings();

		$report = $this->getReport();

		$counts = [];

		/** @var Options $oHGOptions */
		$oHGOptions = $this->getCon()->getModule_HackGuard()->getOptions();
		foreach ( $oHGOptions->getScanSlugs() as $scan ) {
			try {
				$event = $scan.'_item_repair_success';
				$count = $selectorEvents
					->filterByEvent( $event )
					->filterByBoundary( $report->interval_start_at, $report->interval_end_at )
					->count();
				if ( $count > 0 ) {
					$counts[ $scan ] = [
						'count' => $count,
						'name'  => $strings->getEventName( $event ),
					];
				}
			}
			catch ( \Exception $e ) {
			}
		}

		if ( count( $counts ) > 0 ) {
			$aAlerts[] = $this->getMod()->renderTemplate(
				'/components/reports/mod/events/info_keystats.twig',
				[
					'vars'    => [
						'counts' => $counts
					],
					'strings' => [
						'title' => __( 'Scanner Repairs', 'wp-simple-firewall' ),
					],
					'hrefs'   => [
					],
				]
			);
		}

		return $aAlerts;
	}
}