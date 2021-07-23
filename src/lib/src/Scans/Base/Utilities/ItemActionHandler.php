<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\Utilities;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Scanner;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\ResultItem;
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

			case 'repair-delete':
				$success = $this->repairDelete();
				break;

			default:
				throw new \Exception( 'Unsupported Scan Item Action' );
		}
		return $success;
	}

	/**
	 * TODO: Determine if "delete" is always the same as a "repair" - see UFC override
	 * @return bool
	 * @throws \Exception
	 */
	public function delete() {
		$item = $this->getScanItem();
		if ( $this->getRepairer()->deleteItem() ) {
			$item->repaired = true;
			$item->repair_event_status = 'delete_success';
		}
		$this->fireRepairEvent();
		return $item->repaired;
	}

	/**
	 * @return bool
	 * @throws \Exception
	 */
	public function ignore() {
		if ( empty( $this->getScanItem()->VO ) ) {
			throw new \Exception( 'Item could not be found to ignore.' );
		}

		/** @var HackGuard\ModCon $mod */
		$mod = $this->getMod();
		/** @var Scanner\Update $updater */
		$updater = $mod->getDbHandler_ScanResults()->getQueryUpdater();
		if ( !$updater->setIgnored( $this->getScanItem()->VO ) ) {
			throw new \Exception( 'Item could not be ignored at this time.' );
		}

		return true;
	}

	/**
	 * @return bool
	 * @throws \Exception
	 */
	public function repairDelete() :bool {
		throw new \Exception( 'Certain items cannot be automatically bulk repaired / deleted.' );
	}

	/**
	 * @param bool $allowDelete
	 * @return bool
	 * @throws \Exception
	 */
	public function repair( bool $allowDelete = false ) {
		$repairer = $this->getRepairer();

		$item = $this->getScanItem();

		try {
			$item->repaired = $repairer->repairItem();
		}
		catch ( \Exception $e ) {
			$item->repaired = false;
		}
		$item->repair_event_status = $item->repaired ? 'repair_success' : 'repair_fail';

		if ( $allowDelete && !$item->repaired && $repairer->deleteItem() ) {
			$item->repaired = true;
			$item->repair_event_status = 'delete_success';
		}

		$this->fireRepairEvent();

		if ( $item->repaired ) {
			/** @var HackGuard\ModCon $mod */
			$mod = $this->getMod();
			/** @var Scanner\Delete $deleter */
			$mod->getDbHandler_ScanResults()
				->getQueryDeleter()
				->deleteById( $item->VO->id );
		}

		return $item->repaired;
	}

	/**
	 * @return BaseRepair|mixed
	 */
	abstract public function getRepairer();

	protected function fireRepairEvent() {
		/** @var ResultItem $item */
		$item = $this->getScanItem();

		if ( !empty( $item->path_full ) ) {
			$this->getCon()->fireEvent(
				sprintf( 'scan_item_%s', $item->repair_event_status ),
				[ 'audit' => [ 'path_full' => $item->path_full ] ]
			);
		}
	}
}