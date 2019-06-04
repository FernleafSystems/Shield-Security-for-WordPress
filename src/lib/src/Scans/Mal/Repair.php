<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Mal;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services;
use FernleafSystems\Wordpress\Services\Utilities\WpOrg;

/**
 * Class Repair
 * @package FernleafSystems\Wordpress\Plugin\Shield\Scans\Mal
 */
class Repair extends Shield\Scans\Base\BaseRepair {

	use Shield\Modules\ModConsumer;

	/**
	 * @param ResultItem $oItem
	 * @return bool
	 */
	public function repairItem( $oItem ) {
		/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oMod */
		$oMod = $this->getMod();
		$bSuccess = false;

		if ( $oMod->isMalAutoRepairCore()
			 && Services\Services::CoreFileHashes()->isCoreFile( $oItem->path_fragment ) ) {
			$bSuccess = $this->repairCoreItem( $oItem );
		}
		else {
			$oPlugin = ( new WpOrg\Plugin\Files() )->findPluginFromFile( $oItem->path_full );
			if ( $oMod->isMalAutoRepairPlugins()
				 && $oPlugin instanceof Services\Core\VOs\WpPluginVo && $oPlugin->isWpOrg() ) {
				$bSuccess = $this->repairItemInPlugin( $oItem );
			}
			else if ( $oMod->isMalAutoRepairSurgical() ) {
				$bSuccess = $this->repairSurgicalItem( $oItem );
			}
		}

		return $bSuccess;
	}

	/**
	 * @param ResultItem $oItem
	 * @return bool
	 */
	private function repairCoreItem( $oItem ) {
		$oFiles = Services\Services::WpGeneral()->isClassicPress() ? new WpOrg\Cp\Files() : new WpOrg\Wp\Files();
		try {
			$bSuccess = $oFiles->replaceFileFromVcs( $oItem->path_fragment );
		}
		catch ( \InvalidArgumentException $oE ) {
			$bSuccess = false;
		}
		return $bSuccess;
	}

	/**
	 * @param ResultItem $oItem
	 * @return bool
	 */
	private function repairSurgicalItem( $oItem ) {
		$bSuccess = false;
		foreach ( $oItem->file_lines as $nLine ) {
			try {
				( new Services\Utilities\File\RemoveLineFromFile() )->run( $oItem->path_full, $nLine );
				$bSuccess = true;
			}
			catch ( \Exception $oE ) {
				$bSuccess = false;
				break;
			}
		}
		return $bSuccess;
	}

	/**
	 * @param ResultItem $oItem
	 * @return bool
	 */
	private function repairItemInPlugin( $oItem ) {
		$oFiles = new WpOrg\Plugin\Files();
		try {
			if ( $oFiles->isValidFileFromPlugin( $oItem->path_fragment ) ) {
				$bSuccess = $oFiles->replaceFileFromVcs( $oItem->path_fragment );
			}
			else {
				$bSuccess = Services\Services::WpFs()->deleteFile( $oItem->path_full );
			}
		}
		catch ( \InvalidArgumentException $oE ) {
			$bSuccess = false;
		}
		return (bool)$bSuccess;
	}
}