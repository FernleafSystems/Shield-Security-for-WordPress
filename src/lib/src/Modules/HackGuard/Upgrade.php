<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

class Upgrade extends Base\Upgrade {

	protected function upgrade_900() {
		/** @var Options $oOpts */
		$oOpts = $this->getOptions();
		if ( $oOpts->getOpt( 'ptg_enable' ) === 'enabled' ) {
			$oOpts->setOpt( 'ptg_enable', 'Y' );
		}
		elseif ( $oOpts->getOpt( 'ptg_enable' ) === 'disabled' ) {
			$oOpts->setOpt( 'ptg_enable', 'N' );
		}

		/**
		 * @deprecated 9.0
		 */
		{
			if ( $oOpts->getOpt( 'mal_scan_enable' ) === 'enabled' ) {
				$oOpts->setOpt( 'mal_scan_enable', 'Y' );
			}
			elseif ( $oOpts->getOpt( 'mal_scan_enable' ) === 'disabled' ) {
				$oOpts->setOpt( 'mal_scan_enable', 'N' );
			}
		}

		$aRepairAreas = $oOpts->getRepairAreas();
		$aMap = [
			'attempt_auto_file_repair' => 'wp',
			'mal_autorepair_plugins'   => 'plugin',
		];
		foreach ( $aMap as $sOld => $sNew ) {
			if ( $oOpts->getOpt( $sOld ) !== false ) {
				$bWasEnabled = $oOpts->isOpt( $sOld, 'Y' );
				$nIsEnabled = array_search( $sNew, $aRepairAreas );
				if ( $bWasEnabled && ( $nIsEnabled === false ) ) {
					$aRepairAreas[] = $sNew;
				}
				elseif ( !$bWasEnabled && ( $nIsEnabled !== false ) ) {
					unset( $aRepairAreas[ $nIsEnabled ] );
				}
			}
		}
		$oOpts->setOpt( 'file_repair_areas', $aRepairAreas );

		{ // migrate old scan options
			if ( $oOpts->getOpt( 'enable_unrecognised_file_cleaner_scan' ) == 'enabled_delete_report' ) {
				$oOpts->setOpt( 'enable_unrecognised_file_cleaner_scan', 'enabled_delete_only' );
			}
			$sApcOpt = $oOpts->getOpt( 'enabled_scan_apc' );
			if ( strlen( $sApcOpt ) > 1 ) {
				$oOpts->setOpt( 'enabled_scan_apc', $sApcOpt == 'disabled' ? 'N' : 'Y' );
			}
			$sWpvOpt = $oOpts->getOpt( 'enable_wpvuln_scan' );
			if ( strlen( $sWpvOpt ) > 1 ) {
				$oOpts->setOpt( 'enable_wpvuln_scan', $sWpvOpt == 'disabled' ? 'N' : 'Y' );
			}
		}
	}
}