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
				$msg = sprintf( '%s: %s', sprintf( __( '%s Status Report', 'wp-simple-firewall' ), 'MAL{ai}' ),
					( new MalwareStatus() )->nameFromStatusLabel( $this->getMalaiStatus() ) );
				$success = true;
			}
			catch ( \Exception $e ) {
				$msg = $e->getMessage();
			}
		}
		else {
			$msg = __( 'Please upgrade your plan to access the MAL{ai} malware service.', 'wp-simple-firewall' );
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
			throw new \Exception( __( 'There was a problem locating or reading the file on this site', 'wp-simple-firewall' ) );
		}
		if ( $FS->getFileSize( $path ) === 0 ) {
			throw new \Exception( __( 'The file is empty.', 'wp-simple-firewall' ) );
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
