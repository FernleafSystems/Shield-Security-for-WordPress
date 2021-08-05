<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\LogHandlers\Utility;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Common\ExecOnceModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class LogFileRotate extends ExecOnceModConsumer {

	private $logFile;

	public function __construct( string $logFile ) {
		$this->logFile = $logFile;
	}

	/**
	 * @throws \Exception
	 */
	public function run() {
		$FS = Services::WpFs();
		if ( !$FS->mkdir( dirname( $this->logFile ) ) ) {
			throw new \Exception( 'Could not create logs dir' );
		}

		$startOfDay = Services::Request()->carbon( true )->startOfDay()->timestamp;

		if ( $FS->isFile( $this->logFile )
			 && $FS->getModifiedTime( $this->logFile ) < $startOfDay ) {
			$this->rotateLogs( (int)apply_filters( 'shield/file_log_rotation_limit', 5 ) );
		}
	}

	protected function rotateLogs( int $limit = 5 ) {
		$FS = Services::WpFs();

		$basePath = $this->logFile;
		for ( $i = $limit ; $i >= 0 ; $i-- ) {

			$suffix = $i === 0 ? '' : '.'.$i;
			$fileToRotate = $basePath.$suffix;

			if ( $FS->isFile( $fileToRotate ) ) {
				$FS->move( $fileToRotate, $basePath.'.'.( $i + 1 ) );
			}
		}

		$excessFile = $basePath.'.'.( $limit + 1 );
		if ( $FS->isFile( $excessFile ) ) {
			$FS->deleteFile( $excessFile );
		}
	}
}