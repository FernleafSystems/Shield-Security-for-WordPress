<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Retrieve\RetrieveItems;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Processing\MalwareStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\ResultItem;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Integrations\WpHashes\Malai\MalwareScan;

class ScansMalaiFileQuery extends ScansBase {

	public const SLUG = 'scans_malai_file_query';

	protected function exec() {
		$req = Services::Request();
		$FS = Services::WpFs();

		try {
			/** @var ResultItem $item */
			$item = ( new RetrieveItems() )->byID( (int)$req->post( 'rid' ) );
			$path = $item->path_full;
			if ( !$FS->isAccessibleFile( $path ) ) {
				throw new \Exception( 'There was a problem locating or reading the file on this site' );
			}
			if ( $FS->getFileSize( $path ) === 0 ) {
				throw new \Exception( 'File is empty.' );
			}

			$status = ( new MalwareScan() )->scan( basename( $path ), $FS->getFileContent( $path ), 'php' );
			if ( empty( $status ) ) {
				sleep( 3 );
				$status = ( new MalwareScan() )->scan( basename( $path ), $FS->getFileContent( $path ), 'php' );
			}

			$msg = sprintf( '%s: %s',
				sprintf( __( '%s Status Report' ), 'MAL{ai}' ),
				( new MalwareStatus() )->nameFromStatusLabel( (string)$status )
			);
			$success = true;
		}
		catch ( \Exception $e ) {
			$success = false;
			$msg = 'There was a problem locating or reading the file on this site.';
		}
		$this->response()->action_response_data = [
			'success' => $success,
			'message' => $msg
		];
	}
}