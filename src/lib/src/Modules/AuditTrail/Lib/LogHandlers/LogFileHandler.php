<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\LogHandlers;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\{
	ModCon,
	Options
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class LogFileHandler extends StreamHandler {

	use ModConsumer;

	public function __construct( ModCon $modCon, $level = Logger::DEBUG, $bubble = true, $filePermission = null, $useLocking = false ) {
		$this->setMod( $modCon );

		parent::__construct( $this->getLogFilePath(), $level, $bubble, $filePermission, $useLocking );

		$this->rotateLogs();
	}

	public function getLogFilePath() :string {
		/** @var Options $opts */
		$opts = $this->getOptions();
		return $opts->getLogFilePath();
	}

	public function getLogFileRotationLimit() :int {
		/** @var Options $opts */
		$opts = $this->getOptions();
		return $opts->getLogFileRotationLimit();
	}

	private function rotateLogs() {
		if ( apply_filters( 'shield/audit_trail_rotate_log_files', true ) ) {
			try {
				( new Utility\LogFileRotate( $this->getLogFilePath(), $this->getLogFileRotationLimit() ) )->run();
			}
			catch ( \Exception $e ) {
			}
		}
	}
}