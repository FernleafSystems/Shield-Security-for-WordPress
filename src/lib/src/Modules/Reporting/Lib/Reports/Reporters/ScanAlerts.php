<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting\Lib\Reports\Reporters;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\Components\Reports\Alerts\ScanResultsAlert;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting\Lib\Constants;
use FernleafSystems\Wordpress\Services\Services;

class ScanAlerts extends BaseReporter {
	public const TYPE = Constants::REPORT_TYPE_ALERT;

	public function build() :array {
		$mod = $this->getCon()->getModule_HackGuard();
		$alerts = [];

		$counts = array_filter(
			( new Helpers\ScanCounts() )
				->setMod( $mod )
				->standard()
		);

		if ( !empty( $counts ) ) {
			/** @var HackGuard\Strings $strings */
			$strings = $mod->getStrings();
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
								 'scan_counts' => $scanCounts,
							 ] );
			$this->markAlertsAsNotified();
		}

		return $alerts;
	}

	private function markAlertsAsNotified() {
		$this->getCon()
			 ->getModule_HackGuard()
			 ->getDbH_ResultItems()
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