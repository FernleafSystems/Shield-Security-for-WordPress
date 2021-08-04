<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\LogHandlers;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Options;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use Monolog\Handler\StreamHandler;

class LogFileHandler extends StreamHandler {

	use ModConsumer;

	public function __construct( ModCon $modCon, $bubble = true, $filePermission = null, $useLocking = false ) {
		$this->setMod( $modCon );
		parent::__construct( $this->getLogFilePath(), $this->getLogLevel(), $bubble, $filePermission, $useLocking );
		$this->rotateLogs();
	}

	protected function getLogFilePath() :string {
		return $this->getCon()->getPluginCachePath( 'logs/shield.log' );
	}

	protected function getLogLevel() :string {
		/** @var Options $opts */
		$opts = $this->getOptions();
		$level = $opts->getOpt( 'log_level_db' );
		return $level === 'same_as_db' ? $opts->getLogLevelDB() : $level;
	}

	protected function rotateLogs() {
		if ( (bool)apply_filters( 'shield/audit_trail_rotate_log_files', true ) ) {
			try {
				( new Utility\LogFileRotate( $this->getLogFilePath() ) )->run();
			}
			catch ( \Exception $e ) {
			}
		}
	}
}