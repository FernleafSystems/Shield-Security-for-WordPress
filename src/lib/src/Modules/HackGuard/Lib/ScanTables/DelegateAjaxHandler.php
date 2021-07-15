<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\ScanTables;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Databases;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;
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
		$successfulItems = [];
		$scanSlugs = [];
		foreach ( $items as $ID ) {
			/** @var Shield\Databases\Scanner\EntryVO $entry */
			$entry = $mod->getDbHandler_ScanResults()
						 ->getQuerySelector()
						 ->byId( $ID );
			if ( $entry instanceof Shield\Databases\Scanner\EntryVO ) {
				$scanSlugs[] = $entry->scan;
				if ( $mod->getScanCon( $entry->scan )->executeItemAction( $ID, $action ) ) {
					$successfulItems[] = $ID;
				}
			}
		}

		if ( count( $successfulItems ) === count( $items ) ) {
			$success = true;
			$msg = __( 'Action successful.' );
		}
		else {
			$msg = __( 'An error occurred.' ).' '.__( 'Some items may not have been processed.' );
		}

		// We don't rescan for ignores or malware
		$rescanSlugs = array_diff( $scanSlugs, [ HackGuard\Scan\Controller\Mal::SCAN_SLUG ] );

		if ( empty( $rescanSlugs ) || in_array( $action, [ 'ignore' ] ) ) {
			$msg .= ' '.__( 'Reloading', 'wp-simple-firewall' ).' ...';
		}
		else {
			// rescan
			$mod->getScanQueueController()->startScans( $rescanSlugs );
			$msg .= ' '.__( 'Rescanning', 'wp-simple-firewall' ).' ...';
		}

		return [
			'success'      => $success,
			'page_reload'  => false,
			'table_reload' => in_array( $action, [ 'ignore', 'repair', 'delete', 'repair-delete' ] ),
			'message'      => $msg,
		];
	}

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
	 * @return array
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
			'vars'    => ( new RetrieveFileContents() )
				->setMod( $this->getMod() )
				->retrieve( (int)$rid ),
		];
	}

	/**
	 * @return array
	 * @throws \Exception
	 */
	private function retrieveTableData() :array {
		$req = Services::Request();
		return [
			'success' => true,
			'vars'    => [
				'data' => ( new LoadRawTableData() )
					->setMod( $this->getMod() )
					->loadFor( $req->post( 'type' ), $req->post( 'file' ) )
			],
		];
	}
}