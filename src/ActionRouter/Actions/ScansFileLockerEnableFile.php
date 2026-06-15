<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\FileLocker\Ops\GetFileLockCandidateDisplays;

class ScansFileLockerEnableFile extends ScansBase {

	public const SLUG = 'filelocker_enable_file';

	protected function exec() {
		$fileKey = sanitize_key( (string)$this->action_data[ 'file_key' ] );
		$success = false;

		try {
			$this->assertFileCanBeEnabled( $fileKey );

			$filesToLock = \array_values( \array_filter(
				\array_unique( \array_map(
					static fn( $file ) :string => sanitize_key( (string)$file ),
					self::con()->comps->file_locker->getFilesToLock()
				) ),
				static fn( string $file ) :bool => $file !== ''
			) );
			if ( !\in_array( $fileKey, $filesToLock, true ) ) {
				$filesToLock[] = $fileKey;
				self::con()->opts->optSet( 'file_locker', $filesToLock )->store();
			}

			self::con()->comps->file_locker->clearLocks();
			self::con()->comps->file_locker->scheduleLocksCreationIfNeeded();
			$success = true;
			$msg = __( 'File Locker has been enabled for this file.', 'wp-simple-firewall' );
		}
		catch ( \Exception $e ) {
			$msg = __( 'File Locker could not be enabled for this file.', 'wp-simple-firewall' );
		}

		$this->response()->setPayload( [
			'message'     => $msg,
			'page_reload' => false,
			'file_key'    => $fileKey,
		] )->setPayloadSuccess( $success );
	}

	protected function getRequiredDataKeys() :array {
		return [
			'file_key',
		];
	}

	/**
	 * @throws \Exception
	 */
	private function assertFileCanBeEnabled( string $fileKey ) :void {
		if ( !self::con()->isPremiumActive() || !self::con()->caps->hasCap( 'scan_file_locker' ) ) {
			throw new \Exception( 'File Locker is unavailable.' );
		}

		if ( ( new GetFileLockCandidateDisplays() )->forFileKey( $fileKey ) === null ) {
			throw new \Exception( 'File Locker candidate is unavailable.' );
		}
	}
}
