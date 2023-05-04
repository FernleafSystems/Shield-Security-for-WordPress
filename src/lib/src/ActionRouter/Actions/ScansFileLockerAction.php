<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops\{
	Accept,
	Restore
};
use FernleafSystems\Wordpress\Services\Services;

class ScansFileLockerAction extends ScansBase {

	public const SLUG = 'filelocker_fileaction';

	protected function exec() {
		$mod = $this->con()->getModule_HackGuard();
		$FLCon = $mod->getFileLocker();
		$req = Services::Request();
		$success = false;

		if ( $req->post( 'confirmed' ) == '1' ) {
			try {
				$lock = $FLCon->getFileLock( (int)$req->post( 'rid' ) );

				switch ( $req->post( 'file_action' ) ) {
					case 'accept':
						$success = ( new Accept() )->run( $lock );
						break;
					case 'restore':
						$success = ( new Restore() )->run( $lock );
						break;
					default:
						throw new \Exception( __( 'Not a supported file lock action.', 'wp-simple-firewall' ) );
				}

				$msg = $success ?
					__( 'Requested action completed successfully.', 'wp-simple-firewall' )
					: __( 'Requested action failed.', 'wp-simple-firewall' );
			}
			catch ( \Exception $e ) {
				$msg = __( 'Requested action failed.', 'wp-simple-firewall' );
			}
		}
		else {
			$msg = __( 'Please check the box to confirm this action', 'wp-simple-firewall' );
		}

		$this->response()->action_response_data = [
			'success' => $success,
			'message' => $msg,
		];
	}
}