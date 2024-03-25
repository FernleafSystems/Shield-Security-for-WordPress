<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Retrieve\RetrieveItems;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\Scans\BuildScanTableData;

class ScanResultsTableAction extends ScansBase {

	public const SLUG = 'scanresults_action';

	protected function exec() {
		try {
			switch ( $this->action_data[ 'sub_action' ] ?? '' ) {
				case 'retrieve_table_data':
					$response = $this->retrieveTableData();
					break;
				case 'delete':
				case 'ignore':
				case 'repair':
				case 'repair-delete':
					$response = $this->doAction( $this->action_data[ 'sub_action' ] );
					break;
				default:
					throw new \Exception( 'Not a supported scan tables sub_action: '.$this->action_data[ 'sub_action' ] );
			}
		}
		catch ( \Exception $e ) {
			$response = [
				'success'     => false,
				'page_reload' => true,
				'message'     => $e->getMessage(),
			];
		}

		$this->response()->action_response_data = $response;
	}

	/**
	 * @throws \Exception
	 */
	private function doAction( string $action ) :array {
		$items = $this->getItemIDs();

		$scanSlugs = [];
		$successfulItems = [];
		foreach ( $items as $itemID ) {
			try {
				$item = ( new RetrieveItems() )->byID( $itemID );
				$scanSlugs[] = $item->VO->scan;
				if ( self::con()->comps->scans->getScanCon( $item->VO->scan )->executeItemAction( $item, $action ) ) {
					$successfulItems[] = $item->VO->scanresult_id;
				}
			}
			catch ( \Exception $e ) {
			}
		}

		foreach ( \array_unique( $scanSlugs ) as $slug ) {
			self::con()->comps->scans->getScanCon( $slug )->cleanStalesResults();
		}

		if ( \count( $successfulItems ) === \count( $items ) ) {
			$success = true;
			switch ( $action ) {
				case 'delete':
					$msg = __( 'Delete Success', 'wp-simple-firewall' );
					break;
				case 'ignore':
					$msg = __( 'Ignore Success', 'wp-simple-firewall' );
					break;
				case 'repair':
					$msg = __( 'Repair Success', 'wp-simple-firewall' );
					break;
				case 'repair-delete':
					$msg = __( 'Repair/Delete Success', 'wp-simple-firewall' );
					break;
				default:
					$msg = __( 'Success', 'wp-simple-firewall' );
					break;
			}
			$itemCount = \count( $items );
			$msg = sprintf( '%s: %s', $msg,
				sprintf( _n( '%s item processed', '%s items processed', $itemCount, 'wp-simple-firewall' ), $itemCount ) );
		}
		else {
			$success = false;
			$msg = __( 'An error occurred.', 'wp-simple-firewall' )
				   .' '.__( 'Some items may not have been processed.', 'wp-simple-firewall' );
		}

		return [
			'success'      => $success,
			'page_reload'  => false,
			'table_reload' => \in_array( $action, [ 'ignore', 'repair', 'delete', 'repair-delete' ] ),
			'message'      => $msg,
		];
	}

	/**
	 * @throws \Exception
	 */
	private function getItemIDs() :array {
		$items = $this->action_data[ 'rids' ] ?? '';
		if ( empty( $items ) || !\is_array( $items ) ) {
			throw new \Exception( 'No items selected.' );
		}
		return \array_filter(
			\array_map(
				function ( $rid ) {
					return \is_numeric( $rid ) ? \intval( $rid ) : null;
				},
				$items
			),
			function ( $rid ) {
				return !\is_null( $rid );
			}
		);
	}

	/**
	 * @throws \Exception
	 */
	private function retrieveTableData() :array {
		$builder = new BuildScanTableData();
		$builder->table_data = $this->action_data[ 'table_data' ] ?? [];
		$builder->type = $this->action_data[ 'type' ] ?? '';
		$builder->file = $this->action_data[ 'file' ] ?? '';
		return [
			'success'        => true,
			'datatable_data' => $builder->build(),
		];
	}
}