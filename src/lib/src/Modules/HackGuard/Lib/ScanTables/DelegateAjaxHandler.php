<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\ScanTables;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\ScanTables\Modals\ScanItemView;
use FernleafSystems\Wordpress\Services\Services;

class DelegateAjaxHandler {

	use Shield\Modules\ModConsumer;

	/**
	 * @return array
	 * @throws \Exception
	 */
	public function processAjaxAction() :array {
		$action = Services::Request()->post( 'sub_action' );
		switch ( $action ) {

			case 'retrieve_table_data':
				$response = $this->retrieveTableData();
				break;

			case 'delete':
			case 'ignore':
			case 'repair':
			case 'repair-delete':
				$response = $this->doAction( $action );
				break;

			case 'view_file':
				$response = $this->viewFile();
				break;

			default:
				throw new \Exception( 'Not a supported scan tables sub_action: '.$action );
		}
		return $response;
	}

	/**
	 * @param string $action
	 * @return array
	 * @throws \Exception
	 */
	private function doAction( string $action ) :array {
		/** @var HackGuard\ModCon $mod */
		$mod = $this->getMod();

		$success = false;

		$items = $this->getItemIDs();

		$scanSlugs = [];
		$successfulItems = [];
		foreach ( $items as $itemID ) {
			try {
				$item = ( new HackGuard\Scan\Results\Retrieve() )
					->setMod( $this->getMod() )
					->byID( $itemID );
				$scanSlugs[] = $item->VO->scan;
				if ( $mod->getScanCon( $item->VO->scan )->executeItemAction( $item, $action ) ) {
					$successfulItems[] = $item->VO->scanresult_id;
				}
			}
			catch ( \Exception $e ) {
			}
		}

		$scanSlugs = array_unique( $scanSlugs );
		foreach ( $scanSlugs as $slug ) {
			$mod->getScanCon( $slug )->cleanStalesResults();
		}

		if ( count( $successfulItems ) === count( $items ) ) {
			$success = true;
			$msg = __( 'Action successful.' );
		}
		else {
			$msg = __( 'An error occurred.' )
				   .' '.__( 'Some items may not have been processed.' );
		}

		return [
			'success'      => $success,
			'page_reload'  => false,
			'table_reload' => in_array( $action, [ 'ignore', 'repair', 'delete', 'repair-delete' ] ),
			'message'      => $msg,
		];
	}

	/**
	 * @throws \Exception
	 */
	private function getItemIDs() :array {
		$items = Services::Request()->post( 'rids' );
		if ( empty( $items ) || !is_array( $items ) ) {
			throw new \Exception( 'No items selected.' );
		}
		return array_filter(
			array_map(
				function ( $rid ) {
					return is_numeric( $rid ) ? intval( $rid ) : null;
				},
				$items
			),
			function ( $rid ) {
				return !is_null( $rid );
			}
		);
	}

	/**
	 * @throws \Exception
	 */
	private function viewFile() :array {
		$req = Services::Request();
		$rid = $req->post( 'rid' );
		if ( !is_numeric( $rid ) ) {
			throw new \Exception( 'Not a valid file to view' );
		}

		return [
			'success' => true,
			'vars'    => ( new ScanItemView() )
				->setMod( $this->getMod() )
				->run( (int)$rid ),
		];
	}

	/**
	 * @throws \Exception
	 */
	private function retrieveTableData() :array {
		$req = Services::Request();
		return [
			'success' => true,
			'vars'    => [
				'data' => array_values( ( new LoadRawTableData() )
					->setMod( $this->getMod() )
					->loadFor( $req->post( 'type' ), $req->post( 'file' ) ) )
			],
		];
	}
}