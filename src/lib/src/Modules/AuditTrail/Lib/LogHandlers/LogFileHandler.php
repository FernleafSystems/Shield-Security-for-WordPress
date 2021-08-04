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

		/** @var Options $opts */
		$opts = $this->getOptions();

		parent::__construct( $this->getLogFilePath(), $opts->getLogLevelFile(), $bubble, $filePermission, $useLocking );

		$this->rotateLogs();
	}

	private function getLogFilePath() :string {
		return $this->getCon()->getPluginCachePath( 'logs/shield.log' );
	}

	private function rotateLogs() {
		if ( apply_filters( 'shield/audit_trail_rotate_log_files', true ) ) {
			try {
				( new Utility\LogFileRotate( $this->getLogFilePath() ) )->run();
			}
			catch ( \Exception $e ) {
			}
		}
	}
}