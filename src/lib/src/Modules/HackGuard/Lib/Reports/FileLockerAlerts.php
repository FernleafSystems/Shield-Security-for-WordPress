<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Reports;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\FileLocker;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting\Lib\Reports\BaseReporter;

class FileLockerAlerts extends BaseReporter {

	/**
	 * @inheritDoc
	 */
	public function build() {
		$aAlerts = [];

		/** @var HackGuard\ModCon $mod */
		$mod = $this->getMod();

		$oLockOps = ( new HackGuard\Lib\FileLocker\Ops\LoadFileLocks() )
			->setMod( $this->getMod() );
		$aNotNotified = $oLockOps->withProblemsNotNotified();

		if ( count( $aNotNotified ) > 0 ) {
			$aAlerts[] = $this->getMod()->renderTemplate(
				'/components/reports/mod/hack_protect/alert_filelocker.twig',
				[
					'vars'    => [
						'count' => $mod->getFileLocker()->countProblems()
					],
					'strings' => [
						'title'        => __( 'File Locker Changes Detected', 'wp-simple-firewall' ),
						'file_changed' => __( 'Changes have been detected in the contents of critical files.', 'wp-simple-firewall' ),
						'total_files'  => sprintf( '%s: %s', __( 'Total Changed Files', 'wp-simple-firewall' ), count( $aNotNotified ) ),
						'view_results' => __( 'Click Here To View File Locker Results', 'wp-simple-firewall' ),
					],
					'hrefs'   => [
						'view_results' => $this->getCon()->getModule_Insights()->getUrl_SubInsightsPage( 'scans' ),
					],
				]
			);
			$this->markAlertsAsNotified( $aNotNotified );
			$oLockOps->clearLocksCache();
		}

		return $aAlerts;
	}

	/**
	 * @param FileLocker\EntryVO[] $aNotNotified
	 */
	private function markAlertsAsNotified( $aNotNotified ) {
		/** @var HackGuard\ModCon $mod */
		$mod = $this->getMod();
		/** @var FileLocker\Update $oUpdater */
		$oUpdater = $mod->getDbHandler_FileLocker()->getQueryUpdater();
		foreach ( $aNotNotified as $oEntry ) {
			$oUpdater->markNotified( $oEntry );
		}
	}
}