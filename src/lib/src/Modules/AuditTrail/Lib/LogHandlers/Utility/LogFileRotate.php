<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\LogHandlers\Utility;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Services\Services;

/**
 * @deprecated 19.2
 */
class LogFileRotate {

	use ExecOnce;

	private $logFile;

	/**
	 * @var int
	 */
	private $limit;

	public function __construct( string $logFile, int $limit = 5 ) {
		$this->logFile = $logFile;
		$this->limit = $limit;
	}

	/**
	 * @throws \Exception
	 */
	public function run() {
		$FS = Services::WpFs();
		if ( !$FS->mkdir( \dirname( $this->logFile ) ) ) {
			throw new \Exception( 'Could not create logs dir' );
		}

		$startOfDay = Services::Request()->carbon( true )->startOfDay()->timestamp;

		if ( $FS->isAccessibleFile( $this->logFile ) && $FS->getModifiedTime( $this->logFile ) < $startOfDay ) {
			$this->rotateLogs();
		}
	}

	protected function rotateLogs() {
		$FS = Services::WpFs();
		$limit = (int)\max( 1, \apply_filters( 'shield/file_log_rotation_limit', $this->limit ) );

		$basePath = $this->logFile;
		for ( $i = $limit ; $i >= 0 ; $i-- ) {

			$suffix = $i === 0 ? '' : '.'.$i;
			$fileToRotate = $basePath.$suffix;

			if ( $FS->isAccessibleFile( $fileToRotate ) ) {
				$FS->move( $fileToRotate, $basePath.'.'.( $i + 1 ) );
			}
		}

		$excessFile = $basePath.'.'.( $limit + 1 );
		if ( $FS->isAccessibleFile( $excessFile ) ) {
			$FS->deleteFile( $excessFile );
		}
	}
}