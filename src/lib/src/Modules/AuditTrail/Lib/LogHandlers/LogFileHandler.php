<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\LogHandlers;

use AptowebDeps\Monolog\Handler\StreamHandler;
use AptowebDeps\Monolog\Logger;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\ModConsumer;

class LogFileHandler extends StreamHandler {

	use ModConsumer;

	public function __construct( $level = Logger::DEBUG, $bubble = true, $filePermission = null, $useLocking = false ) {
		parent::__construct( $this->opts()->getLogFilePath(), $level, $bubble, $filePermission, $useLocking );
		$this->rotateLogs();
	}

	private function rotateLogs() {
		if ( apply_filters( 'shield/audit_trail_rotate_log_files', true ) ) {
			try {
				( new Utility\LogFileRotate(
					$this->opts()->getLogFilePath(),
					$this->opts()->getLogFileRotationLimit()
				) )->run();
			}
			catch ( \Exception $e ) {
			}
		}
	}
}