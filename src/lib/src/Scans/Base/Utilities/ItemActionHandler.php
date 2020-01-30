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
	use HandlerConsumer;
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

		/** @var Scanner\Update $oUp */
		$oUp = $this->getDbHandler()->getQueryUpdater();
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
		$bSuccess = $oRep->repairItem();
		$this->fireRepairEvent( $bSuccess );
		return $bSuccess;
	}

	/**
	 * @return Scanner\EntryVO|null
	 */
	protected function getEntryVO() {
		/** @var Scanner\Select $oSel */
		$oSel = $this->getDbHandler()->getQuerySelector();
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
