<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Reports;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\BaseReporting;
use FernleafSystems\Wordpress\Plugin\Shield\Databases\FileLocker;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;

class FileLockerAlerts extends BaseReporting {

	/**
	 * @inheritDoc
	 */
	public function buildAlerts() {
		$aAlerts = [];

		/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oMod */
		$oMod = $this->getMod();

		$oLockOps = ( new HackGuard\Lib\FileLocker\Ops\LoadFileLocks() )
			->setMod( $this->getMod() );
		$aNotNotified = $oLockOps->withProblemsNotNotified();

		if ( count( $aNotNotified ) > 0 ) {
			$aAlerts[] = $this->getMod()->renderTemplate(
				'/components/reports/mod/hack_protect/alert_filelocker.twig',
				[
					'vars'    => [
						'count' => $oMod->getFileLocker()->countProblems()
					],
					'strings' => [
						'title'        => __( 'File Locker Issues', 'wp-simple-firewall' ),
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
		/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oMod */
		$oMod = $this->getMod();
		/** @var FileLocker\Update $oUpdater */
		$oUpdater = $oMod->getDbHandler_FileLocker()->getQueryUpdater();
		foreach ( $aNotNotified as $oEntry ) {
			$oUpdater->markNotified( $oEntry );
		}
	}
}