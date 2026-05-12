<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Retrieve\RetrieveItems;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Processing\MalwareStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\ResultItem;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Utilities\MalaiFileQueryEligibility;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Integrations\WpHashes\Malai\MalwareScan;

class ScansMalaiFileQuery extends ScansBase {

	public const SLUG = 'scans_malai_file_query';

	protected function exec() {
		$success = false;

		if ( self::con()->caps->canScanMalwareMalai() ) {
			if ( ( $this->action_data[ 'confirm' ] ?? '' ) !== 'Y' ) {
				$msg = __( 'You must confirm that you accept the implications of submitting this file to MAL{ai}.', 'wp-simple-firewall' );
			}
			else {
				try {
					$msg = sprintf( '%s: %s', sprintf( __( '%s Status Report', 'wp-simple-firewall' ), 'MAL{ai}' ),
						( new MalwareStatus() )->nameFromStatusLabel( $this->getMalaiStatus() ) );
					$success = true;
				}
				catch ( \Exception $e ) {
					$msg = $e->getMessage();
				}
			}
		}
		else {
			$msg = __( 'Please upgrade your plan to access the MAL{ai} malware service.', 'wp-simple-firewall' );
		}

		$this->response()->setPayload( [
			'message' => $msg
		] )->setPayloadSuccess( $success );
	}

	/**
	 * @throws \Exception
	 */
	private function getMalaiStatus() :string {
		$FS = Services::WpFs();

		$item = ( new RetrieveItems() )->byID( (int)( $this->action_data[ 'rid' ] ?? -1 ) );
		if ( !$item instanceof ResultItem ) {
			throw new \Exception( __( 'The selected scan result is not a supported file item.', 'wp-simple-firewall' ) );
		}
		$path = ( new MalaiFileQueryEligibility() )->assertCanSubmitQuery( $item );

		$token = self::con()->comps->api_token->getToken();
		$status = ( new MalwareScan( $token ) )->scan( \basename( $path ), $FS->getFileContent( $path ), 'php' );
		if ( empty( $status ) ) {
			\sleep( 3 );
			$status = ( new MalwareScan( $token ) )->scan( \basename( $path ), $FS->getFileContent( $path ), 'php' );
		}

		return (string)$status;
	}
}
