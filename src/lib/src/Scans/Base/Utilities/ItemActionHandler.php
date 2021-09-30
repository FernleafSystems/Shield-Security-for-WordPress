<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\Utilities;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\ResultItem;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Common\ScanItemConsumer;
use FernleafSystems\Wordpress\Services\Services;

abstract class ItemActionHandler {

	use ModConsumer;
	use ScanItemConsumer;
	use HackGuard\Scan\Controller\ScanControllerConsumer;

	/**
	 * @throws \Exception
	 */
	public function process( string $action ) :bool {
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
	 * @return bool
	 * @throws \Exception
	 */
	public function delete() :bool {
		/** @var HackGuard\ModCon $mod */
		$mod = $this->getMod();
		$item = $this->getScanItem();

		$item->deleted = ( new ItemDeleteHandler() )
			->setMod( $this->getMod() )
			->setScanItem( $this->getScanItem() )
			->delete(); // Exception if can't delete
		if ( $item->deleted ) {
			$mod->getDbH_ResultItems()
				->getQueryUpdater()
				->updateById( $item->VO->resultitem_id, [
					'item_deleted_at' => Services::Request()->ts()
				] );
			$item->repair_event_status = 'delete_success';
		}

		$this->fireRepairEvent();
		return $item->deleted;
	}

	/**
	 * @throws \Exception
	 */
	public function ignore() :bool {
		return ( new ItemIgnoreHandler() )
			->setMod( $this->getMod() )
			->setScanItem( $this->getScanItem() )
			->ignore();
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
	public function repair( bool $allowDelete = false ) :bool {
		/** @var HackGuard\ModCon $mod */
		$mod = $this->getMod();
		$item = $this->getScanItem();

		$repairer = $this->getRepairHandler();
		if ( $repairer->canRepairItem() ) {
			try {
				$item->repaired = $repairer->repairItem();
			}
			catch ( \Exception $e ) {
				$item->repaired = false;
			}

			$updateInfo = [
				'attempt_repair_at' => Services::Request()->ts()
			];
			if ( $item->repaired ) {
				$updateInfo[ 'item_repaired_at' ] = Services::Request()->ts();
			}
			$mod->getDbH_ResultItems()
				->getQueryUpdater()
				->updateById( $item->VO->resultitem_id, $updateInfo );

			$item->repair_event_status = $item->repaired ? 'repair_success' : 'repair_fail';

			$this->fireRepairEvent();
		}
		elseif ( $allowDelete ) {
			$this->delete();
		}

		return $item->repaired || $item->deleted;
	}

	public function getRepairHandler() :ItemRepairHandler {
		return ( new ItemRepairHandler() )
			->setMod( $this->getMod() )
			->setScanItem( $this->getScanItem() );
	}

	protected function fireRepairEvent() {
		/** @var ResultItem $item */
		$item = $this->getScanItem();

		if ( !empty( $item->path_full ) && !empty( $item->repair_event_status ) ) {
			$this->getCon()->fireEvent(
				sprintf( 'scan_item_%s', $item->repair_event_status ),
				[ 'audit_params' => [ 'path_full' => $item->path_full ] ]
			);
		}
	}
}