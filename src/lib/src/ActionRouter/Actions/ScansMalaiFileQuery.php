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
		$success = false;

		if ( self::con()->caps->canScanMalwareMalai() ) {
			try {
				$msg = sprintf( '%s: %s', sprintf( __( '%s Status Report' ), 'MAL{ai}' ),
					( new MalwareStatus() )->nameFromStatusLabel( $this->getMalaiStatus() ) );
				$success = true;
			}
			catch ( \Exception $e ) {
				$msg = $e->getMessage();
			}
		}
		else {
			$msg = __( 'Please upgrade your plan to access the MAL{ai} malware service.' );
		}

		$this->response()->action_response_data = [
			'success' => $success,
			'message' => $msg
		];
	}

	/**
	 * @throws \Exception
	 */
	private function getMalaiStatus() :string {
		$FS = Services::WpFs();

		/** @var ResultItem $item */
		$item = ( new RetrieveItems() )->byID( (int)$this->action_data[ 'rid' ] ?? -1 );
		$path = $item->path_full;
		if ( !$FS->isAccessibleFile( $path ) ) {
			throw new \Exception( 'There was a problem locating or reading the file on this site' );
		}
		if ( $FS->getFileSize( $path ) === 0 ) {
			throw new \Exception( 'The file is empty.' );
		}

		$token = self::con()->comps->api_token->getToken();
		$status = ( new MalwareScan( $token ) )->scan( \basename( $path ), $FS->getFileContent( $path ), 'php' );
		if ( empty( $status ) ) {
			\sleep( 3 );
			$status = ( new MalwareScan( $token ) )->scan( \basename( $path ), $FS->getFileContent( $path ), 'php' );
		}

		return (string)$status;
	}
}