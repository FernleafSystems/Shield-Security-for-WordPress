<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\Utilities;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller\ScanControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Base\ResultItem;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Common\ScanItemConsumer;
use FernleafSystems\Wordpress\Services\Services;

abstract class ItemActionHandler {

	use PluginControllerConsumer;
	use ScanItemConsumer;
	use ScanControllerConsumer;

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
	 * @throws \Exception
	 */
	public function delete() :bool {
		$item = $this->getScanItem();

		$item->deleted = ( new ItemDeleteHandler() )
			->setScanItem( $item )
			->delete(); // Exception if can't delete
		if ( $item->deleted ) {
			self::con()
				->db_con
				->scan_result_items
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
			->setScanItem( $this->getScanItem() )
			->ignore();
	}

	/**
	 * @throws \Exception
	 */
	public function repairDelete() :bool {
		throw new \Exception( 'Certain items cannot be automatically bulk repaired / deleted.' );
	}

	/**
	 * @throws \Exception
	 */
	public function repair( bool $allowDelete = false ) :bool {
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
			self::con()
				->db_con
				->scan_result_items
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
		return ( new ItemRepairHandler() )->setScanItem( $this->getScanItem() );
	}

	protected function fireRepairEvent() {
		/** @var ResultItem $item */
		$item = $this->getScanItem();

		if ( !empty( $item->path_fragment ) && !empty( $item->repair_event_status ) ) {
			self::con()->fireEvent(
				sprintf( 'scan_item_%s', $item->repair_event_status ),
				[ 'audit_params' => [ 'path_full' => $item->path_full ] ]
			);
		}
	}
}