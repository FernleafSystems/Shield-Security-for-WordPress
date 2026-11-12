<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Utility\ExternalEntryGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops\{
	Accept,
	Restore
};

class ScansFileLockerAction extends ScansBase {

	public const SLUG = 'filelocker_fileaction';

	public static function ExternalAccess() :array {
		return [
			ExternalEntryGuard::ENTRY_AJAX => ExternalEntryGuard::PROOF_NONCE,
		];
	}

	protected function exec() {
		$fields = $this->fields();
		$success = false;

		// The page sends 1 when the confirmation box is ticked and 0 when it isn't.
		if ( $fields->flag( 'confirmed', '1', '0', false ) ) {
			$lockID = $fields->positiveInt( 'rid' );
			$fileAction = $fields->oneOf( 'file_action', [ 'accept', 'restore' ] );
			try {
				$lock = self::con()->comps->file_locker->getFileLock( $lockID );

				switch ( $fileAction ) {
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

		$this->response()->setPayload( [
			'message'     => $msg,
			'page_reload' => $success,
		] )->setPayloadSuccess( $success );
	}
}
