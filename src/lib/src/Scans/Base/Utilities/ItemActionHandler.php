<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\Utilities;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base\HandlerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Databases\Scanner;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller\ScanControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Common\ScanItemConsumer;

abstract class ItemActionHandler {

	use ModConsumer;
	use ScanItemConsumer;
	use ScanControllerConsumer;

	/**
	 * @param string $sAction
	 * @return bool
	 * @throws \Exception
	 */
	public function process( $sAction ) {
		switch ( $sAction ) {
			case 'delete':
				$bSuccess = $this->delete();
				break;

			case 'ignore':
				$bSuccess = $this->ignore();
				break;

			case 'repair':
				$bSuccess = $this->repair();
				break;

			default:
				throw new \Exception( 'Unsupported Scan Item Action' );
				break;
		}
		return $bSuccess;
	}

	/**
	 * @return bool
	 * @throws \Exception
	 */
	public function delete() {
		return $this->getRepairer()
					->setAllowDelete( true )
					->repairItem();
	}

	/**
	 * @return bool
	 * @throws \Exception
	 */
	public function ignore() {
		/** @var Scanner\EntryVO $oEntry */
		$oEntry = $this->getEntryVO();
		if ( empty( $oEntry ) ) {
			throw new \Exception( 'Item could not be found to ignore.' );
		}

		/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oMod */
		$oMod = $this->getMod();
		/** @var Scanner\Update $oUp */
		$oUp = $oMod->getDbHandler_ScanResults()->getQueryUpdater();
		if ( !$oUp->setIgnored( $oEntry ) ) {
			throw new \Exception( 'Item could not be ignored at this time.' );
		}

		return true;
	}

	/**
	 * @return bool
	 * @throws \Exception
	 */
	public function repair() {
		$oRep = $this->getRepairer();
		if ( !$oRep->canRepair() ) {
			throw new \Exception( 'This item cannot be automatically repaired.' );
		}

		$oItem = $this->getScanItem();
		$oItem->repaired = $oRep->repairItem();
		$this->fireRepairEvent( $oItem->repaired );

		if ( $oItem->repaired ) {
			/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oMod */
			$oMod = $this->getMod();
			/** @var Scanner\Delete $oDel */
			$oDel = $oMod->getDbHandler_ScanResults()->getQueryDeleter();
			$oDel->filterByHash( $oItem->hash )
				 ->filterByScan( $oItem->scan )
				 ->query();
		}

		return $oItem->repaired;
	}

	/**
	 * @return Scanner\EntryVO|null
	 */
	protected function getEntryVO() {
		/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oMod */
		$oMod = $this->getMod();
		/** @var Scanner\Select $oSel */
		$oSel = $oMod->getDbHandler_ScanResults()->getQuerySelector();
		return $oSel->filterByHash( $this->getScanItem()->hash )
					->filterByScan( $this->getScanController()->getSlug() )
					->first();
	}

	/**
	 * @return BaseRepair|mixed
	 */
	abstract public function getRepairer();

	/**
	 * @param bool $bSuccess
	 */
	abstract protected function fireRepairEvent( $bSuccess );
}
