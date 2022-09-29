<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModCon;
use FernleafSystems\Wordpress\Services\Services;

class ScansFileLockerAction extends ScansBase {

	const SLUG = 'filelocker_fileaction';

	protected function exec() {
		/** @var ModCon $mod */
		$mod = $this->primary_mod;
		$FLCon = $mod->getFileLocker();
		$req = Services::Request();
		$success = false;

		if ( $req->post( 'confirmed' ) == '1' ) {
			try {
				$lock = $FLCon->getFileLock( (int)$req->post( 'rid' ) );
				$success = ( new FileLocker\Ops\PerformAction() )
					->setMod( $mod )
					->run( $lock, (string)$req->post( 'file_action' ) );
				$msg = __( 'Requested action completed successfully.', 'wp-simple-firewall' );
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