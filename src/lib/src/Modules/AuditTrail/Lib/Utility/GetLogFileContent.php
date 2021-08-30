<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\Lib\Utility;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\{
	ModCon,
	Lib\LogHandlers\LogFileHandler
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use Monolog\Handler\FilterHandler;

class GetLogFileContent {

	use ModConsumer;

	public function run() :string {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$handlers = $mod->getAuditLogger()
						->getLogger()
						->getHandlers();

		$logFileHandler = null;
		foreach ( $handlers as $handler ) {
			if ( $handler instanceof FilterHandler ) {
				$handler = $handler->getHandler();
			}
			if ( $handler instanceof LogFileHandler ) {
				$logFileHandler = $handler;
				break;
			}
		}

		$content = '';
		if ( !empty( $logFileHandler ) ) {
			$logFile = $logFileHandler->getLogFilePath();
			$FS = Services::WpFs();
			if ( $FS->isFile( $logFile ) ) {
				$content = (string)$FS->getFileContent( $logFile );
			}
		}

		return $content;
	}
}