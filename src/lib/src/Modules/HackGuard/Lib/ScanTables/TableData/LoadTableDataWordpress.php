<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\ScanTables\TableData;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Retrieve;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Controller\Afs;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;
use FernleafSystems\Wordpress\Services\Services;

class LoadTableDataWordpress extends BaseLoadTableData {

	public function run() :array {
		$RS = $this->getRecordRetriever()->retrieveLatest();
		try {
			$files = array_map(
				function ( $item ) {
					/** @var Scans\Afs\ResultItem $item */
					$data = $item->getRawData();

					$data[ 'rid' ] = $item->VO->scanresult_id;
					$data[ 'file' ] = $item->path_fragment;
					$data[ 'created_at' ] = $item->VO->created_at;
					$data[ 'detected_since' ] = Services::Request()
														->carbon( true )
														->setTimestamp( $item->VO->created_at )
														->diffForHumans();

					$data[ 'file_as_href' ] = $this->getColumnContent_File( $item );

					if ( $item->is_checksumfail ) {
						$data[ 'status_slug' ] = 'modified';
						$data[ 'status' ] = __( 'Modified', 'wp-simple-firewall' );
					}
					elseif ( $item->is_missing ) {
						$data[ 'status_slug' ] = 'missing';
						$data[ 'status' ] = __( 'Missing', 'wp-simple-firewall' );
					}
					else {
						$data[ 'status_slug' ] = 'unrecognised';
						$data[ 'status' ] = __( 'Unrecognised', 'wp-simple-firewall' );
					}
					$data[ 'status' ] = $this->getColumnContent_FileStatus( $item, $data[ 'status' ] );

					$data[ 'file_type' ] = strtoupper( Services::Data()->getExtension( $item->path_full ) );
					$data[ 'actions' ] = implode( ' ', $this->getActions( $data[ 'status_slug' ], $item ) );
					return $data;
				},
				$RS->getWordpressCore()->getItems()
			);
		}
		catch ( \Exception $e ) {
			$files = [];
		}

		return $files;
	}

	protected function getRecordRetriever() :Retrieve {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$retriever = parent::getRecordRetriever();
		$retriever->setScanController( $mod->getScanCon( Afs::SCAN_SLUG ) );
		$retriever->wheres = [
			"`rim`.`meta_key`='is_in_core'",
			"`rim`.`meta_value`=1",
		];
		return $retriever;
	}
}