<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\LogHandlers;

use AptowebDeps\Monolog\Handler\StreamHandler;
use AptowebDeps\Monolog\Logger;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\ModConsumer;

class LogFileHandler extends StreamHandler {

	use ModConsumer;

	public function __construct( $level = Logger::DEBUG, $bubble = true, $filePermission = null, $useLocking = false ) {
		$audit = self::con()->getModule_AuditTrail()->getAuditCon();
		$path = \method_exists( $audit, 'getLogFilePath' ) ? $audit->getLogFilePath() : $this->opts()->getLogFilePath();

		parent::__construct( $path, $level, $bubble, $filePermission, $useLocking );
		$this->rotateLogs();
	}

	private function rotateLogs() {
		$auditCon = self::con()->getModule_AuditTrail()->getAuditCon();
		if ( \method_exists( $auditCon, 'getLogFilePath' ) ) {
			$path = $auditCon->getLogFilePath();
			$limit = $auditCon->getLogFileRotationLimit();
		}
		else {
			$path = $this->opts()->getLogFilePath();
			$limit = $this->opts()->getLogFileRotationLimit();
		}

		if ( apply_filters( 'shield/audit_trail_rotate_log_files', true ) ) {
			try {
				( new Utility\LogFileRotate( $path, $limit ) )->run();
			}
			catch ( \Exception $e ) {
			}
		}
	}
}