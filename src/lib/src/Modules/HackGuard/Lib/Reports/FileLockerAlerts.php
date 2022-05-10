<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Reports;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\FileLocker;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting\Lib\Reports\BaseReporter;

class FileLockerAlerts extends BaseReporter {

	public function build() :array {
		$alerts = [];

		/** @var HackGuard\ModCon $mod */
		$mod = $this->getMod();

		$lockOps = ( new HackGuard\Lib\FileLocker\Ops\LoadFileLocks() )
			->setMod( $this->getMod() );
		$notNotified = $lockOps->withProblemsNotNotified();

		if ( count( $notNotified ) > 0 ) {
			$alerts[] = $this->getMod()->renderTemplate( '/components/reports/mod/hack_protect/alert_filelocker.twig', [
				'hrefs'   => [
					'view_results' => $this->getCon()->getModule_Insights()->getUrl_ScansResults(),
				],
				'strings' => [
					'title'        => __( 'File Locker Changes Detected', 'wp-simple-firewall' ),
					'file_changed' => __( 'Changes have been detected in the contents of critical files.', 'wp-simple-firewall' ),
					'total_files'  => sprintf( '%s: %s', __( 'Total Changed Files', 'wp-simple-firewall' ), count( $notNotified ) ),
					'view_results' => __( 'Click Here To View File Locker Results', 'wp-simple-firewall' ),
				],
				'vars'    => [
					'count' => $mod->getFileLocker()->countProblems()
				],
			] );
			$this->markAlertsAsNotified( $notNotified );
			$lockOps->clearLocksCache();
		}

		return $alerts;
	}

	/**
	 * @param FileLocker\EntryVO[] $setNotified
	 */
	private function markAlertsAsNotified( $setNotified ) {
		/** @var HackGuard\ModCon $mod */
		$mod = $this->getMod();
		/** @var FileLocker\Update $updater */
		$updater = $mod->getDbHandler_FileLocker()->getQueryUpdater();
		foreach ( $setNotified as $entry ) {
			$updater->markNotified( $entry );
		}
	}
}