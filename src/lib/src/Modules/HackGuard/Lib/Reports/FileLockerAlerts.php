<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Reports;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\FileLocker;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\Components\Reports\Alerts\FileLockerAlert;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting\Lib\Reports\BaseReporter;

class FileLockerAlerts extends BaseReporter {

	public function build() :array {
		$alerts = [];

		$lockOps = ( new HackGuard\Lib\FileLocker\Ops\LoadFileLocks() )
			->setMod( $this->getMod() );
		$notNotified = $lockOps->withProblemsNotNotified();

		if ( count( $notNotified ) > 0 ) {
			$alerts[] = $this->getCon()
							 ->getModule_Insights()
							 ->getActionRouter()
							 ->render( FileLockerAlert::SLUG, [
								 'count_not_notified'  => count( $notNotified ),
								 'count_with_problems' => count( $lockOps->withProblems() ),
							 ] );
			$this->markAlertsAsNotified( $notNotified );
			$lockOps->clearLocksCache();
		}

		return $alerts;
	}

	/**
	 * @param FileLocker\EntryVO[] $setNotified
	 */
	private function markAlertsAsNotified( array $setNotified ) {
		/** @var HackGuard\ModCon $mod */
		$mod = $this->getMod();
		/** @var FileLocker\Update $updater */
		$updater = $mod->getDbHandler_FileLocker()->getQueryUpdater();
		foreach ( $setNotified as $entry ) {
			$updater->markNotified( $entry );
		}
	}
}