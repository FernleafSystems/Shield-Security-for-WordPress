<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Reports;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\Components\Reports\Alerts\ScanResultsAlert;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting\Lib\Reports\BaseReporter;
use FernleafSystems\Wordpress\Services\Services;

class ScanAlerts extends BaseReporter {

	public function build() :array {
		$alerts = [];

		$counts = array_filter(
			( new Query\ScanCounts() )
				->setMod( $this->getMod() )
				->standard()
		);

		if ( !empty( $counts ) ) {
			/** @var HackGuard\Strings $strings */
			$strings = $this->getMod()->getStrings();
			$scanCounts = [];
			foreach ( $counts as $slug => $count ) {
				$scanCounts[ $slug ] = [
					'count' => $count,
					'name'  => $strings->getScanName( $slug ),
				];
			}

			$alerts[] = $this->getCon()
							 ->getModule_Insights()
							 ->getActionRouter()
							 ->render( ScanResultsAlert::SLUG, [
								 'scan_counts'  => $scanCounts,
							 ] );
			$this->markAlertsAsNotified();
		}

		return $alerts;
	}

	private function markAlertsAsNotified() {
		/** @var HackGuard\ModCon $mod */
		$mod = $this->getMod();
		$mod->getDbH_ResultItems()
			->getQueryUpdater()
			->setUpdateWheres( [
				'notified_at' => 0,
			] )
			->setUpdateData( [
				'notified_at' => Services::Request()->ts()
			] )
			->query();
	}
}