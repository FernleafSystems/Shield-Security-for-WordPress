<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\Utilities;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Scanner;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Common\ScanItemConsumer;

abstract class ItemActionHandler {

	use ModConsumer;
	use ScanItemConsumer;
	use HackGuard\Scan\Controller\ScanControllerConsumer;

	/**
	 * @param string $action
	 * @return bool
	 * @throws \Exception
	 */
	public function process( $action ) {
		switch ( $action ) {
			case 'delete':
				$success = $this->delete();
				break;

			case 'ignore':
				$success = $this->ignore();
				break;

			case 'repair':
				$success = $this->repair();
				break;

			default:
				throw new \Exception( 'Unsupported Scan Item Action' );
				break;
		}
		return $success;
	}

	/**
	 * TODO: Determine if "delete" is always the same as a "repair" - see UFC override
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

		/** @var HackGuard\ModCon $mod */
		$mod = $this->getMod();
		/** @var Scanner\Update $updater */
		$updater = $mod->getDbHandler_ScanResults()->getQueryUpdater();
		if ( !$updater->setIgnored( $oEntry ) ) {
			throw new \Exception( 'Item could not be ignored at this time.' );
		}

		return true;
	}

	/**
	 * @param bool $bAllowDelete
	 * @return bool
	 * @throws \Exception
	 */
	public function repair( $bAllowDelete = false ) {
		$oRep = $this->getRepairer();
		if ( !$oRep->canRepair() ) {
			throw new \Exception( 'This item cannot be automatically repaired.' );
		}

		$oRep->setAllowDelete( $bAllowDelete );

		$oItem = $this->getScanItem();
		$oItem->repaired = $oRep->repairItem();
		$this->fireRepairEvent( $oItem->repaired );

		if ( $oItem->repaired ) {
			/** @var HackGuard\ModCon $mod */
			$mod = $this->getMod();
			/** @var Scanner\Delete $oDel */
			$oDel = $mod->getDbHandler_ScanResults()->getQueryDeleter();
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
		/** @var HackGuard\ModCon $mod */
		$mod = $this->getMod();
		/** @var Scanner\Select $oSel */
		$oSel = $mod->getDbHandler_ScanResults()->getQuerySelector();
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
